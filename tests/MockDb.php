<?php
class MockDb extends Db
{
    public $log = array();
    public function __construct() {}
    private function fake($sql)
    {
        $this->log[] = $sql;
        $s = strtolower($sql);
        if (strpos($s, 'from users where id') !== false || strpos($s, 'from users where username') !== false) {
            return array('id' => 1, 'username' => 'root', 'display_name' => 'Root', 'password_hash' => password_hash('x', 1), 'role' => 'admin', 'auth_source' => 'local', 'is_active' => 1, 'created_at' => '2026-01-01 00:00:00', 'last_login_at' => null, 'last_login_ip' => null);
        }
        if (strpos($s, 'from call_attempts where id') !== false) {
            return array('id' => 9, 'contact_id' => 7, 'campaign_id' => 5, 'attempt_no' => 1, 'action_id' => 'ac-7-1-x', 'channel' => 'Local/x', 'uniqueid' => null, 'started_at' => date('Y-m-d H:i:s'), 'answered_at' => null, 'ended_at' => null, 'result' => 'dialing', 'reason_code' => null, 'hangup_cause' => null, 'duration_sec' => 0, 'dtmf' => null, 'amd_result' => null);
        }
        if (strpos($s, 'from campaign_contacts where id') !== false) {
            return array('id' => 7, 'campaign_id' => 5, 'phone' => '09121234567', 'raw_phone' => '', 'name' => 'Ali', 'audio_id' => null, 'extra' => null, 'status' => 'dialing', 'attempts' => 1, 'next_attempt_at' => null, 'last_attempt_at' => null, 'answered_at' => null, 'dtmf' => null, 'result_tag' => null, 'duration_sec' => 0, 'hangup_cause' => null, 'amd_result' => null, 'uniqueid' => null, 'last_error' => null, 'updated_at' => '');
        }
        if (strpos($s, 'from daemon_status') !== false) {
            return array('id' => 1, 'pid' => 123, 'started_at' => '2026-01-01 00:00:00', 'heartbeat_at' => date('Y-m-d H:i:s'), 'ami_connected' => 1, 'active_calls' => 0, 'version' => '2', 'last_error' => null);
        }
        if (strpos($s, 'from campaigns where id') !== false) {
            return array('id' => 5, 'name' => 'Test', 'kind' => 'campaign', 'description' => 'd', 'status' => 'running', 'audio_id' => 1, 'ivr_config' => '{"digits":{"1":{"action":"transfer","target":"201","context":"from-internal","tag":"yes"}}}',
                'max_repeats' => 1, 'max_retries' => 1, 'retry_delay_min' => 15, 'retry_on' => 'noanswer,busy', 'concurrent' => 2, 'gap_ms' => 1500, 'ring_timeout' => 30, 'start_at' => null, 'end_at' => null,
                'work_start' => null, 'work_end' => null, 'work_days' => null, 'respect_holidays' => 1, 'priority' => 5, 'callerid_name' => null, 'callerid_number' => null, 'dial_prefix' => null, 'channel_tech' => null, 'trunk_name' => null, 'amd' => 0,
                'total_contacts' => 10, 'cnt_done' => 4, 'cnt_answered' => 2, 'last_dial_at' => null, 'started_at' => '2026-01-01 10:00:00', 'finished_at' => null, 'last_error' => 'wait:off_hours', 'created_by' => 1, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01');
        }
        if (strpos($s, 'from audio_files where id') !== false) {
            return array('id' => 1, 'name' => 'promo', 'original_name' => 'p.mp3', 'path' => 'audio/ac_1.wav', 'duration_sec' => 12, 'size_bytes' => 1000, 'created_by' => 1, 'created_at' => '2026-01-01');
        }
        if (strpos($s, 'select count(*) total') !== false || strpos($s, 'count(*) total,') !== false) {
            return array('total' => 3, 'answered' => 2, 'busy' => 0, 'noanswer' => 1, 'failed' => 0, 'machine' => 0, 'dnc' => 0, 'talk' => 60, 'avg_talk' => 30);
        }
        return null;
    }
    public function run($sql, array $params = array()) { $this->fake($sql); return new MockStmt(); }
    public function all($sql, array $params = array()) { $this->fake($sql); if (strpos($sql, 'FROM trunks') !== false) { return array(array('id' => 1, 'name' => 'T1', 'tech' => 'sip', 'channel_id' => '65743452', 'dial_template' => '', 'dial_prefix' => '', 'max_channels' => 1, 'is_enabled' => 1, 'sort' => 0, 'calls_total' => 5, 'calls_failed' => 1, 'last_used_at' => null, 'last_error' => 'congestion')); } return array(); }
    public function one($sql, array $params = array()) { return $this->fake($sql); }
    public function val($sql, array $params = array()) { $this->fake($sql); return strpos(strtolower($sql), 'version()') !== false ? '10.3-MariaDB' : 0; }
    public function exec($sql, array $params = array()) { $this->fake($sql); return 0; }
    public function insert($table, array $data) { return 1; }
    public $updates = array();
    public function update($table, array $data, $where, array $whereParams = array()) { $this->updates[] = array($table, $data); return 1; }
    public function begin() {} public function commit() {} public function rollback() {}
    public function lastId() { return 1; }
    public function tableExists($t) { return true; }
    public function ping() { return true; }
}
class MockStmt { public function fetchAll() { return array(); } public function fetch() { return false; } public function fetchColumn() { return false; } public function rowCount() { return 0; } }

