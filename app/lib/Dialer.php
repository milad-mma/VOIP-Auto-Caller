<?php
/**
 * The dialer engine. Runs forever inside bin/dialer.php (systemd service).
 *
 *  - originates calls through AMI (async) for every running campaign
 *  - respects global / per-campaign concurrency, pacing, schedule windows, holidays, DNC
 *  - consumes AMI events (OriginateResponse, Hangup) to record the real outcome
 *  - retries no-answer/busy/... according to campaign rules
 *  - optional CDR enrichment (asteriskcdrdb) for exact billsec/disposition
 */
class Dialer
{
    /** @var Ami */
    private $ami;
    /** @var Db */
    private $db;
    private $running = true;
    private $lastHeartbeat = 0;
    private $lastStaleCheck = 0;
    private $lastFinishCheck = 0;
    private $lastReconnect = 0;
    private $reconnectDelay = 2;
    private $lastPing = 0;
    /** actionId => array(attempt_id, contact_id, campaign_id, started) */
    private $pendingOriginate = array();
    /** uniqueid => attempt_id */
    private $liveByUniqueid = array();
    /** campaign_id => microtime of last originate */
    private $lastDial = array();
    private $cdrPdo = false;

    public function __construct()
    {
        $this->db = Db::get();
        $this->ami = Ami::fromConfig();
    }

    public function stop()
    {
        $this->running = false;
    }

    public function run()
    {
        Logger::info('dialer starting v' . AC_VERSION . ' pid ' . getmypid());
        $this->db->update('daemon_status', array(
            'pid' => getmypid(), 'started_at' => Util::now(), 'heartbeat_at' => Util::now(),
            'version' => AC_VERSION, 'ami_connected' => 0, 'last_error' => null,
        ), 'id = 1');
        $this->recoverAfterRestart();

        if (function_exists('pcntl_signal')) {
            $self = $this;
            pcntl_signal(SIGTERM, function () use ($self) {
                $self->stop();
            });
            pcntl_signal(SIGINT, function () use ($self) {
                $self->stop();
            });
        }

        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            try {
                $this->tick();
            } catch (AmiException $e) {
                Logger::warn('AMI error: ' . $e->getMessage());
                $this->ami->close();
                $this->setError($e->getMessage());
                usleep(500000);
            } catch (PDOException $e) {
                Logger::error('DB error: ' . $e->getMessage());
                $this->setError('DB: ' . $e->getMessage());
                sleep(2);
                $this->db->ping();
            } catch (Exception $e) {
                Logger::error('tick error: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
                $this->setError($e->getMessage());
                usleep(500000);
            }
        }
        Logger::info('dialer stopped');
        $this->db->update('daemon_status', array('pid' => null, 'ami_connected' => 0, 'heartbeat_at' => Util::now()), 'id = 1');
        $this->ami->close();
    }

    private function setError($msg)
    {
        try {
            $this->db->update('daemon_status', array('last_error' => Util::oneLine($msg, 250)), 'id = 1');
        } catch (Exception $e) {
        }
    }

    private function tick()
    {
        $now = time();

        if (!$this->ami->isConnected()) {
            if ($now - $this->lastReconnect >= $this->reconnectDelay) {
                $this->lastReconnect = $now;
                try {
                    $this->ami->connect();
                    $this->reconnectDelay = 2;
                    Logger::info('AMI connected');
                    $this->db->update('daemon_status', array('ami_connected' => 1, 'last_error' => null), 'id = 1');
                } catch (AmiException $e) {
                    $this->reconnectDelay = min(30, $this->reconnectDelay * 2);
                    $this->db->update('daemon_status', array('ami_connected' => 0, 'last_error' => Util::oneLine($e->getMessage(), 250), 'heartbeat_at' => Util::now()), 'id = 1');
                    Logger::warn($e->getMessage() . ' (retry in ' . $this->reconnectDelay . 's)');
                }
            }
            sleep(1);
            return;
        }

        // pump socket, handle events
        $this->ami->pump(200);
        foreach ($this->ami->events() as $ev) {
            $this->handleEvent($ev);
        }

        if ($now - $this->lastPing >= 30) {
            $this->lastPing = $now;
            if (!$this->ami->ping()) {
                throw new AmiException('ping timeout');
            }
        }

        if ($now - $this->lastHeartbeat >= 5) {
            $this->lastHeartbeat = $now;
            $this->db->ping();
            $active = (int)$this->db->val("SELECT COUNT(*) FROM campaign_contacts WHERE status IN ('dialing','answered')");
            $this->db->update('daemon_status', array('heartbeat_at' => Util::now(), 'ami_connected' => 1, 'active_calls' => $active, 'pid' => getmypid()), 'id = 1');
            Settings::reset();
        }

        if ($now - $this->lastStaleCheck >= 20) {
            $this->lastStaleCheck = $now;
            $this->checkStale();
        }

        $this->dialStep();

        if ($now - $this->lastFinishCheck >= 10) {
            $this->lastFinishCheck = $now;
            $this->autoStartScheduled();
            foreach ($this->db->all("SELECT id FROM campaigns WHERE status = 'running'") as $c) {
                Campaign::finishIfDone($c['id']);
            }
        }
    }

