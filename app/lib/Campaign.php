<?php
/**
 * Campaign domain helpers shared by web, API and the dialer daemon.
 */
class CallStatus
{
    const PENDING = 'pending';
    const DIALING = 'dialing';
    const ANSWERED = 'answered';     // in progress (AGI running)
    const COMPLETED = 'completed';   // answered & finished
    const NOANSWER = 'noanswer';
    const BUSY = 'busy';
    const CONGESTION = 'congestion';
    const FAILED = 'failed';
    const DNC = 'dnc';
    const CANCELLED = 'cancelled';
    const INVALID = 'invalid';
    const MACHINE = 'machine';

    public static function terminal()
    {
        return array(self::COMPLETED, self::NOANSWER, self::BUSY, self::CONGESTION, self::FAILED, self::DNC, self::CANCELLED, self::INVALID, self::MACHINE);
    }

    public static function retryable()
    {
        return array(self::NOANSWER, self::BUSY, self::CONGESTION, self::FAILED, self::MACHINE);
    }

    public static function all()
    {
        return array(self::PENDING, self::DIALING, self::ANSWERED, self::COMPLETED, self::NOANSWER, self::BUSY, self::CONGESTION, self::FAILED, self::DNC, self::CANCELLED, self::INVALID, self::MACHINE);
    }

    /** Map Asterisk OriginateResponse Reason to status */
    public static function fromReason($reason)
    {
        switch ((int)$reason) {
            case 4:
                return self::ANSWERED;
            case 5:
                return self::BUSY;
            case 3:
                return self::NOANSWER;
            case 8:
                return self::CONGESTION;
            case 1:
            case 0:
            default:
                return self::FAILED;
        }
    }

    /** Map Q.931 hangup cause to status (used when no OriginateResponse arrives) */
    public static function fromCause($cause)
    {
        $cause = (int)$cause;
        if ($cause === 16) {
            return self::COMPLETED;
        }
        if ($cause === 17) {
            return self::BUSY;
        }
        if (in_array($cause, array(18, 19, 20, 21), true)) {
            return self::NOANSWER;
        }
        if (in_array($cause, array(34, 38, 41, 42, 44, 58), true)) {
            return self::CONGESTION;
        }
        if (in_array($cause, array(1, 22, 28), true)) {
            return self::INVALID;
        }
        return self::FAILED;
    }
}

class Schedule
{
    /**
     * @return array(bool $ok, string $reason)
     */
    public static function canDial(array $c, $now = null)
    {
        $now = $now === null ? time() : $now;
        if (!empty($c['start_at']) && strtotime($c['start_at']) > $now) {
            return array(false, 'not_started');
        }
        if (!empty($c['end_at']) && strtotime($c['end_at']) < $now) {
            return array(false, 'expired');
        }
        $ws = !empty($c['work_start']) ? $c['work_start'] : Settings::get('work_start', '09:00');
        $we = !empty($c['work_end']) ? $c['work_end'] : Settings::get('work_end', '21:00');
        $wd = !empty($c['work_days']) ? $c['work_days'] : Settings::get('work_days', '0,1,2,3,4,5,6');
        $days = array_map('intval', array_filter(explode(',', $wd), 'strlen'));
        $dow = (int)date('w', $now);
        if ($days && !in_array($dow, $days, true)) {
            return array(false, 'off_day');
        }
        $hm = date('H:i', $now);
        if (Util::isHm($ws) && Util::isHm($we)) {
            if ($ws <= $we) {
                if ($hm < $ws || $hm >= $we) {
                    return array(false, 'off_hours');
                }
            } else { // window crossing midnight
                if ($hm < $ws && $hm >= $we) {
                    return array(false, 'off_hours');
                }
            }
        }
        $respect = isset($c['respect_holidays']) ? (int)$c['respect_holidays'] : (int)Settings::get('respect_holidays', 1);
        if ($respect && self::isHoliday(date('Y-m-d', $now))) {
            return array(false, 'holiday');
        }
        return array(true, '');
    }

    private static $holidayCache = array();

    public static function isHoliday($date)
    {
        if (!isset(self::$holidayCache[$date])) {
            self::$holidayCache = array(); // keep tiny
            self::$holidayCache[$date] = (bool)Db::get()->val('SELECT COUNT(*) FROM holidays WHERE hdate = ?', array($date));
        }
        return self::$holidayCache[$date];
    }
}

class Campaign
{
    /** Default IVR config skeleton */
    public static function defaultIvr()
    {
        return array(
            'timeout_sec' => 5,
            'max_repeats' => 1,
            'digits' => array(),
        );
    }

    public static function ivrActions()
    {
        return array('transfer', 'replay', 'hangup', 'dnc', 'play', 'tag');
    }

    public static function parseIvr($json)
    {
        $d = json_decode((string)$json, true);
        if (!is_array($d)) {
            $d = array();
        }
        $d = array_merge(self::defaultIvr(), $d);
        if (!isset($d['digits']) || !is_array($d['digits'])) {
            $d['digits'] = array();
        }
        return $d;
    }

