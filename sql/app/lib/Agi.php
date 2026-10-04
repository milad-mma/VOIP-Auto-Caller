<?php
/**
 * Tiny AGI protocol implementation + the IVR logic executed for every answered call.
 * Invoked from the dialplan: exten => s,1,AGI(/opt/autocaller/bin/agi.php)
 */
class Agi
{
    public $env = array();
    private $in;
    private $out;

    public function __construct()
    {
        $this->in = defined('STDIN') ? STDIN : fopen('php://stdin', 'r');
        $this->out = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w');
        while (($line = fgets($this->in)) !== false) {
            $line = trim($line);
            if ($line === '') {
                break;
            }
            $p = strpos($line, ':');
            if ($p !== false) {
                $this->env[trim(substr($line, 0, $p))] = trim(substr($line, $p + 1));
            }
        }
    }

    public function envv($k, $d = '')
    {
        return isset($this->env['agi_' . $k]) ? $this->env['agi_' . $k] : $d;
    }

    /** @return array(code, result, data) */
    public function cmd($command)
    {
        fwrite($this->out, $command . "\n");
        fflush($this->out);
        $line = fgets($this->in);
        if ($line === false) {
            return array(510, -1, '');
        }
        $line = trim($line);
        // multi-line 520 usage errors
        if (strpos($line, '520-') === 0) {
            while (($l = fgets($this->in)) !== false) {
                if (strpos(trim($l), '520 ') === 0) {
                    break;
                }
            }
            return array(520, -1, '');
        }
        $code = (int)substr($line, 0, 3);
        $result = -1;
        $data = '';
        if (preg_match('/result=(-?[0-9]+)(.*)$/', $line, $m)) {
            $result = (int)$m[1];
            $data = trim($m[2]);
        } elseif (preg_match('/result=(\S+)(.*)$/', $line, $m)) {
            $result = $m[1];
            $data = trim($m[2]);
        }
        return array($code, $result, $data);
    }

    public function verbose($msg, $level = 1)
    {
        return $this->cmd('VERBOSE "' . str_replace('"', "'", Util::oneLine($msg, 200)) . '" ' . (int)$level);
    }

    public function getVariable($name)
    {
        $r = $this->cmd('GET VARIABLE ' . $name);
        if ($r[1] == 1 && preg_match('/\((.*)\)/', $r[2], $m)) {
            return $m[1];
        }
        return '';
    }

    public function setVariable($name, $value)
    {
        return $this->cmd('SET VARIABLE ' . $name . ' "' . str_replace('"', '', Util::oneLine($value, 200)) . '"');
    }

    public function answer()
    {
        return $this->cmd('ANSWER');
    }

    public function hangup()
    {
        return $this->cmd('HANGUP');
    }

    /** Stream a file (no extension). Returns pressed digit as string, '' if none, null if hangup. */
    public function streamFile($file, $escape = '0123456789*#')
    {
        $r = $this->cmd('STREAM FILE "' . $file . '" "' . $escape . '"');
        if ($r[0] !== 200 || $r[1] == -1) {
            return null;
        }
        if ($r[1] > 0) {
            return chr((int)$r[1]);
        }
        return '';
    }

    /** Wait up to $ms for a digit. Returns digit, '' on timeout, null on hangup/error */
    public function waitForDigit($ms)
    {
        $r = $this->cmd('WAIT FOR DIGIT ' . (int)$ms);
        if ($r[0] !== 200 || $r[1] == -1) {
            return null;
        }
        if ($r[1] > 0) {
            return chr((int)$r[1]);
        }
        return '';
    }

    public function exec($app, $args = '')
    {
        return $this->cmd('EXEC ' . $app . ' "' . str_replace('"', '', $args) . '"');
    }

    public function channelAlive()
    {
        $r = $this->cmd('CHANNEL STATUS');
        return $r[0] === 200 && $r[1] >= 0;
    }
}

/**
 * The IVR for outbound campaign calls.
 */