    // ------------------------------------------------------------------ dialing

    private function dialStep()
    {
        $globalMax = (int)Settings::get('global_max_concurrent', 4);
        $globalActive = (int)$this->db->val("SELECT COUNT(*) FROM campaign_contacts WHERE status IN ('dialing','answered')");
        if ($globalActive >= $globalMax) {
            return;
        }
        $campaigns = $this->db->all("SELECT * FROM campaigns WHERE status = 'running' ORDER BY priority DESC, id ASC");
        foreach ($campaigns as $c) {
            if ($globalActive >= $globalMax) {
                break;
            }
            list($ok, $reason) = Schedule::canDial($c);
            if (!$ok) {
                if ($c['last_error'] !== 'wait:' . $reason) {
                    $this->db->update('campaigns', array('last_error' => 'wait:' . $reason), 'id = ?', array((int)$c['id']));
                }
                continue;
            }
            $gap = max(0, (int)$c['gap_ms']) / 1000.0;
            $last = isset($this->lastDial[$c['id']]) ? $this->lastDial[$c['id']] : 0;
            if (microtime(true) - $last < $gap) {
                continue;
            }
            $cActive = (int)$this->db->val("SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('dialing','answered')", array((int)$c['id']));
            if ($cActive >= max(1, (int)$c['concurrent'])) {
                continue;
            }
            $contact = $this->db->one(
                "SELECT * FROM campaign_contacts WHERE campaign_id = ? AND status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= NOW()) ORDER BY next_attempt_at IS NULL DESC, id ASC LIMIT 1",
                array((int)$c['id'])
            );
            if (!$contact) {
                continue;
            }
            if ($c['last_error'] !== null) {
                $this->db->update('campaigns', array('last_error' => null), 'id = ?', array((int)$c['id']));
            }
            if ($this->originate($c, $contact)) {
                $globalActive++;
                $this->lastDial[$c['id']] = microtime(true);
            }
        }
    }

    private function originate(array $c, array $contact)
    {
        $now = Util::now();
        // DNC check at dial time (list may have grown since import)
        if (Dnc::has($contact['phone'])) {
            $this->db->update('campaign_contacts', array('status' => CallStatus::DNC, 'updated_at' => $now, 'last_error' => 'dnc'), 'id = ?', array((int)$contact['id']));
            return false;
        }
        $eff = Campaign::effective($c);
        $channel = Campaign::channelFor($eff, $contact['phone']);
        $callerId = self::callerIdString($eff['callerid_name'], $eff['callerid_number']);
        $attemptNo = (int)$contact['attempts'] + 1;
        $actionId = 'ac-' . $contact['id'] . '-' . $attemptNo . '-' . substr(md5(uniqid('', true)), 0, 6);

        $attemptId = $this->db->insert('call_attempts', array(
            'contact_id' => (int)$contact['id'],
            'campaign_id' => (int)$c['id'],
            'attempt_no' => $attemptNo,
            'action_id' => $actionId,
            'channel' => $channel,
            'started_at' => $now,
            'result' => 'dialing',
        ));
        $this->db->update('campaign_contacts', array(
            'status' => CallStatus::DIALING, 'attempts' => $attemptNo, 'last_attempt_at' => $now,
            'next_attempt_at' => null, 'updated_at' => $now, 'last_error' => null, 'uniqueid' => null,
        ), 'id = ?', array((int)$contact['id']));

        $vars = array(
            'AC_ATTEMPT' => $attemptId,
            'AC_CONTACT' => (int)$contact['id'],
            'AC_CAMPAIGN' => (int)$c['id'],
        );
        try {
            $this->ami->originate(
                $channel, 'autocaller-ivr', 's', 1,
                max(5, (int)$c['ring_timeout']) * 1000,
                $callerId, $vars, $actionId
            );
        } catch (AmiException $e) {
            $this->finishAttempt($attemptId, CallStatus::FAILED, array('reason_code' => 'ami', 'hangup_cause' => Util::oneLine($e->getMessage(), 60)));
            throw $e;
        }
        $this->pendingOriginate[$actionId] = array('attempt' => $attemptId, 'contact' => (int)$contact['id'], 'campaign' => (int)$c['id'], 'at' => time());
        $this->db->update('campaigns', array('last_dial_at' => $now), 'id = ?', array((int)$c['id']));
        Logger::info("originate #$attemptId contact {$contact['id']} -> {$contact['phone']} via $channel");
        return true;
    }

