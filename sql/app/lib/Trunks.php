<?php
/**
 * Trunk pool: the app itself decides which trunk each call goes out on and enforces
 * a per-trunk channel limit. Calls are placed directly on the trunk (SIP/<trunk>/<number>),
 * so Issabel's outbound routes - and their automatic trunk failover / re-dial - are bypassed.
 */
class Trunks
{
    public static function all($enabledOnly = false)
    {
        return Db::get()->all('SELECT * FROM trunks ' . ($enabledOnly ? 'WHERE is_enabled = 1 ' : '') . 'ORDER BY sort, id');
    }

    public static function find($id)
    {
        return Db::get()->one('SELECT * FROM trunks WHERE id = ?', array((int)$id));
    }

    /** active (in-flight) attempts per trunk id */
    public static function activeCounts()
    {
        $out = array();
        foreach (Db::get()->all("SELECT trunk_id, COUNT(*) n FROM call_attempts WHERE trunk_id IS NOT NULL AND result IN ('dialing','answered') GROUP BY trunk_id") as $r) {
            $out[(int)$r['trunk_id']] = (int)$r['n'];
        }
        return $out;
    }

    /** total channels of all enabled trunks */
    public static function capacity()
    {
        return (int)Db::get()->val('SELECT COALESCE(SUM(max_channels),0) FROM trunks WHERE is_enabled = 1');
    }

    /**
     * Pick a trunk with a free channel. Least loaded first (relative to capacity), then least recently used.
     * @return array|null trunk row
     */
    public static function pickFree()
    {
        $trunks = self::all(true);
        if (!$trunks) {
            return null;
        }
        $active = self::activeCounts();
        $best = null;
        $bestScore = null;
        foreach ($trunks as $t) {
            $used = isset($active[(int)$t['id']]) ? $active[(int)$t['id']] : 0;
            $max = max(1, (int)$t['max_channels']);
            if ($used >= $max) {
                continue;
            }
            $score = $used / $max; // load ratio
            $lu = $t['last_used_at'] ? strtotime($t['last_used_at']) : 0;
            if ($best === null || $score < $bestScore || ($score == $bestScore && $lu < strtotime($best['last_used_at'] ? $best['last_used_at'] : '1970-01-01'))) {
                $best = $t;
                $bestScore = $score;
            }
        }
        return $best;
    }

    /** Channel string for a number on this trunk */
    public static function channelFor(array $t, $phone)
    {
        $num = Util::dialSafe($t['dial_prefix'] . $phone);
        $id = preg_replace('/[^A-Za-z0-9_\-\.@]/', '', (string)$t['channel_id']);
        switch ($t['tech']) {
            case 'pjsip':
                return 'PJSIP/' . $num . '@' . $id;
            case 'custom':
                $tpl = (string)$t['dial_template'];
                if ($tpl === '') {
                    return 'SIP/' . $id . '/' . $num;
                }
                return Util::oneLine(str_replace(array('{trunk}', '{number}'), array($id, $num), $tpl), 160);
            case 'sip':
            default:
                return 'SIP/' . $id . '/' . $num;
        }
    }

    /** "Name" <123> / 123 / <123>  ->  123 */
    public static function cidNumber($cid)
    {
        $cid = trim((string)$cid);
        if (preg_match('/<([^>]*)>/', $cid, $m)) {
            $cid = $m[1];
        }
        return Util::dialSafe($cid);
    }

    /** username / fromuser / defaultuser of a SIP or PJSIP trunk in the Issabel asterisk db */
    private static function peerUsername(PDO $pdo, $tech, $chan)
    {
        try {
            if ($tech === 'sip') {
                // sip table rows of a trunk have id = tr-peer-<trunkid>
                $st = $pdo->prepare("SELECT s.keyword, s.data FROM sip s JOIN trunks t ON s.id = CONCAT('tr-peer-', t.trunkid) WHERE t.channelid = ? AND s.keyword IN ('fromuser','username','defaultuser') ORDER BY FIELD(s.keyword,'fromuser','username','defaultuser')");
                $st->execute(array('SIP/' . $chan));
            } else {
                $st = $pdo->prepare("SELECT p.keyword, p.data FROM pjsip p JOIN trunks t ON p.id = t.trunkid WHERE t.channelid = ? AND p.keyword IN ('from_user','username') ORDER BY FIELD(p.keyword,'from_user','username')");
                $st->execute(array('PJSIP/' . $chan));
            }
            $row = $st->fetch();
            return $row ? Util::dialSafe($row['data']) : '';
        } catch (Exception $e) {
            return '';
        }
    }

    public static function markUsed($id, $failed = false)
    {
        Db::get()->run('UPDATE trunks SET last_used_at = NOW(), calls_total = calls_total + 1' . ($failed ? ', calls_failed = calls_failed + 1' : '') . ' WHERE id = ?', array((int)$id));
    }

    /** Trunks defined in the Issabel/Elastix PBX (for the "add from Issabel" helper) */
    public static function fromIssabel()
    {
        $root = Auth::pbxRootPassword();
        if ($root === null) {
            return array();
        }
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=asterisk;charset=utf8', 'root', $root, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
            $rows = $pdo->query('SELECT trunkid, name, tech, channelid, outcid, disabled FROM trunks ORDER BY trunkid')->fetchAll();
            $out = array();
            foreach ($rows as $r) {
                $tech = strtolower($r['tech']);
                if (!in_array($tech, array('sip', 'pjsip', 'custom', 'iax', 'dahdi'), true)) {
                    continue;
                }
                $chan = preg_replace('#^[A-Za-z]+/#', '', $r['channelid']);
                $cid = self::cidNumber(isset($r['outcid']) ? $r['outcid'] : '');
                // no Outbound CID on the trunk: most providers expect the account/username as From user
                if ($cid === '' && ($tech === 'sip' || $tech === 'pjsip')) {
                    $cid = self::peerUsername($pdo, $tech, $chan);
                }
                $out[] = array('name' => $r['name'], 'tech' => $tech === 'pjsip' ? 'pjsip' : ($tech === 'sip' ? 'sip' : 'custom'), 'channel_id' => $chan, 'raw' => $r['channelid'], 'callerid' => $cid, 'disabled' => $r['disabled'] === 'on');
            }
            return $out;
        } catch (Exception $e) {
            return array();
        }
    }
}