class AgiIvr
{
    /** @var Agi */
    private $agi;
    /** @var Db */
    private $db;
    private $attempt;
    private $contact;
    private $campaign;
    private $ivr;
    private $dtmf = '';
    private $tag = null;
    private $finalStatus = null;

    public function __construct(Agi $agi)
    {
        $this->agi = $agi;
        $this->db = Db::get();
    }

    public function run()
    {
        $attemptId = (int)$this->agi->getVariable('AC_ATTEMPT');
        $contactId = (int)$this->agi->getVariable('AC_CONTACT');
        $uniqueid = $this->agi->envv('uniqueid');
        $this->attempt = $attemptId ? $this->db->one('SELECT * FROM call_attempts WHERE id = ?', array($attemptId)) : null;
        $this->contact = $contactId ? $this->db->one('SELECT * FROM campaign_contacts WHERE id = ?', array($contactId)) : null;
        if (!$this->attempt || !$this->contact) {
            $this->agi->verbose('autocaller: unknown call (no AC_ATTEMPT/AC_CONTACT)');
            $this->agi->hangup();
            return;
        }
        $this->campaign = Campaign::find($this->contact['campaign_id']);
        if (!$this->campaign) {
            $this->agi->hangup();
            return;
        }
        $this->ivr = Campaign::parseIvr($this->campaign['ivr_config']);
        $now = Util::now();

        // mark answered
        $this->db->update('call_attempts', array('answered_at' => $this->attempt['answered_at'] ? $this->attempt['answered_at'] : $now, 'uniqueid' => $uniqueid), 'id = ?', array($attemptId));
        $upd = array('updated_at' => $now, 'uniqueid' => $uniqueid);
        if (in_array($this->contact['status'], array(CallStatus::DIALING, CallStatus::PENDING), true)) {
            $upd['status'] = CallStatus::ANSWERED;
            $upd['answered_at'] = $now;
        }
        $this->db->update('campaign_contacts', $upd, 'id = ?', array($contactId));
        Logger::info("agi: call answered contact $contactId attempt $attemptId uniqueid $uniqueid");

        // answering machine detection
        if ((int)$this->campaign['amd'] === 1 || Settings::get('amd_enabled') === '1') {
            $this->agi->exec('AMD');
            $amd = strtoupper($this->agi->getVariable('AMDSTATUS'));
            $this->db->update('call_attempts', array('amd_result' => $amd), 'id = ?', array($attemptId));
            $this->db->update('campaign_contacts', array('amd_result' => $amd), 'id = ?', array($contactId));
            if ($amd === 'MACHINE') {
                $this->finish(CallStatus::MACHINE, 'amd');
                $this->agi->hangup();
                return;
            }
        }

        $audio = $this->audioPath(!empty($this->contact['audio_id']) ? $this->contact['audio_id'] : $this->campaign['audio_id']);
        if ($audio === null) {
            $this->agi->verbose('autocaller: no audio file for this call');
            Logger::error("agi: missing audio for contact $contactId");
            $this->db->update('campaign_contacts', array('last_error' => 'missing audio'), 'id = ?', array($contactId));
            $this->agi->hangup();
            return;
        }

        $escape = implode('', array_keys($this->ivr['digits']));
        // no keys configured: play once, ignore key presses
        $repeats = $escape === '' ? 1 : (int)$this->ivr['max_repeats'] + 1;
        $timeoutMs = max(1, (int)$this->ivr['timeout_sec']) * 1000;
        $replays = 0;

        for ($i = 0; $i < $repeats; $i++) {
            $d = $this->agi->streamFile($audio, $escape);
            if ($d === null) {
                return $this->hungUp();
            }
            $unknown = 0;
            while (true) {
                if ($d === '') {
                    if ($escape === '') {
                        break; // nothing to wait for
                    }
                    $d = $this->agi->waitForDigit($timeoutMs);
                    if ($d === null) {
                        return $this->hungUp();
                    }
                    if ($d === '') {
                        break; // timeout -> next repeat
                    }
                }
                $this->dtmf .= $d;
                $action = $this->handleDigit($d, $replays);
                if ($action === 'done') {
                    return null; // transferred or hung up
                }
                if ($action === 'replay') {
                    $replays++;
                    if ($replays <= 3) {
                        $i--; // a replay does not consume a repeat
                    }
                    break;
                }
                // unknown key: keep listening a little, bounded
                $unknown++;
                if ($unknown > 3 || strlen($this->dtmf) > 12) {
                    break;
                }
                $d = '';
            }
        }
        // nothing pressed
        $this->finish(CallStatus::COMPLETED, null);
        $this->agi->hangup();
    }