    /** "Name" <number>, number-only, or name-only - never an empty <> which some dialplans/trunks choke on */
    public static function callerIdString($name, $number)
    {
        $name = trim(str_replace(array('"', '<', '>', "\r", "\n"), '', (string)$name));
        $number = Util::dialSafe($number);
        if ($number !== '' && $name !== '') {
            return '"' . $name . '" <' . $number . '>';
        }
        if ($number !== '') {
            return $number;
        }
        return $name !== '' ? $name : 'AutoCaller';
    }

    // ------------------------------------------------------------------ events

    private function handleEvent(array $ev)
    {
        $name = $ev['Event'];
        if ($name === 'OriginateResponse') {
            $this->onOriginateResponse($ev);
        } elseif ($name === 'Hangup') {
            $this->onHangup($ev);
        }
    }

    private function onOriginateResponse(array $ev)
    {
        $actionId = isset($ev['ActionID']) ? $ev['ActionID'] : '';
        if (strpos($actionId, 'ac-') !== 0) {
            return;
        }
        $att = $this->attemptByAction($actionId);
        if (!$att) {
            return;
        }
        unset($this->pendingOriginate[$actionId]);
        $reason = isset($ev['Reason']) ? (int)$ev['Reason'] : 0;
        $uniqueid = isset($ev['Uniqueid']) && $ev['Uniqueid'] !== '<null>' ? $ev['Uniqueid'] : null;
        $success = isset($ev['Response']) && strtolower($ev['Response']) === 'success';

        if ($success || $reason === 4) {
            $data = array('reason_code' => (string)$reason);
            if ($uniqueid) {
                $data['uniqueid'] = $uniqueid;
                $this->liveByUniqueid[$uniqueid] = (int)$att['id'];
            }
            if ($att['answered_at'] === null) {
                $data['answered_at'] = Util::now();
            }
            $this->db->update('call_attempts', $data, 'id = ?', array((int)$att['id']));
            $contact = $this->db->one('SELECT status, uniqueid FROM campaign_contacts WHERE id = ?', array((int)$att['contact_id']));
            $upd = array('updated_at' => Util::now());
            if ($contact && $contact['status'] === CallStatus::DIALING) {
                $upd['status'] = CallStatus::ANSWERED;
                $upd['answered_at'] = Util::now();
            }
            if ($uniqueid && $contact && empty($contact['uniqueid'])) {
                $upd['uniqueid'] = $uniqueid;
            }
            $this->db->update('campaign_contacts', $upd, 'id = ?', array((int)$att['contact_id']));
            Logger::info("answered attempt #{$att['id']} uniqueid=$uniqueid");
            return;
        }
        $status = CallStatus::fromReason($reason);
        $cause = isset($ev['Message']) ? $ev['Message'] : '';
        $this->finishAttempt((int)$att['id'], $status, array('reason_code' => (string)$reason, 'hangup_cause' => Util::oneLine($cause, 60)));
    }

    private function onHangup(array $ev)
    {
        $uniqueid = isset($ev['Uniqueid']) ? $ev['Uniqueid'] : '';
        if ($uniqueid === '') {
            return;
        }
        $attemptId = null;
        if (isset($this->liveByUniqueid[$uniqueid])) {
            $attemptId = $this->liveByUniqueid[$uniqueid];
        } else {
            // the AGI may have recorded the uniqueid before OriginateResponse reached us
            $row = $this->db->one("SELECT id FROM call_attempts WHERE uniqueid = ? AND result IN ('dialing','answered') LIMIT 1", array($uniqueid));
            if ($row) {
                $attemptId = (int)$row['id'];
            }
        }
        if ($attemptId === null) {
            return;
        }
        unset($this->liveByUniqueid[$uniqueid]);
        $cause = isset($ev['Cause']) ? (int)$ev['Cause'] : 0;
        $causeTxt = isset($ev['Cause-txt']) ? $ev['Cause-txt'] : '';
        $att = $this->db->one('SELECT * FROM call_attempts WHERE id = ?', array($attemptId));
        if (!$att || !in_array($att['result'], array('dialing', 'answered'), true)) {
            return;
        }
        $contact = $this->db->one('SELECT * FROM campaign_contacts WHERE id = ?', array((int)$att['contact_id']));
        // AGI may already have decided (dnc / machine / transfer tag). Keep its verdict.
        $status = CallStatus::COMPLETED;
        if ($contact && in_array($contact['status'], array(CallStatus::DNC, CallStatus::MACHINE, CallStatus::COMPLETED), true)) {
            $status = $contact['status'];
        } elseif ($att['answered_at'] === null && (!$contact || $contact['status'] === CallStatus::DIALING)) {
            $status = CallStatus::fromCause($cause);
            if ($status === CallStatus::COMPLETED) {
                $status = CallStatus::FAILED;
            }
        }
        $this->finishAttempt($attemptId, $status, array('hangup_cause' => Util::oneLine($cause . ' ' . $causeTxt, 60)));
    }

