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

    /** why the last pickFree() returned null: none | busy | cooldown | down */
    public static $lastReason = 'none';

    public static function isCoolingDown(array $t)
    {
        return !empty($t['cooldown_until']) && strtotime($t['cooldown_until']) > time();
    }

    /** registration / peer state says the trunk cannot carry calls right now */
    public static function isDown(array $t)
    {
        if (Settings::get('trunk_block_down', '1') !== '1') {
            return false;
        }
        return isset($t['reg_state']) && in_array($t['reg_state'], array('unreachable', 'reg_failed'), true);
    }

    /** enabled, not cooling down, not known-down */
    public static function isUsable(array $t)
    {
        return (int)$t['is_enabled'] === 1 && !self::isCoolingDown($t) && !self::isDown($t);
    }

    /**
     * Pick a trunk with a free channel. Least loaded first (relative to capacity), then least recently used.
     * Trunks in cooldown (consecutive failures) or reported down by Asterisk are skipped.
     * @return array|null trunk row
     */
    public static function pickFree()
    {
        $trunks = self::all(true);
        self::$lastReason = 'none';
        if (!$trunks) {
            return null;
        }
        $active = self::activeCounts();
        $best = null;
        $bestScore = null;
        $busy = 0;
        $cool = 0;
        $down = 0;
        foreach ($trunks as $t) {
            if (self::isCoolingDown($t)) {
                $cool++;
                continue;
            }
            if (self::isDown($t)) {
                $down++;
                continue;
            }
            $used = isset($active[(int)$t['id']]) ? $active[(int)$t['id']] : 0;
            $max = max(1, (int)$t['max_channels']);
            if ($used >= $max) {
                $busy++;
                continue;
            }
            $score = $used / $max; // load ratio
            $lu = $t['last_used_at'] ? strtotime($t['last_used_at']) : 0;
            if ($best === null || $score < $bestScore || ($score == $bestScore && $lu < strtotime($best['last_used_at'] ? $best['last_used_at'] : '1970-01-01'))) {
                $best = $t;
                $bestScore = $score;
            }
        }
        if ($best === null) {
            self::$lastReason = $busy ? 'busy' : ($cool ? 'cooldown' : ($down ? 'down' : 'none'));
        }
        return $best;
    }

    /** usable capacity (channels of trunks that can carry calls right now) */
    public static function usableCapacity()
    {
        $n = 0;
        foreach (self::all(true) as $t) {
            if (self::isUsable($t)) {
                $n += (int)$t['max_channels'];
            }
        }
        return $n;
    }

    /**
     * Outcome of a call on this trunk. Provider-side failures (failed / congestion) count towards the
     * consecutive-failure streak; anything the far end answered or rejected itself (answered, busy,
     * no answer, invalid number) proves the trunk works and resets it.
     */
    public static function recordResult($trunkId, $status, $detail = '')
    {
        $db = Db::get();
        $t = self::find($trunkId);
        if (!$t) {
            return;
        }
        $now = Util::now();
        $failed = in_array($status, array(CallStatus::FAILED, CallStatus::CONGESTION), true);
        if (!$failed) {
            $upd = array('calls_total' => $t['calls_total'] + 1, 'fail_streak' => 0, 'last_ok_at' => $now);
            if ($status !== CallStatus::INVALID) {
                $upd['cooldown_count'] = 0;
            }
            if (self::isCoolingDown($t)) {
                $upd['cooldown_until'] = null;
            }
            $db->update('trunks', $upd, 'id = ?', array((int)$t['id']));
            return;
        }
        $streak = (int)$t['fail_streak'] + 1;
        $upd = array('calls_total' => $t['calls_total'] + 1, 'calls_failed' => $t['calls_failed'] + 1, 'fail_streak' => $streak, 'last_error' => Util::oneLine($detail !== '' ? $detail : $status, 120));
        $threshold = max(1, (int)Settings::get('trunk_fail_streak', 3));
        if ($streak >= $threshold && !self::isCoolingDown($t)) {
            $count = (int)$t['cooldown_count'] + 1;
            $base = max(1, (int)Settings::get('trunk_cooldown_min', 5));
            $cap = max($base, (int)Settings::get('trunk_cooldown_max_min', 60));
            $minutes = min($cap, $base * pow(2, min(10, $count - 1)));
            $upd['cooldown_until'] = date('Y-m-d H:i:s', time() + 60 * $minutes);
            $upd['cooldown_count'] = $count;
            $upd['fail_streak'] = 0;
            Logger::warn("trunk {$t['name']} ({$t['channel_id']}): $streak consecutive failures ($detail) -> out of the pool for $minutes min");
        }
        $db->update('trunks', $upd, 'id = ?', array((int)$t['id']));
    }

    /** badge for the UI: array(cls, label) */
    public static function healthOf(array $t)
    {
        if (!(int)$t['is_enabled']) {
            return array('cls' => 'muted', 'label' => t('disabled'));
        }
        if (self::isCoolingDown($t)) {
            $min = max(1, (int)ceil((strtotime($t['cooldown_until']) - time()) / 60));
            return array('cls' => 'paused', 'label' => t('trunk_cooling', $min));
        }
        switch (isset($t['reg_state']) ? $t['reg_state'] : '') {
            case 'ok':
                return array('cls' => 'completed', 'label' => t('trunk_ok'));
            case 'unreachable':
                return array('cls' => 'failed', 'label' => t('trunk_unreachable'));
            case 'reg_failed':
                return array('cls' => 'failed', 'label' => t('trunk_reg_failed'));
            case 'missing':
                return array('cls' => 'failed', 'label' => t('trunk_missing'));
            case 'unmonitored':
                return array('cls' => 'muted', 'label' => t('trunk_unmonitored'));
            default:
                return array('cls' => 'muted', 'label' => '—');
        }
    }

    /** enabled trunks that are out of service right now (for alerts) */
    public static function alerts()
    {
        $out = array();
        foreach (self::all(true) as $t) {
            if (self::isCoolingDown($t) || in_array($t['reg_state'], array('unreachable', 'reg_failed', 'missing'), true)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    /** manual: put the trunk back in the pool now */
    public static function clearCooldown($id)
    {
        Db::get()->update('trunks', array('cooldown_until' => null, 'fail_streak' => 0, 'cooldown_count' => 0), 'id = ?', array((int)$id));
    }

    /**
     * Ask Asterisk about every SIP/PJSIP trunk of the pool (peer qualify status + registration).
     * Sets reg_state: ok | unreachable | reg_failed | unmonitored | missing | '' (unknown/custom)
     * @return int number of trunks whose state changed
     */
    public static function probe(Ami $ami)
    {
        $trunks = self::all(false);
        if (!$trunks) {
            return 0;
        }
        $sipReg = null;
        $pjReg = null;
        $pjContacts = null;
        $changed = 0;
        $now = Util::now();
        foreach ($trunks as $t) {
            $state = '';
            $detail = '';
            try {
                if ($t['tech'] === 'sip') {
                    if ($sipReg === null) {
                        $sipReg = self::parseSipRegistry($ami->command('sip show registry'));
                    }
                    $raw = $ami->command('sip show peer ' . preg_replace('/[^A-Za-z0-9_\-\.]/', '', $t['channel_id']));
                    if (trim($raw) === '' || stripos($raw, 'No such command') !== false) {
                        continue; // AMI hiccup or chan_sip not loaded: keep the previous state
                    }
                    $peer = self::parseSipPeer($raw);
                    list($state, $detail) = self::judge($peer, self::matchRegistry($sipReg, $peer, $t));
                } elseif ($t['tech'] === 'pjsip') {
                    if ($pjReg === null) {
                        $pjReg = self::parsePjsipRegistrations($ami->command('pjsip show registrations'));
                        $pjContacts = self::parsePjsipContacts($ami->command('pjsip show contacts'));
                    }
                    $id = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $t['channel_id']);
                    $peer = array('status' => isset($pjContacts[$id]) ? $pjContacts[$id] : 'Unmonitored', 'username' => '', 'host' => '', 'ip' => '', 'found' => isset($pjContacts[$id]));
                    $reg = null;
                    foreach ($pjReg as $name => $st) {
                        if ($name === $id || strpos($name, $id) === 0) {
                            $reg = $st;
                            break;
                        }
                    }
                    list($state, $detail) = self::judge($peer, $reg);
                } else {
                    continue; // custom dial strings: nothing to ask
                }
            } catch (Exception $e) {
                Logger::warn('trunk probe ' . $t['channel_id'] . ': ' . $e->getMessage());
                continue;
            }
            $upd = array('reg_checked_at' => $now, 'reg_state' => $state, 'reg_detail' => Util::oneLine($detail, 160));
            if ($state !== $t['reg_state']) {
                $changed++;
                if (in_array($state, array('unreachable', 'reg_failed', 'missing'), true)) {
                    Logger::warn("trunk {$t['name']} ({$t['channel_id']}) is $state: $detail");
                } elseif ($t['reg_state'] !== '') {
                    Logger::info("trunk {$t['name']} ({$t['channel_id']}) is $state ($detail)");
                }
            }
            Db::get()->update('trunks', $upd, 'id = ?', array((int)$t['id']));
        }
        return $changed;
    }

    /** @return array(state, detail) */
    private static function judge(array $peer, $reg)
    {
        $st = strtoupper((string)$peer['status']);
        $parts = array();
        if ($peer['status'] !== '') {
            $parts[] = $peer['status'];
        }
        if ($reg !== null) {
            $parts[] = $reg;
        }
        $detail = implode(' · ', $parts);
        if (!$peer['found']) {
            return array('missing', $detail !== '' ? $detail : 'peer not found');
        }
        if ($reg !== null && stripos($reg, 'Registered') !== 0) {
            return array('reg_failed', $detail);
        }
        if (strpos($st, 'UNREACHABLE') === 0) {
            return array('unreachable', $detail);
        }
        if (strpos($st, 'OK') === 0 || strpos($st, 'LAGGED') === 0 || strpos($st, 'AVAIL') === 0) {
            return array('ok', $detail);
        }
        if ($reg !== null) {
            return array('ok', $detail); // registered, peer not qualified
        }
        return array('unmonitored', $detail !== '' ? $detail : 'no qualify, no registration');
    }

    /** sip show peer <name> -> status / username / host */
    public static function parseSipPeer($out)
    {
        $p = array('status' => '', 'username' => '', 'host' => '', 'ip' => '', 'found' => false);
        if (preg_match('/Peer\s+\S+\s+not found/i', $out) || trim($out) === '') {
            return $p;
        }
        $p['found'] = true;
        if (preg_match('/^\s*Status\s*:\s*(.+)$/mi', $out, $m)) {
            $p['status'] = trim($m[1]);
        }
        if (preg_match('/^\s*Username\s*:\s*(\S*)/mi', $out, $m)) {
            $p['username'] = $m[1];
        }
        if (preg_match('/^\s*ToHost\s*:\s*(\S*)/mi', $out, $m)) {
            $p['host'] = strtolower($m[1]);
        }
        if (preg_match('/^\s*Addr->IP\s*:\s*(\S+)/mi', $out, $m)) {
            $p['ip'] = preg_replace('/:\d+$/', '', $m[1]);
        }
        return $p;
    }

    /** sip show registry -> list of array(host, username, state) */
    public static function parseSipRegistry($out)
    {
        $rows = array();
        // Host  [dnsmgr]  Username  Refresh  State  Reg.Time   (Refresh is right-aligned: single space before State)
        $re = '/^\\s*(\\S+)\\s+(?:([YN])\\s+)?(\\S+)\\s+(\\d+)\\s+(Registered|Unregistered|Request Sent|Auth\\. Sent|Rejected|Timeout|No Authentication|Failed)\\b/i';
        foreach (preg_split('/\\r?\\n/', $out) as $line) {
            if (preg_match($re, $line, $m)) {
                $rows[] = array('host' => strtolower(preg_replace('/:\\d+$/', '', $m[1])), 'username' => $m[3], 'state' => $m[5]);
            }
        }
        return $rows;
    }

    /** registry row for this peer: username match first, then host/ip match */
    private static function matchRegistry(array $reg, array $peer, array $t)
    {
        if (!$reg) {
            return null;
        }
        $cands = array_filter(array($peer['username'], $t['callerid'], $t['channel_id']), 'strlen');
        foreach ($reg as $r) {
            if (in_array($r['username'], $cands, true)) {
                return $r['state'];
            }
        }
        foreach ($reg as $r) {
            if ($r['host'] !== '' && ($r['host'] === $peer['host'] || $r['host'] === $peer['ip'])) {
                return $r['state'];
            }
        }
        return null;
    }

    /** pjsip show registrations -> name => state */
    public static function parsePjsipRegistrations($out)
    {
        $rows = array();
        foreach (preg_split('/\r?\n/', $out) as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_\-\.]+)\/sip:\S+\s+\S*\s+(Registered|Unregistered|Rejected|Request Sent|Auth[^\s]*)/i', $line, $m)) {
                $rows[$m[1]] = $m[2];
            }
        }
        return $rows;
    }

    /** pjsip show contacts -> endpoint => Avail|Unavail|NonQual|Unknown */
    public static function parsePjsipContacts($out)
    {
        $rows = array();
        foreach (preg_split('/\r?\n/', $out) as $line) {
            if (preg_match('/^\s*Contact:\s+([A-Za-z0-9_\-\.]+)\/\S+\s+\S+\s+(Avail|Unavail|NonQual|Unknown|Created|Removed)/i', $line, $m)) {
                $rows[$m[1]] = $m[2];
            }
        }
        return $rows;
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