    private function hungUp()
    {
        // caller hung up in the middle. Daemon's Hangup handler finalizes; store dtmf now.
        $this->storeDtmf();
        return null;
    }

    private function storeDtmf()
    {
        if ($this->dtmf !== '') {
            $this->db->update('campaign_contacts', array('dtmf' => substr($this->dtmf, 0, 16), 'updated_at' => Util::now()), 'id = ?', array((int)$this->contact['id']));
            $this->db->update('call_attempts', array('dtmf' => substr($this->dtmf, 0, 16)), 'id = ?', array((int)$this->attempt['id']));
        }
    }

    /** @return 'done' | 'replay' | 'ignore' */
    private function handleDigit($d, $replays)
    {
        if (!isset($this->ivr['digits'][$d])) {
            return 'ignore';
        }
        $cfg = $this->ivr['digits'][$d];
        $tag = isset($cfg['tag']) && $cfg['tag'] !== '' ? $cfg['tag'] : null;
        switch ($cfg['action']) {
            case 'replay':
                $this->storeDtmf();
                return 'replay';
            case 'transfer':
                $this->finish(CallStatus::COMPLETED, $tag ? $tag : 'transfer:' . $cfg['target']);
                Logger::info("agi: transfer contact {$this->contact['id']} -> {$cfg['target']}@{$cfg['context']}");
                $this->agi->setVariable('AC_TRANSFER', $cfg['target']);
                $this->agi->exec('Goto', $cfg['context'] . ',' . $cfg['target'] . ',1');
                return 'done';
            case 'dnc':
                Dnc::add($this->contact['phone'], 'IVR key ' . $d . ' campaign #' . $this->campaign['id'], 'ivr', null);
                $this->finish(CallStatus::DNC, $tag ? $tag : 'dnc');
                $this->agi->hangup();
                return 'done';
            case 'play':
                $p = $this->audioPath($cfg['audio_id']);
                if ($p !== null) {
                    $this->agi->streamFile($p, '');
                }
                $this->finish(CallStatus::COMPLETED, $tag);
                $this->agi->hangup();
                return 'done';
            case 'tag':
                $this->finish(CallStatus::COMPLETED, $tag);
                $this->agi->hangup();
                return 'done';
            case 'hangup':
            default:
                $this->finish(CallStatus::COMPLETED, $tag);
                $this->agi->hangup();
                return 'done';
        }
    }

    private function finish($status, $tag)
    {
        $now = Util::now();
        $this->storeDtmf();
        $upd = array('updated_at' => $now);
        if ($tag !== null) {
            $upd['result_tag'] = mb_substr($tag, 0, 64, 'UTF-8');
        }
        if ($status !== CallStatus::COMPLETED) {
            // dnc/machine are decided here; 'completed' is stamped by the daemon on Hangup (to get duration)
            $upd['status'] = $status;
        }
        $this->db->update('campaign_contacts', $upd, 'id = ?', array((int)$this->contact['id']));
    }

    /** Absolute path without extension, or null */
    private function audioPath($audioId)
    {
        $audioId = (int)$audioId;
        if ($audioId <= 0) {
            return null;
        }
        $a = $this->db->one('SELECT * FROM audio_files WHERE id = ?', array($audioId));
        if (!$a) {
            return null;
        }
        $full = Config::storage($a['path']);
        if (!is_file($full)) {
            return null;
        }
        return preg_replace('/\.[A-Za-z0-9]+$/', '', $full);
    }
}