    private function attemptByAction($actionId)
    {
        if (isset($this->pendingOriginate[$actionId])) {
            return $this->db->one('SELECT * FROM call_attempts WHERE id = ?', array($this->pendingOriginate[$actionId]['attempt']));
        }
        return $this->db->one('SELECT * FROM call_attempts WHERE action_id = ? LIMIT 1', array($actionId));
    }

    /**
     * Terminal update of an attempt and its contact, retry scheduling, counters.
     */
    private function finishAttempt($attemptId, $status, array $extra = array())
    {
        $att = $this->db->one('SELECT * FROM call_attempts WHERE id = ?', array((int)$attemptId));
        if (!$att) {
            return;
        }
        $now = Util::now();
        $contact = $this->db->one('SELECT * FROM campaign_contacts WHERE id = ?', array((int)$att['contact_id']));
        $c = Campaign::find($att['campaign_id']);
        $duration = 0;
        if ($att['answered_at'] !== null) {
            $duration = max(0, time() - strtotime($att['answered_at']));
        }
        $upd = array_merge(array('result' => $status, 'ended_at' => $now, 'duration_sec' => $duration), $extra);
        if ($contact && $contact['dtmf'] !== null && $att['dtmf'] === null) {
            $upd['dtmf'] = $contact['dtmf'];
        }
        $this->db->update('call_attempts', $upd, 'id = ?', array((int)$attemptId));

        if ($contact) {
            $cu = array('updated_at' => $now, 'duration_sec' => $contact['duration_sec'] + $duration, 'hangup_cause' => isset($extra['hangup_cause']) ? $extra['hangup_cause'] : $contact['hangup_cause']);
            $retryable = in_array($status, CallStatus::retryable(), true);
            $retryOn = $c ? array_map('trim', explode(',', $c['retry_on'])) : array();
            $canRetry = $c && $retryable && in_array($status, $retryOn, true) && (int)$contact['attempts'] <= (int)$c['max_retries']
                && !in_array($contact['status'], array(CallStatus::DNC, CallStatus::CANCELLED), true);
            if ($canRetry) {
                $cu['status'] = CallStatus::PENDING;
                $cu['next_attempt_at'] = date('Y-m-d H:i:s', time() + 60 * max(1, (int)$c['retry_delay_min']));
                $cu['last_error'] = 'retry:' . $status;
            } else {
                $cu['status'] = $status;
                $cu['next_attempt_at'] = null;
            }
            $this->db->update('campaign_contacts', $cu, 'id = ?', array((int)$contact['id']));
        }
        if ($c) {
            $this->db->run(
                "UPDATE campaigns SET cnt_done = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status NOT IN ('pending','dialing','answered')), " .
                "cnt_answered = (SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('completed','dnc','machine')), updated_at = ? WHERE id = ?",
                array((int)$c['id'], (int)$c['id'], $now, (int)$c['id'])
            );
        }
        Logger::info("attempt #$attemptId finished: $status (" . (isset($extra['hangup_cause']) ? $extra['hangup_cause'] : '') . ")");

        if ($att['answered_at'] !== null && Settings::get('cdr_lookup') === '1') {
            $this->enrichFromCdr($attemptId, $att['uniqueid']);
        }
    }

    // ------------------------------------------------------------------ housekeeping

    /** Anything left in dialing/answered from a previous daemon run is unknown -> retry once or fail */
    private function recoverAfterRestart()
    {
        $n = $this->db->exec(
            "UPDATE call_attempts SET result = 'failed', ended_at = NOW(), reason_code = 'restart' WHERE result IN ('dialing','answered')"
        );
        $this->db->exec(
            "UPDATE campaign_contacts SET status = 'pending', next_attempt_at = NOW(), updated_at = NOW(), last_error = 'recovered' WHERE status IN ('dialing','answered')"
        );
        if ($n) {
            Logger::warn("recovered $n in-flight attempts after restart");
        }
    }

