<?php
class CampaignController extends Controller
{
    private function load($id, $withQuick = true)
    {
        $c = Campaign::find($id);
        if (!$c) {
            $this->notFound();
        }
        return $c;
    }

    public function index()
    {
        Auth::requireLogin();
        $status = Request::str('status', '', 20);
        $q = Request::str('q', '', 64);
        $kind = Request::str('kind', 'campaign', 10) === 'quick' ? 'quick' : 'campaign';
        $where = array('kind = ?');
        $params = array($kind);
        if ($status !== '' && in_array($status, array('draft', 'scheduled', 'running', 'paused', 'completed', 'stopped'), true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = 'name LIKE ?';
            $params[] = '%' . $q . '%';
        }
        $page = Request::int('page', 1, 1);
        $per = 25;
        $total = (int)$this->db->val('SELECT COUNT(*) FROM campaigns WHERE ' . implode(' AND ', $where), $params);
        $rows = $this->db->all('SELECT c.*, u.username creator FROM campaigns c LEFT JOIN users u ON u.id = c.created_by WHERE ' . implode(' AND ', $where) . ' ORDER BY c.id DESC LIMIT ' . (($page - 1) * $per) . ',' . $per, $params);
        $this->view('campaigns/index', array('rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'status' => $status, 'q' => $q, 'kind' => $kind));
    }

    public function create()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $c = $this->defaults();
        $this->view('campaigns/form', array('c' => $c, 'audio' => $this->audioList(), 'ivr' => Campaign::defaultIvr(), 'isNew' => true));
    }

    private function defaults()
    {
        return array(
            'id' => 0, 'name' => '', 'description' => '', 'status' => 'draft', 'audio_id' => null,
            'max_repeats' => 1, 'max_retries' => (int)Settings::get('default_max_retries', 1),
            'retry_delay_min' => (int)Settings::get('default_retry_delay_min', 15), 'retry_on' => 'noanswer,busy,congestion,failed',
            'concurrent' => (int)Settings::get('default_concurrent', 2), 'gap_ms' => (int)Settings::get('default_gap_ms', 1500),
            'ring_timeout' => (int)Settings::get('default_ring_timeout', 30),
            'start_at' => null, 'end_at' => null, 'work_start' => null, 'work_end' => null, 'work_days' => null, 'respect_holidays' => 1,
            'priority' => 5, 'callerid_name' => null, 'callerid_number' => null, 'dial_prefix' => null, 'channel_tech' => null, 'trunk_name' => null, 'amd' => 0,
            'ivr_config' => null,
        );
    }

    private function audioList()
    {
        return $this->db->all('SELECT id, name, duration_sec FROM audio_files ORDER BY name');
    }

    /** Read & validate the form into a row array */
    private function fromForm(array $base)
    {
        $errors = array();
        $d = $base;
        $d['name'] = Request::str('name', '', 128);
        if ($d['name'] === '') {
            $errors[] = t('name_required');
        }
        $d['description'] = Request::str('description', '', 2000);
        $d['audio_id'] = Request::int('audio_id', 0, 0);
        if ($d['audio_id'] <= 0) {
            $d['audio_id'] = null;
        } elseif (!$this->db->val('SELECT id FROM audio_files WHERE id = ?', array($d['audio_id']))) {
            $d['audio_id'] = null;
        }
        $d['max_repeats'] = Request::int('max_repeats', 1, 0, 5);
        $d['max_retries'] = Request::int('max_retries', 1, 0, 10);
        $d['retry_delay_min'] = Request::int('retry_delay_min', 15, 1, 1440);
        $ro = Request::post('retry_on', array());
        $ro = is_array($ro) ? array_values(array_intersect($ro, CallStatus::retryable())) : array();
        $d['retry_on'] = implode(',', $ro);
        $d['concurrent'] = Request::int('concurrent', 2, 1, 200);
        $d['gap_ms'] = Request::int('gap_ms', 1500, 0, 600000);
        $d['ring_timeout'] = Request::int('ring_timeout', 30, 5, 120);
        $d['priority'] = Request::int('priority', 5, 1, 10);
        $d['amd'] = Request::int('amd', 0, 0, 1);
        $d['respect_holidays'] = Request::int('respect_holidays', 1, 0, 1);
        foreach (array('start_at', 'end_at') as $k) {
            $v = Request::str($k, '', 32);
            $d[$k] = null;
            if ($v !== '') {
                $ts = strtotime(Util::toAsciiDigits($v));
                if ($ts === false) {
                    $errors[] = t('bad_datetime') . ': ' . $k;
                } else {
                    $d[$k] = date('Y-m-d H:i:00', $ts);
                }
            }
        }
        if ($d['start_at'] && $d['end_at'] && $d['end_at'] <= $d['start_at']) {
            $errors[] = t('end_before_start');
        }
        $ws = Request::str('work_start', '', 5);
        $we = Request::str('work_end', '', 5);
        $d['work_start'] = Util::isHm($ws) ? $ws : null;
        $d['work_end'] = Util::isHm($we) ? $we : null;
        $wd = Request::post('work_days', array());
        $d['work_days'] = null;
        if (is_array($wd)) {
            $wd = array_values(array_unique(array_filter(array_map('intval', $wd), function ($x) {
                return $x >= 0 && $x <= 6;
            })));
            if ($wd && count($wd) < 7) {
                $d['work_days'] = implode(',', $wd);
            } elseif (count($wd) === 7) {
                $d['work_days'] = null;
            }
        }
        if (Request::int('use_global_window', 1, 0, 1) === 1) {
            $d['work_start'] = null;
            $d['work_end'] = null;
            $d['work_days'] = null;
        }
        $d['callerid_name'] = Request::str('callerid_name', '', 64);
        $d['callerid_number'] = Util::dialSafe(Request::str('callerid_number', '', 32));
        $d['dial_prefix'] = Request::str('dial_prefix', '', 16);
        $d['trunk_name'] = Request::str('trunk_name', '', 64);
        $d['channel_tech'] = Request::str('channel_tech', '', 16);
        foreach (array('callerid_name', 'callerid_number', 'dial_prefix', 'trunk_name', 'channel_tech') as $k) {
            if ($d[$k] === '') {
                $d[$k] = null;
            }
        }
        if ($d['channel_tech'] !== null && !in_array($d['channel_tech'], array('local', 'sip', 'pjsip', 'custom'), true)) {
            $d['channel_tech'] = null;
        }
        if (Request::int('use_global_prefix', 1, 0, 1) === 1) {
            $d['dial_prefix'] = null;
        }
        // IVR
        $ivr = array('timeout_sec' => Request::int('ivr_timeout', 5, 1, 30), 'max_repeats' => $d['max_repeats'], 'digits' => array());
        foreach (array('1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '*', '#') as $k) {
            $key = $k === '*' ? 'star' : ($k === '#' ? 'hash' : $k);
            $ivr['digits'][$k] = array(
                'action' => Request::str('ivr_action_' . $key, 'none', 16),
                'target' => Request::str('ivr_target_' . $key, '', 32),
                'context' => Request::str('ivr_context_' . $key, 'from-internal', 64),
                'audio_id' => Request::int('ivr_audio_' . $key, 0, 0),
                'tag' => Request::str('ivr_tag_' . $key, '', 64),
            );
        }
        $d['ivr_config'] = Util::json(Campaign::cleanIvr($ivr));
        return array($d, $errors);
    }

    public function store()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        list($d, $errors) = $this->fromForm($this->defaults());
        if ($errors) {
            foreach ($errors as $e) {
                Flash::set('error', $e);
            }
            $this->view('campaigns/form', array('c' => $d, 'audio' => $this->audioList(), 'ivr' => Campaign::parseIvr($d['ivr_config']), 'isNew' => true));
            return;
        }
        unset($d['id'], $d['status']);
        $d['kind'] = 'campaign';
        $d['status'] = 'draft';
        $d['created_by'] = Auth::user()['id'];
        $d['created_at'] = Util::now();
        $d['updated_at'] = Util::now();
        $id = $this->db->insert('campaigns', $d);
        Audit::log('campaign.create', 'campaign', $id, $d['name']);
        Flash::set('success', t('saved'));
        $this->redirect('/campaigns/' . $id . '/import');
    }

    public function edit($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $c = $this->load($p['id']);
        $this->view('campaigns/form', array('c' => $c, 'audio' => $this->audioList(), 'ivr' => Campaign::parseIvr($c['ivr_config']), 'isNew' => false));
    }

    public function update($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $c = $this->load($p['id']);
        list($d, $errors) = $this->fromForm($c);
        if ($errors) {
            foreach ($errors as $e) {
                Flash::set('error', $e);
            }
            $this->view('campaigns/form', array('c' => $d, 'audio' => $this->audioList(), 'ivr' => Campaign::parseIvr($d['ivr_config']), 'isNew' => false));
            return;
        }
        $keep = array('name', 'description', 'audio_id', 'max_repeats', 'max_retries', 'retry_delay_min', 'retry_on', 'concurrent', 'gap_ms', 'ring_timeout', 'start_at', 'end_at', 'work_start', 'work_end', 'work_days', 'respect_holidays', 'priority', 'callerid_name', 'callerid_number', 'dial_prefix', 'channel_tech', 'trunk_name', 'amd', 'ivr_config');
        $upd = array();
        foreach ($keep as $k) {
            $upd[$k] = $d[$k];
        }
        $upd['updated_at'] = Util::now();
        $this->db->update('campaigns', $upd, 'id = ?', array((int)$c['id']));
        Audit::log('campaign.update', 'campaign', $c['id'], $d['name']);
        Flash::set('success', t('saved'));
        $this->redirect('/campaigns/' . $c['id']);
    }

    public function show($p)
    {
        Auth::requireLogin();
        $c = $this->load($p['id']);
        $stats = Campaign::stats($c['id']);
        $audio = $c['audio_id'] ? $this->db->one('SELECT * FROM audio_files WHERE id = ?', array((int)$c['audio_id'])) : null;
        $tags = $this->db->all('SELECT result_tag, COUNT(*) n FROM campaign_contacts WHERE campaign_id = ? AND result_tag IS NOT NULL GROUP BY result_tag ORDER BY n DESC LIMIT 20', array((int)$c['id']));
        $dtmf = $this->db->all("SELECT LEFT(dtmf,1) k, COUNT(*) n FROM campaign_contacts WHERE campaign_id = ? AND dtmf IS NOT NULL AND dtmf <> '' GROUP BY LEFT(dtmf,1) ORDER BY n DESC", array((int)$c['id']));
        $this->view('campaigns/show', array('c' => $c, 'stats' => $stats, 'audio' => $audio, 'ivr' => Campaign::parseIvr($c['ivr_config']), 'tags' => $tags, 'dtmf' => $dtmf, 'eff' => Campaign::effective($c)));
    }

    public function action($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $c = $this->load($p['id']);
        $a = Request::str('do', '', 20);
        $now = Util::now();
        $id = (int)$c['id'];
        $msg = t('done');
        switch ($a) {
            case 'start':
                if (!$c['audio_id'] && !$this->db->val('SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND audio_id IS NOT NULL', array($id))) {
                    Flash::set('error', t('no_audio_selected'));
                    $this->back($id);
                }
                if (!$this->db->val("SELECT COUNT(*) FROM campaign_contacts WHERE campaign_id = ? AND status = 'pending'", array($id))) {
                    Flash::set('error', t('no_pending_contacts'));
                    $this->back($id);
                }
                $status = ($c['start_at'] && strtotime($c['start_at']) > time()) ? 'scheduled' : 'running';
                $this->db->update('campaigns', array('status' => $status, 'started_at' => $c['started_at'] ? $c['started_at'] : $now, 'finished_at' => null, 'last_error' => null, 'updated_at' => $now), 'id = ?', array($id));
                $msg = $status === 'scheduled' ? t('campaign_scheduled') : t('campaign_started');
                break;
            case 'pause':
                if (in_array($c['status'], array('running', 'scheduled'), true)) {
                    $this->db->update('campaigns', array('status' => 'paused', 'updated_at' => $now), 'id = ?', array($id));
                    $msg = t('campaign_paused');
                }
                break;
            case 'resume':
                if ($c['status'] === 'paused') {
                    $this->db->update('campaigns', array('status' => 'running', 'updated_at' => $now, 'last_error' => null), 'id = ?', array($id));
                    $msg = t('campaign_started');
                }
                break;
            case 'stop':
                if (in_array($c['status'], array('running', 'paused', 'scheduled'), true)) {
                    $this->db->update('campaigns', array('status' => 'stopped', 'finished_at' => $now, 'updated_at' => $now), 'id = ?', array($id));
                    $this->db->exec("UPDATE campaign_contacts SET status = 'cancelled', updated_at = ? WHERE campaign_id = ? AND status = 'pending'", array($now, $id));
                    Campaign::refreshCounters($id);
                    $msg = t('campaign_stopped');
                }
                break;
            case 'requeue':
                // put failed / cancelled / noanswer ... back into the queue
                $which = Request::post('statuses', array());
                $which = is_array($which) ? array_values(array_intersect($which, array('noanswer', 'busy', 'congestion', 'failed', 'cancelled', 'machine'))) : array();
                if ($which) {
                    $in = implode(',', array_fill(0, count($which), '?'));
                    $n = $this->db->exec("UPDATE campaign_contacts SET status = 'pending', next_attempt_at = NULL, attempts = 0, updated_at = ? WHERE campaign_id = ? AND status IN ($in)", array_merge(array($now, $id), $which));
                    if ($c['status'] === 'completed' || $c['status'] === 'stopped') {
                        $this->db->update('campaigns', array('status' => 'draft', 'finished_at' => null, 'updated_at' => $now), 'id = ?', array($id));
                    }
                    Campaign::refreshCounters($id);
                    $msg = t('requeued_n', $n);
                }
                break;
            case 'duplicate':
                $new = $c;
                unset($new['id']);
                $new['name'] = $c['name'] . ' (copy)';
                $new['status'] = 'draft';
                $new['total_contacts'] = 0;
                $new['cnt_done'] = 0;
                $new['cnt_answered'] = 0;
                $new['started_at'] = null;
                $new['finished_at'] = null;
                $new['last_dial_at'] = null;
                $new['last_error'] = null;
                $new['created_by'] = Auth::user()['id'];
                $new['created_at'] = $now;
                $new['updated_at'] = $now;
                $nid = $this->db->insert('campaigns', $new);
                if (Request::int('with_contacts', 0, 0, 1) === 1) {
                    $this->db->exec("INSERT INTO campaign_contacts (campaign_id, phone, raw_phone, name, audio_id, extra, status, updated_at) SELECT ?, phone, raw_phone, name, audio_id, extra, 'pending', ? FROM campaign_contacts WHERE campaign_id = ? AND status <> 'dnc'", array($nid, $now, $id));
                    Campaign::refreshCounters($nid);
                }
                Audit::log('campaign.duplicate', 'campaign', $nid, 'from #' . $id);
                Flash::set('success', t('done'));
                $this->redirect('/campaigns/' . $nid);
                break;
            case 'delete':
                $this->requireRole(Auth::ROLE_ADMIN);
                if (in_array($c['status'], array('running', 'scheduled'), true)) {
                    Flash::set('error', t('stop_before_delete'));
                    $this->back($id);
                }
                $this->db->exec('DELETE FROM call_attempts WHERE campaign_id = ?', array($id));
                $this->db->exec('DELETE FROM campaign_contacts WHERE campaign_id = ?', array($id));
                $this->db->exec('DELETE FROM campaigns WHERE id = ?', array($id));
                Audit::log('campaign.delete', 'campaign', $id, $c['name']);
                Flash::set('success', t('deleted'));
                $this->redirect('/campaigns');
                break;
            case 'clear_contacts':
                if (in_array($c['status'], array('running', 'scheduled'), true)) {
                    Flash::set('error', t('stop_before_delete'));
                    $this->back($id);
                }
                $this->db->exec('DELETE FROM call_attempts WHERE campaign_id = ?', array($id));
                $this->db->exec('DELETE FROM campaign_contacts WHERE campaign_id = ?', array($id));
                Campaign::refreshCounters($id);
                $msg = t('deleted');
                break;
            default:
                $msg = t('unknown_action');
        }
        Audit::log('campaign.' . $a, 'campaign', $id, $c['name']);
        Flash::set('success', $msg);
        $this->back($id);
    }

    private function back($id)
    {
        $this->redirect('/campaigns/' . $id);
    }

    // ---------------- import

    public function importForm($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $c = $this->load($p['id']);
        $this->view('campaigns/import', array('c' => $c, 'step' => 1, 'stats' => Campaign::stats($c['id'])));
    }

    public function importUpload($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $c = $this->load($p['id']);
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Flash::set('error', t('upload_error'));
            $this->redirect('/campaigns/' . $c['id'] . '/import');
        }
        $f = $_FILES['file'];
        if ($f['size'] > 30 * 1024 * 1024) {
            Flash::set('error', t('file_too_big'));
            $this->redirect('/campaigns/' . $c['id'] . '/import');
        }
        $token = Util::token(8);
        $dest = Config::storage('uploads/imp_' . $token . '.dat');
        move_uploaded_file($f['tmp_name'], $dest);
        try {
            $parsed = Importer::parseFile($dest, $f['name']);
        } catch (Exception $e) {
            @unlink($dest);
            Flash::set('error', t('parse_failed') . ': ' . $e->getMessage());
            $this->redirect('/campaigns/' . $c['id'] . '/import');
        }
        $rows = $parsed['rows'];
        if (!$rows) {
            @unlink($dest);
            Flash::set('error', t('file_empty'));
            $this->redirect('/campaigns/' . $c['id'] . '/import');
        }
        // store parsed rows for the confirm step
        file_put_contents(Config::storage('uploads/imp_' . $token . '.json'), Util::json($rows));
        @unlink($dest);
        $_SESSION['import_' . $token] = array('campaign' => (int)$c['id'], 'name' => $f['name'], 'rows' => count($rows));
        $guess = Importer::guessMapping($rows[0]);
        $cols = max(array_map('count', array_slice($rows, 0, 20)));
        $this->view('campaigns/import', array(
            'c' => $c, 'step' => 2, 'token' => $token, 'preview' => array_slice($rows, 0, 8), 'cols' => $cols,
            'guess' => $guess, 'rowCount' => count($rows), 'fileName' => $f['name'], 'stats' => Campaign::stats($c['id']),
        ));
    }

    public function importConfirm($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $c = $this->load($p['id']);
        $token = preg_replace('/[^a-f0-9]/', '', Request::str('token', '', 32));
        $json = Config::storage('uploads/imp_' . $token . '.json');
        if ($token === '' || empty($_SESSION['import_' . $token]) || !is_file($json)) {
            Flash::set('error', t('import_session_expired'));
            $this->redirect('/campaigns/' . $c['id'] . '/import');
        }
        $rows = json_decode(file_get_contents($json), true);
        @unlink($json);
        unset($_SESSION['import_' . $token]);
        $map = array(
            'phone' => Request::int('col_phone', 0, 0),
            'name' => Request::int('col_name', -1, -1),
            'audio' => Request::int('col_audio', -1, -1),
            'has_header' => Request::int('has_header', 0, 0, 1) === 1,
        );
        $opts = array('skip_dnc' => Request::int('skip_dnc', 1, 0, 1) === 1, 'dedupe' => Request::int('dedupe', 1, 0, 1) === 1);
        try {
            $stats = Importer::importContacts($c['id'], is_array($rows) ? $rows : array(), $map, $opts);
        } catch (Exception $e) {
            Flash::set('error', t('import_failed') . ': ' . $e->getMessage());
            $this->redirect('/campaigns/' . $c['id'] . '/import');
        }
        Audit::log('campaign.import', 'campaign', $c['id'], $stats);
        Flash::set('success', t('import_result', $stats['added'], $stats['duplicate'], $stats['invalid'], $stats['dnc'], $stats['audio_missing']));
        $this->redirect('/campaigns/' . $c['id']);
    }

    public function addContacts($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $c = $this->load($p['id']);
        $text = (string)Request::post('numbers', '');
        $lines = preg_split('/[\r\n,;]+/', $text);
        $rows = array();
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l !== '') {
                $parts = preg_split('/\s+/', $l, 2);
                $rows[] = array($parts[0], isset($parts[1]) ? $parts[1] : '');
            }
        }
        $stats = Importer::importContacts($c['id'], $rows, array('phone' => 0, 'name' => 1, 'audio' => -1, 'has_header' => false));
        Flash::set('success', t('import_result', $stats['added'], $stats['duplicate'], $stats['invalid'], $stats['dnc'], $stats['audio_missing']));
        $this->redirect('/campaigns/' . $c['id']);
    }

    // ---------------- json

    public function statsJson($p)
    {
        Auth::requireLogin();
        $c = $this->load($p['id']);
        $s = Campaign::stats($c['id']);
        $this->json(array('ok' => true, 'status' => $c['status'], 'last_error' => $c['last_error'], 'stats' => $s));
    }

    public function contactsJson($p)
    {
        Auth::requireLogin();
        $c = $this->load($p['id']);
        $status = Request::str('status', '', 20);
        $q = Request::str('q', '', 64);
        $page = Request::int('page', 1, 1);
        $per = Request::int('per', 50, 10, 500);
        $where = array('campaign_id = ?');
        $params = array((int)$c['id']);
        if ($status !== '' && in_array($status, CallStatus::all(), true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        } elseif ($status === 'active') {
            $where[] = "status IN ('dialing','answered')";
        } elseif ($status === 'pressed') {
            $where[] = "dtmf IS NOT NULL AND dtmf <> ''";
        }
        if ($q !== '') {
            $where[] = '(phone LIKE ? OR name LIKE ?)';
            $params[] = '%' . Util::toAsciiDigits($q) . '%';
            $params[] = '%' . $q . '%';
        }
        $w = implode(' AND ', $where);
        $total = (int)$this->db->val('SELECT COUNT(*) FROM campaign_contacts WHERE ' . $w, $params);
        $rows = $this->db->all('SELECT id, phone, name, status, attempts, next_attempt_at, last_attempt_at, answered_at, dtmf, result_tag, duration_sec, hangup_cause, amd_result, last_error, audio_id FROM campaign_contacts WHERE ' . $w . ' ORDER BY updated_at DESC, id DESC LIMIT ' . (($page - 1) * $per) . ',' . $per, $params);
        $this->json(array('ok' => true, 'total' => $total, 'page' => $page, 'per' => $per, 'rows' => $rows));
    }

    public function export($p)
    {
        Auth::requireLogin();
        $c = $this->load($p['id']);
        $format = Request::str('format', 'xlsx', 8) === 'csv' ? 'csv' : 'xlsx';
        $status = Request::str('status', '', 20);
        $where = 'cc.campaign_id = ?';
        $params = array((int)$c['id']);
        if ($status !== '' && in_array($status, CallStatus::all(), true)) {
            $where .= ' AND cc.status = ?';
            $params[] = $status;
        }
        $rows = $this->db->all('SELECT cc.*, a.name audio_name FROM campaign_contacts cc LEFT JOIN audio_files a ON a.id = cc.audio_id WHERE ' . $where . ' ORDER BY cc.id', $params);
        $header = array(t('phone'), t('name'), t('status'), t('attempts'), t('last_attempt'), t('answered_at'), t('duration'), t('dtmf'), t('result_tag'), t('hangup_cause'), t('audio'), t('raw_phone'));
        $extraKeys = array();
        foreach ($rows as $r) {
            if ($r['extra']) {
                foreach (json_decode($r['extra'], true) ?: array() as $k => $v) {
                    $extraKeys[$k] = true;
                }
            }
        }
        $extraKeys = array_keys($extraKeys);
        $header = array_merge($header, $extraKeys);
        $out = array();
        foreach ($rows as $r) {
            $line = array($r['phone'], $r['name'], t('st_' . $r['status']), $r['attempts'], $r['last_attempt_at'], $r['answered_at'], $r['duration_sec'], $r['dtmf'], $r['result_tag'], $r['hangup_cause'], $r['audio_name'], $r['raw_phone']);
            $ex = $r['extra'] ? (json_decode($r['extra'], true) ?: array()) : array();
            foreach ($extraKeys as $k) {
                $line[] = isset($ex[$k]) ? $ex[$k] : '';
            }
            $out[] = $line;
        }
        Audit::log('campaign.export', 'campaign', $c['id'], $format);
        Exporter::send($format, 'campaign_' . $c['id'] . '_' . $c['name'], $header, $out);
    }

    public function contactAction($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $c = $this->load($p['id']);
        $ct = $this->db->one('SELECT * FROM campaign_contacts WHERE id = ? AND campaign_id = ?', array((int)$p['cid'], (int)$c['id']));
        if (!$ct) {
            $this->json(array('ok' => false, 'error' => 'not_found'), 404);
        }
        $a = Request::str('do', '', 16);
        $now = Util::now();
        if ($a === 'cancel' && $ct['status'] === 'pending') {
            $this->db->update('campaign_contacts', array('status' => 'cancelled', 'updated_at' => $now), 'id = ?', array((int)$ct['id']));
        } elseif ($a === 'retry' && !in_array($ct['status'], array('pending', 'dialing', 'answered'), true)) {
            $this->db->update('campaign_contacts', array('status' => 'pending', 'next_attempt_at' => null, 'attempts' => 0, 'updated_at' => $now), 'id = ?', array((int)$ct['id']));
            if (in_array($c['status'], array('completed', 'stopped'), true)) {
                $this->db->update('campaigns', array('status' => 'draft', 'finished_at' => null, 'updated_at' => $now), 'id = ?', array((int)$c['id']));
            }
        } elseif ($a === 'dnc') {
            Dnc::add($ct['phone'], 'manual from campaign #' . $c['id'], 'manual', Auth::user()['id']);
            if ($ct['status'] === 'pending') {
                $this->db->update('campaign_contacts', array('status' => 'dnc', 'updated_at' => $now), 'id = ?', array((int)$ct['id']));
            }
        } elseif ($a === 'delete' && !in_array($ct['status'], array('dialing', 'answered'), true)) {
            $this->db->exec('DELETE FROM call_attempts WHERE contact_id = ?', array((int)$ct['id']));
            $this->db->exec('DELETE FROM campaign_contacts WHERE id = ?', array((int)$ct['id']));
        } else {
            $this->json(array('ok' => false, 'error' => 'not_allowed'), 400);
        }
        Campaign::refreshCounters($c['id']);
        $this->json(array('ok' => true));
    }
}