    /** Validate & clean IVR config coming from a form or API */
    public static function cleanIvr(array $in)
    {
        $out = self::defaultIvr();
        $out['timeout_sec'] = Util::intOr(isset($in['timeout_sec']) ? $in['timeout_sec'] : 5, 5, 1, 30);
        $out['max_repeats'] = Util::intOr(isset($in['max_repeats']) ? $in['max_repeats'] : 1, 1, 0, 5);
        $digits = isset($in['digits']) && is_array($in['digits']) ? $in['digits'] : array();
        foreach ($digits as $k => $v) {
            $k = (string)$k;
            if (!preg_match('/^[0-9*#]$/', $k) || !is_array($v)) {
                continue;
            }
            $action = isset($v['action']) ? (string)$v['action'] : '';
            if ($action === '' || $action === 'none' || !in_array($action, self::ivrActions(), true)) {
                continue;
            }
            $e = array('action' => $action);
            if ($action === 'transfer') {
                $e['target'] = Util::dialSafe(isset($v['target']) ? $v['target'] : '');
                $e['context'] = preg_replace('/[^A-Za-z0-9_\-]/', '', isset($v['context']) ? $v['context'] : 'from-internal');
                if ($e['target'] === '') {
                    continue;
                }
                if ($e['context'] === '') {
                    $e['context'] = 'from-internal';
                }
            }
            if ($action === 'play') {
                $e['audio_id'] = Util::intOr(isset($v['audio_id']) ? $v['audio_id'] : 0, 0, 0);
                if ($e['audio_id'] <= 0) {
                    continue;
                }
            }
            $tag = isset($v['tag']) ? preg_replace('/[^\p{L}\p{N}_\- ]/u', '', (string)$v['tag']) : '';
            $e['tag'] = mb_substr(trim($tag), 0, 64, 'UTF-8');
            $out['digits'][$k] = $e;
        }
        return $out;
    }

    public static function find($id)
    {
        return Db::get()->one('SELECT * FROM campaigns WHERE id = ?', array((int)$id));
    }

    public static function stats($campaignId)
    {
        $rows = Db::get()->all('SELECT status, COUNT(*) n FROM campaign_contacts WHERE campaign_id = ? GROUP BY status', array((int)$campaignId));
        $s = array_fill_keys(CallStatus::all(), 0);
        $total = 0;
        foreach ($rows as $r) {
            $s[$r['status']] = (int)$r['n'];
            $total += (int)$r['n'];
        }
        $s['total'] = $total;
        $s['remaining'] = $s['pending'] + $s['dialing'] + $s['answered'];
        $s['finished'] = $total - $s['remaining'];
        $s['pct'] = $total ? (int)round($s['finished'] * 100 / $total) : 0;
        return $s;
    }

    /** Recount denormalized counters */
    public static function refreshCounters($campaignId)
    {
        $db = Db::get();
        $s = self::stats($campaignId);
        $db->update('campaigns', array(
            'total_contacts' => $s['total'],
            'cnt_done' => $s['finished'],
            'cnt_answered' => $s['completed'] + $s['answered'],
            'updated_at' => Util::now(),
        ), 'id = ?', array((int)$campaignId));
        return $s;
    }

    /** Effective dialing parameters (campaign overrides -> global settings) */
    public static function effective(array $c)
    {
        $e = array();
        $e['callerid_name'] = $c['callerid_name'] !== null && $c['callerid_name'] !== '' ? $c['callerid_name'] : Settings::get('callerid_name', 'AutoCaller');
        $e['callerid_number'] = $c['callerid_number'] !== null && $c['callerid_number'] !== '' ? $c['callerid_number'] : Settings::get('callerid_number', '');
        $e['dial_prefix'] = $c['dial_prefix'] !== null ? $c['dial_prefix'] : Settings::get('dial_prefix', '');
        $e['channel_tech'] = !empty($c['channel_tech']) ? $c['channel_tech'] : Settings::get('channel_tech', 'local');
        $e['trunk_name'] = !empty($c['trunk_name']) ? $c['trunk_name'] : Settings::get('trunk_name', '');
        $e['channel_template'] = Settings::get('channel_template', '');
        $e['outbound_context'] = Settings::get('outbound_context', 'from-internal');
        return $e;
    }

    /** Build the Asterisk channel string for a number.
     *  Local channels get the /n modifier so they are never optimized away
     *  (otherwise the Hangup event for the leg running our IVR would fire at transfer time). */
    public static function channelFor(array $eff, $phone)
    {
        $num = Util::dialSafe($eff['dial_prefix'] . $phone);
        $trunk = preg_replace('/[^A-Za-z0-9_\-\.]/', '', (string)$eff['trunk_name']);
        $local = 'Local/' . $num . '@' . $eff['outbound_context'] . '/n';
        switch ($eff['channel_tech']) {
            case 'pool':
                // resolved by the dialer per call (Trunks::pickFree); shown here only for display
                return 'SIP/{' . (Settings::get('ui_lang') === 'fa' ? 'ترانک آزاد از استخر' : 'free trunk from pool') . '}/' . $num;
            case 'sip':
                return $trunk !== '' ? 'SIP/' . $trunk . '/' . $num : $local;
            case 'pjsip':
                return $trunk !== '' ? 'PJSIP/' . $num . '@' . $trunk : $local;
            case 'custom':
                $tpl = (string)$eff['channel_template'];
                if ($tpl === '') {
                    return $local;
                }
                return Util::oneLine(str_replace(array('{trunk}', '{number}', '{context}'), array($trunk, $num, $eff['outbound_context']), $tpl), 160);
            case 'local':
            default:
                return $local;
        }
    }

    /** Recompute campaign status when its work is done */
    public static function finishIfDone($campaignId)
    {
        $db = Db::get();
        $c = self::find($campaignId);
        if (!$c || !in_array($c['status'], array('running', 'paused'), true)) {
            return false;
        }
        $remaining = (int)$db->val(
            "SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status IN ('pending','dialing','answered')",
            array((int)$campaignId)
        );
        if ($remaining === 0) {
            $db->update('campaigns', array('status' => 'completed', 'finished_at' => Util::now(), 'updated_at' => Util::now()), 'id = ?', array((int)$campaignId));
            self::refreshCounters($campaignId);
            Logger::info("campaign #$campaignId completed");
            return true;
        }
        return false;
    }
}