    private function checkStale()
    {
        $staleMin = max(2, (int)Settings::get('stale_call_minutes', 10));
        // no OriginateResponse after ring timeout + 60s
        $rows = $this->db->all(
            "SELECT a.id, c.ring_timeout FROM call_attempts a JOIN campaigns c ON c.id = a.campaign_id WHERE a.result = 'dialing' AND a.started_at < DATE_SUB(NOW(), INTERVAL 60 SECOND)"
        );
        foreach ($rows as $r) {
            $limit = (int)$r['ring_timeout'] + 60;
            $age = (int)$this->db->val('SELECT TIMESTAMPDIFF(SECOND, started_at, NOW()) FROM call_attempts WHERE id = ?', array((int)$r['id']));
            if ($age > $limit) {
                Logger::warn("attempt #{$r['id']} stale (no response after {$age}s)");
                $this->finishAttempt((int)$r['id'], CallStatus::FAILED, array('reason_code' => 'timeout', 'hangup_cause' => 'no AMI response'));
            }
        }
        // answered but no Hangup within N minutes
        $rows = $this->db->all(
            "SELECT id FROM call_attempts WHERE result IN ('dialing','answered') AND answered_at IS NOT NULL AND answered_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            array($staleMin)
        );
        foreach ($rows as $r) {
            Logger::warn("attempt #{$r['id']} stale (no hangup after {$staleMin}m)");
            $this->finishAttempt((int)$r['id'], CallStatus::COMPLETED, array('reason_code' => 'stale', 'hangup_cause' => 'stale'));
        }
        // in-memory maps cleanup
        foreach ($this->pendingOriginate as $k => $p) {
            if (time() - $p['at'] > 600) {
                unset($this->pendingOriginate[$k]);
            }
        }
        if (count($this->liveByUniqueid) > 2000) {
            $this->liveByUniqueid = array();
        }
    }

    private function autoStartScheduled()
    {
        $rows = $this->db->all("SELECT id FROM campaigns WHERE status = 'scheduled' AND (start_at IS NULL OR start_at <= NOW())");
        foreach ($rows as $r) {
            $this->db->update('campaigns', array('status' => 'running', 'started_at' => Util::now(), 'updated_at' => Util::now()), 'id = ?', array((int)$r['id']));
            Logger::info("campaign #{$r['id']} auto-started");
        }
        // expire
        $rows = $this->db->all("SELECT id FROM campaigns WHERE status IN ('running','scheduled','paused') AND end_at IS NOT NULL AND end_at < NOW()");
        foreach ($rows as $r) {
            $this->db->update('campaigns', array('status' => 'stopped', 'finished_at' => Util::now(), 'updated_at' => Util::now(), 'last_error' => 'expired'), 'id = ?', array((int)$r['id']));
            $this->db->exec("UPDATE campaign_contacts SET status = 'cancelled', updated_at = NOW() WHERE campaign_id = ? AND status = 'pending'", array((int)$r['id']));
            Logger::info("campaign #{$r['id']} expired");
        }
    }

    /** Best effort: read billsec/disposition from asteriskcdrdb using linkedid/uniqueid */
    private function enrichFromCdr($attemptId, $uniqueid)
    {
        if (!$uniqueid) {
            return;
        }
        if ($this->cdrPdo === false) {
            $this->cdrPdo = null;
            $root = Auth::pbxRootPassword();
            if ($root !== null) {
                try {
                    $this->cdrPdo = new PDO('mysql:host=localhost;dbname=asteriskcdrdb;charset=utf8', 'root', $root, array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 2,
                    ));
                } catch (Exception $e) {
                    Logger::warn('CDR db unavailable: ' . $e->getMessage());
                }
            }
        }
        if (!$this->cdrPdo) {
            return;
        }
        try {
            // CDR rows are written a moment after hangup; retry lazily on next heartbeat if empty
            $st = $this->cdrPdo->prepare('SELECT disposition, billsec, duration FROM cdr WHERE uniqueid = ? OR linkedid = ? ORDER BY billsec DESC LIMIT 1');
            $st->execute(array($uniqueid, $uniqueid));
            $row = $st->fetch();
            if ($row && (int)$row['billsec'] > 0) {
                $this->db->update('call_attempts', array('duration_sec' => (int)$row['billsec']), 'id = ?', array((int)$attemptId));
            }
        } catch (Exception $e) {
            Logger::debug('cdr lookup: ' . $e->getMessage());
        }
    }
}
