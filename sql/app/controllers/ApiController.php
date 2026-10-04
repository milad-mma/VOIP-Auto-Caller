<?php
/**
 * REST API v1. Authentication: header  X-API-Key: ack_...   (or ?api_key=)
 * All responses are JSON: {"ok":true,...} or {"ok":false,"error":"code","message":"..."}
 */
class ApiController extends Controller
{
    private $key;

    public function handle($p)
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $path = '/' . trim($p['path'], '/');
        $method = Request::method();
        if ($path === '/ping') {
            $this->json(array('ok' => true, 'version' => AC_VERSION, 'time' => Util::now()));
        }
        $key = Request::header('X-Api-Key');
        if (!$key) {
            $key = Request::get('api_key', '');
        }
        $this->key = Auth::apiUser($key);
        if (!$this->key) {
            $this->json(array('ok' => false, 'error' => 'unauthorized', 'message' => 'invalid or missing API key'), 401);
        }
        if ($method !== 'GET' && $this->key['role'] === 'viewer') {
            $this->json(array('ok' => false, 'error' => 'forbidden', 'message' => 'read-only key'), 403);
        }
        $body = $method === 'GET' ? $_GET : array_merge($_POST, Request::jsonBody());
        try {
            $this->route($method, $path, $body);
        } catch (RuntimeException $e) {
            $this->json(array('ok' => false, 'error' => 'bad_request', 'message' => $e->getMessage()), 400);
        }
        $this->json(array('ok' => false, 'error' => 'not_found', 'message' => 'unknown endpoint ' . $method . ' ' . $path), 404);
    }

    private function route($m, $path, array $b)
    {
        if ($path === '/status' && $m === 'GET') {
            $d = $this->db->one('SELECT * FROM daemon_status WHERE id = 1');
            $age = $d && $d['heartbeat_at'] ? time() - strtotime($d['heartbeat_at']) : null;
            $this->json(array('ok' => true, 'dialer_alive' => $age !== null && $age < 30, 'ami_connected' => $d ? (int)$d['ami_connected'] === 1 : false,
                'active_calls' => $d ? (int)$d['active_calls'] : 0, 'running_campaigns' => (int)$this->db->val("SELECT COUNT(*) FROM campaigns WHERE status='running'"), 'last_error' => $d ? $d['last_error'] : null));
        }
        if ($path === '/audio' && $m === 'GET') {
            $this->json(array('ok' => true, 'audio' => $this->db->all('SELECT id, name, duration_sec, created_at FROM audio_files ORDER BY name')));
        }
        if ($path === '/calls' && $m === 'POST') {
            $phone = Util::normalizePhone(isset($b['phone']) ? $b['phone'] : '', Settings::get('country_code', '98'));
            if ($phone === '') {
                throw new RuntimeException('phone is required');
            }
            $audioId = $this->audioId(isset($b['audio']) ? $b['audio'] : (isset($b['audio_id']) ? $b['audio_id'] : ''));
            if (Dnc::has($phone) && empty($b['ignore_dnc'])) {
                $this->json(array('ok' => false, 'error' => 'dnc', 'message' => 'number is on the do-not-call list'), 409);
            }
            $id = QuickCallController::create($phone, $audioId, Util::dialSafe(isset($b['transfer']) ? $b['transfer'] : ''), (int)$this->key['user_id'], isset($b['name']) ? Util::oneLine($b['name'], 128) : '');
            Audit::log('api.call', 'campaign', $id, $phone);
            $this->json(array('ok' => true, 'call_id' => $id, 'phone' => $phone), 201);
        }
        if (preg_match('#^/calls/(\d+)$#', $path, $mm) && $m === 'GET') {
            $c = $this->db->one("SELECT * FROM campaigns WHERE id = ? AND kind = 'quick'", array((int)$mm[1]));
            if (!$c) {
                $this->json(array('ok' => false, 'error' => 'not_found'), 404);
            }
            $ct = $this->db->one('SELECT phone, name, status, attempts, answered_at, duration_sec, dtmf, result_tag, hangup_cause, amd_result, last_attempt_at FROM campaign_contacts WHERE campaign_id = ?', array((int)$c['id']));
            $this->json(array('ok' => true, 'call_id' => (int)$c['id'], 'campaign_status' => $c['status'], 'call' => $ct));
        }
        if ($path === '/campaigns' && $m === 'GET') {
            $st = isset($b['status']) ? (string)$b['status'] : '';
            $w = "kind = 'campaign'";
            $params = array();
            if ($st !== '') {
                $w .= ' AND status = ?';
                $params[] = $st;
            }
            $rows = $this->db->all("SELECT id, name, status, total_contacts, cnt_done, cnt_answered, start_at, end_at, started_at, finished_at, created_at FROM campaigns WHERE $w ORDER BY id DESC LIMIT 200", $params);
            $this->json(array('ok' => true, 'campaigns' => $rows));
        }
        if ($path === '/campaigns' && $m === 'POST') {
            $this->createCampaign($b);
        }
        if (preg_match('#^/campaigns/(\d+)$#', $path, $mm) && $m === 'GET') {
            $c = Campaign::find($mm[1]);
            if (!$c) {
                $this->json(array('ok' => false, 'error' => 'not_found'), 404);
            }
            $c['ivr'] = Campaign::parseIvr($c['ivr_config']);
            unset($c['ivr_config']);
            $this->json(array('ok' => true, 'campaign' => $c, 'stats' => Campaign::stats($c['id'])));
        }
        if (preg_match('#^/campaigns/(\d+)/contacts$#', $path, $mm)) {
            $c = Campaign::find($mm[1]);
            if (!$c) {
                $this->json(array('ok' => false, 'error' => 'not_found'), 404);
            }
            if ($m === 'GET') {
                $page = Util::intOr(isset($b['page']) ? $b['page'] : 1, 1, 1);
                $per = Util::intOr(isset($b['per']) ? $b['per'] : 100, 100, 1, 1000);
                $w = 'campaign_id = ?';
                $params = array((int)$c['id']);
                if (!empty($b['status']) && in_array($b['status'], CallStatus::all(), true)) {
                    $w .= ' AND status = ?';
                    $params[] = $b['status'];
                }
                if (!empty($b['updated_since'])) {
                    $w .= ' AND updated_at >= ?';
                    $params[] = date('Y-m-d H:i:s', strtotime($b['updated_since']));
                }
                $total = (int)$this->db->val("SELECT COUNT(*) FROM campaign_contacts WHERE $w", $params);
                $rows = $this->db->all("SELECT id, phone, raw_phone, name, status, attempts, next_attempt_at, last_attempt_at, answered_at, duration_sec, dtmf, result_tag, hangup_cause, amd_result, extra, updated_at FROM campaign_contacts WHERE $w ORDER BY id LIMIT " . (($page - 1) * $per) . ',' . $per, $params);
                foreach ($rows as &$r) {
                    $r['extra'] = $r['extra'] ? json_decode($r['extra'], true) : null;
                }
                unset($r);
                $this->json(array('ok' => true, 'total' => $total, 'page' => $page, 'per' => $per, 'contacts' => $rows));
            }
            if ($m === 'POST') {
                $stats = $this->addContacts($c, $b);
                $this->json(array('ok' => true, 'imported' => $stats));
            }
        }
        if (preg_match('#^/campaigns/(\d+)/(start|pause|resume|stop)$#', $path, $mm) && $m === 'POST') {
            $c = Campaign::find($mm[1]);
            if (!$c) {
                $this->json(array('ok' => false, 'error' => 'not_found'), 404);
            }
            $this->json(array('ok' => true, 'status' => $this->transition($c, $mm[2])));
        }
        if ($path === '/dnc') {
            if ($m === 'GET') {
                $phone = Util::normalizePhone(isset($b['phone']) ? $b['phone'] : '');
                if ($phone !== '') {
                    $this->json(array('ok' => true, 'phone' => $phone, 'listed' => Dnc::has($phone)));
                }
                $this->json(array('ok' => true, 'count' => (int)$this->db->val('SELECT COUNT(*) FROM dnc'), 'dnc' => $this->db->all('SELECT phone, reason, source, created_at FROM dnc ORDER BY id DESC LIMIT 1000')));
            }
            if ($m === 'POST') {
                $phones = isset($b['phones']) && is_array($b['phones']) ? $b['phones'] : (isset($b['phone']) ? array($b['phone']) : array());
                $n = 0;
                foreach ($phones as $ph) {
                    if (Dnc::add($ph, isset($b['reason']) ? $b['reason'] : 'api', 'api', (int)$this->key['user_id'])) {
                        $n++;
                    }
                }
                $this->json(array('ok' => true, 'added' => $n));
            }
            if ($m === 'DELETE') {
                $n = Dnc::remove(isset($b['phone']) ? $b['phone'] : '');
                $this->json(array('ok' => true, 'removed' => $n));
            }
        }
        if (preg_match('#^/dnc/(.+)$#', $path, $mm) && $m === 'DELETE') {
            $this->json(array('ok' => true, 'removed' => Dnc::remove($mm[1])));
        }
    }

    private function audioId($ref)
    {
        $ref = trim((string)$ref);
        if ($ref === '') {
            throw new RuntimeException('audio is required (id or name)');
        }
        $id = ctype_digit($ref) ? $this->db->val('SELECT id FROM audio_files WHERE id = ?', array((int)$ref)) : null;
        if (!$id) {
            $id = $this->db->val('SELECT id FROM audio_files WHERE name = ?', array(preg_replace('/\.(wav|mp3)$/i', '', $ref)));
        }
        if (!$id) {
            throw new RuntimeException('audio not found: ' . $ref);
        }
        return (int)$id;
    }

    private function createCampaign(array $b)
    {
        $name = isset($b['name']) ? Util::oneLine($b['name'], 128) : '';
        if ($name === '') {
            throw new RuntimeException('name is required');
        }
        $audioId = isset($b['audio']) || isset($b['audio_id']) ? $this->audioId(isset($b['audio']) ? $b['audio'] : $b['audio_id']) : null;
        $now = Util::now();
        $d = array(
            'name' => $name, 'kind' => 'campaign', 'status' => 'draft', 'description' => isset($b['description']) ? Util::oneLine($b['description'], 2000) : 'created via API',
            'audio_id' => $audioId,
            'max_repeats' => Util::intOr(isset($b['max_repeats']) ? $b['max_repeats'] : 1, 1, 0, 5),
            'max_retries' => Util::intOr(isset($b['max_retries']) ? $b['max_retries'] : Settings::get('default_max_retries'), 1, 0, 10),
            'retry_delay_min' => Util::intOr(isset($b['retry_delay_min']) ? $b['retry_delay_min'] : Settings::get('default_retry_delay_min'), 15, 1, 1440),
            'retry_on' => isset($b['retry_on']) ? implode(',', array_intersect(is_array($b['retry_on']) ? $b['retry_on'] : explode(',', $b['retry_on']), CallStatus::retryable())) : 'noanswer,busy,congestion,failed',
            'concurrent' => Util::intOr(isset($b['concurrent']) ? $b['concurrent'] : Settings::get('default_concurrent'), 2, 1, 200),
            'gap_ms' => Util::intOr(isset($b['gap_ms']) ? $b['gap_ms'] : Settings::get('default_gap_ms'), 1500, 0, 600000),
            'ring_timeout' => Util::intOr(isset($b['ring_timeout']) ? $b['ring_timeout'] : Settings::get('default_ring_timeout'), 30, 5, 120),
            'priority' => Util::intOr(isset($b['priority']) ? $b['priority'] : 5, 5, 1, 10),
            'amd' => Util::intOr(isset($b['amd']) ? $b['amd'] : 0, 0, 0, 1),
            'respect_holidays' => Util::intOr(isset($b['respect_holidays']) ? $b['respect_holidays'] : 1, 1, 0, 1),
            'start_at' => !empty($b['start_at']) ? date('Y-m-d H:i:00', strtotime($b['start_at'])) : null,
            'end_at' => !empty($b['end_at']) ? date('Y-m-d H:i:00', strtotime($b['end_at'])) : null,
            'work_start' => !empty($b['work_start']) && Util::isHm($b['work_start']) ? $b['work_start'] : null,
            'work_end' => !empty($b['work_end']) && Util::isHm($b['work_end']) ? $b['work_end'] : null,
            'work_days' => !empty($b['work_days']) ? implode(',', array_filter(array_map('intval', is_array($b['work_days']) ? $b['work_days'] : explode(',', $b['work_days'])), function ($x) {
                return $x >= 0 && $x <= 6;
            })) : null,
            'callerid_name' => isset($b['callerid_name']) ? Util::oneLine($b['callerid_name'], 64) : null,
            'callerid_number' => isset($b['callerid_number']) ? Util::dialSafe($b['callerid_number']) : null,
            'dial_prefix' => isset($b['dial_prefix']) ? Util::dialSafe($b['dial_prefix']) : null,
            'trunk_name' => isset($b['trunk_name']) ? Util::oneLine($b['trunk_name'], 64) : null,
            'channel_tech' => isset($b['channel_tech']) && in_array($b['channel_tech'], array('pool', 'local', 'sip', 'pjsip', 'custom'), true) ? $b['channel_tech'] : null,
            'ivr_config' => Util::json(Campaign::cleanIvr(isset($b['ivr']) && is_array($b['ivr']) ? $b['ivr'] : array())),
            'created_by' => (int)$this->key['user_id'], 'created_at' => $now, 'updated_at' => $now,
        );
        if ($d['work_days'] === '') {
            $d['work_days'] = null;
        }
        $id = $this->db->insert('campaigns', $d);
        $c = Campaign::find($id);
        $stats = null;
        if (!empty($b['contacts'])) {
            $stats = $this->addContacts($c, $b);
        }
        $status = 'draft';
        if (!empty($b['start'])) {
            $status = $this->transition(Campaign::find($id), 'start');
        }
        Audit::log('api.campaign.create', 'campaign', $id, $name);
        $this->json(array('ok' => true, 'campaign_id' => $id, 'status' => $status, 'imported' => $stats), 201);
    }

    private function addContacts(array $c, array $b)
    {
        $list = isset($b['contacts']) && is_array($b['contacts']) ? $b['contacts'] : array();
        if (!$list) {
            throw new RuntimeException('contacts array is required, e.g. [{"phone":"0912...","name":"...","audio":"promo"}]');
        }
        $rows = array();
        foreach ($list as $item) {
            if (is_array($item)) {
                $rows[] = array(isset($item['phone']) ? $item['phone'] : '', isset($item['name']) ? $item['name'] : '', isset($item['audio']) ? $item['audio'] : '');
            } else {
                $rows[] = array((string)$item, '', '');
            }
        }
        if (count($rows) > 50000) {
            throw new RuntimeException('max 50000 contacts per request');
        }
        return Importer::importContacts($c['id'], $rows, array('phone' => 0, 'name' => 1, 'audio' => 2, 'has_header' => false), array('skip_dnc' => empty($b['ignore_dnc']), 'dedupe' => !isset($b['dedupe']) || $b['dedupe']));
    }

    private function transition(array $c, $action)
    {
        $now = Util::now();
        $id = (int)$c['id'];
        switch ($action) {
            case 'start':
                if (!$c['audio_id'] && !$this->db->val('SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND audio_id IS NOT NULL', array($id))) {
                    throw new RuntimeException('campaign has no audio');
                }
                if (!$this->db->val("SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'pending'", array($id))) {
                    throw new RuntimeException('campaign has no pending contacts');
                }
                $st = ($c['start_at'] && strtotime($c['start_at']) > time()) ? 'scheduled' : 'running';
                $this->db->update('campaigns', array('status' => $st, 'started_at' => $c['started_at'] ? $c['started_at'] : $now, 'finished_at' => null, 'last_error' => null, 'updated_at' => $now), 'id = ?', array($id));
                return $st;
            case 'pause':
                if (in_array($c['status'], array('running', 'scheduled'), true)) {
                    $this->db->update('campaigns', array('status' => 'paused', 'updated_at' => $now), 'id = ?', array($id));
                    return 'paused';
                }
                return $c['status'];
            case 'resume':
                if ($c['status'] === 'paused') {
                    $this->db->update('campaigns', array('status' => 'running', 'updated_at' => $now), 'id = ?', array($id));
                    return 'running';
                }
                return $c['status'];
            case 'stop':
                if (in_array($c['status'], array('running', 'paused', 'scheduled'), true)) {
                    $this->db->update('campaigns', array('status' => 'stopped', 'finished_at' => $now, 'updated_at' => $now), 'id = ?', array($id));
                    $this->db->exec("UPDATE campaign_contacts SET status = 'cancelled', updated_at = ? WHERE campaign_id = ? AND status = 'pending'", array($now, $id));
                    Campaign::refreshCounters($id);
                    return 'stopped';
                }
                return $c['status'];
        }
        return $c['status'];
    }
}
