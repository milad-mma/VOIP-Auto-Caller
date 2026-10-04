<?php
class QuickCallController extends Controller
{
    public function form()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $audio = $this->db->all('SELECT id, name, duration_sec FROM audio_files ORDER BY name');
        $recent = $this->db->all("SELECT c.id, c.name, c.status, c.created_at, cc.phone, cc.status cstatus, cc.dtmf, cc.duration_sec, cc.hangup_cause FROM campaigns c JOIN campaign_contacts cc ON cc.campaign_id = c.id WHERE c.kind = 'quick' ORDER BY c.id DESC LIMIT 15");
        // one-time form token: a re-submitted (back/refresh/double-click) form is rejected
        $_SESSION['quick_token'] = Util::token(8);
        $this->view('quick', array('audio' => $audio, 'recent' => $recent, 'formToken' => $_SESSION['quick_token']));
    }

    public function call()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $tok = Request::str('form_token', '', 32);
        if ($tok === '' || empty($_SESSION['quick_token']) || !hash_equals($_SESSION['quick_token'], $tok)) {
            Flash::set('error', t('form_resubmitted'));
            $this->redirect('/quick');
        }
        unset($_SESSION['quick_token']);
        $phone = Util::normalizePhone(Request::str('phone', '', 32), Settings::get('country_code', '98'));
        $audioId = Request::int('audio_id', 0, 0);
        if ($phone === '' || !$this->db->val('SELECT id FROM audio_files WHERE id = ?', array($audioId))) {
            Flash::set('error', t('invalid_input'));
            $this->redirect('/quick');
        }
        // same number already in progress, or finished seconds ago: refuse unless explicitly forced
        $dup = $this->db->one(
            "SELECT cc.status, cc.updated_at FROM campaign_contacts cc WHERE cc.phone = ? AND (cc.status IN ('pending','dialing','answered') OR cc.updated_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)) ORDER BY cc.updated_at DESC LIMIT 1",
            array($phone)
        );
        if ($dup && Request::int('force', 0, 0, 1) !== 1) {
            Flash::set('error', in_array($dup['status'], array('pending', 'dialing', 'answered'), true) ? t('quick_dup_active', $phone) : t('quick_dup_recent', $phone));
            $this->redirect('/quick?phone=' . urlencode($phone) . '&dup=1');
        }
        $transfer = Util::dialSafe(Request::str('transfer', '', 32));
        $id = self::create($phone, $audioId, $transfer, Auth::user()['id'], Request::str('name', '', 128));
        Audit::log('quick.call', 'campaign', $id, $phone);
        Flash::set('success', t('call_queued'));
        $this->redirect('/quick');
    }

    /** Shared with the API: creates a 1-contact "quick" campaign that starts right away. */
    public static function create($phone, $audioId, $transferTo = '', $userId = null, $name = '')
    {
        $db = Db::get();
        $now = Util::now();
        $ivr = Campaign::defaultIvr();
        if ($transferTo !== '') {
            $ivr['digits']['1'] = array('action' => 'transfer', 'target' => $transferTo, 'context' => 'from-internal', 'tag' => 'transfer');
        }
        $id = $db->insert('campaigns', array(
            'name' => ($name !== '' ? $name . ' - ' : '') . $phone,
            'kind' => 'quick',
            'status' => 'running',
            'audio_id' => (int)$audioId,
            'ivr_config' => Util::json($ivr),
            'max_repeats' => 0, 'max_retries' => 0, 'retry_delay_min' => 1, 'retry_on' => '',
            'concurrent' => 1, 'gap_ms' => 0, 'ring_timeout' => (int)Settings::get('default_ring_timeout', 30),
            'work_start' => '00:00', 'work_end' => '23:59', 'work_days' => '0,1,2,3,4,5,6', 'respect_holidays' => 0,
            'priority' => 10,
            'started_at' => $now, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
        ));
        $db->insert('campaign_contacts', array(
            'campaign_id' => $id, 'phone' => $phone, 'raw_phone' => $phone, 'name' => mb_substr($name, 0, 128, 'UTF-8'),
            'status' => 'pending', 'updated_at' => $now,
        ));
        Campaign::refreshCounters($id);
        return $id;
    }
}

class AudioController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $rows = $this->db->all('SELECT a.*, u.username uploader, (SELECT COUNT(*) FROM campaigns c WHERE c.audio_id = a.id) used FROM audio_files a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.id DESC');
        $this->view('audio', array('rows' => $rows, 'sox' => Util::which('sox') !== null || Util::which('ffmpeg') !== null));
    }

    public function upload()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        if (empty($_FILES['file'])) {
            Flash::set('error', t('upload_error'));
            $this->redirect('/audio');
        }
        try {
            $a = Audio::upload($_FILES['file'], Request::str('name', '', 100), Auth::user()['id']);
            Audit::log('audio.upload', 'audio', $a['id'], $a['name']);
            Flash::set('success', t('audio_uploaded', $a['name'], $a['duration_sec']));
        } catch (Exception $e) {
            Flash::set('error', $e->getMessage());
        }
        $this->redirect('/audio');
    }

    public function play($p)
    {
        Auth::requireLogin();
        $a = $this->db->one('SELECT * FROM audio_files WHERE id = ?', array((int)$p['id']));
        $f = $a ? Config::storage($a['path']) : null;
        if (!$a || !is_file($f)) {
            $this->notFound();
        }
        header('Content-Type: audio/wav');
        header('Content-Length: ' . filesize($f));
        header('Content-Disposition: inline; filename="' . Util::safeName($a['name']) . '.wav"');
        header('Cache-Control: private, max-age=3600');
        readfile($f);
        exit;
    }

    public function delete($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        try {
            Audio::delete((int)$p['id']);
            Audit::log('audio.delete', 'audio', (int)$p['id']);
            Flash::set('success', t('deleted'));
        } catch (Exception $e) {
            Flash::set('error', $e->getMessage());
        }
        $this->redirect('/audio');
    }

    public function rename($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $name = Audio::cleanName(Request::str('name', '', 100));
        if ($this->db->val('SELECT id FROM audio_files WHERE name = ? AND id <> ?', array($name, (int)$p['id']))) {
            Flash::set('error', t('audio_name_exists'));
        } else {
            $this->db->update('audio_files', array('name' => $name), 'id = ?', array((int)$p['id']));
            Flash::set('success', t('saved'));
        }
        $this->redirect('/audio');
    }
}

class DncController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $q = Util::toAsciiDigits(Request::str('q', '', 32));
        $page = Request::int('page', 1, 1);
        $per = 50;
        $where = $q !== '' ? 'WHERE phone LIKE ?' : '';
        $params = $q !== '' ? array('%' . $q . '%') : array();
        $total = (int)$this->db->val('SELECT COUNT(*) FROM dnc ' . $where, $params);
        $rows = $this->db->all('SELECT d.*, u.username FROM dnc d LEFT JOIN users u ON u.id = d.created_by ' . $where . ' ORDER BY d.id DESC LIMIT ' . (($page - 1) * $per) . ',' . $per, $params);
        $this->view('dnc', array('rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'q' => $q));
    }

    public function add()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $text = (string)Request::post('numbers', '');
        $reason = Request::str('reason', '', 200);
        $n = 0;
        foreach (preg_split('/[\s,;]+/', $text) as $ph) {
            if (trim($ph) !== '' && Dnc::add($ph, $reason, 'manual', Auth::user()['id'])) {
                $n++;
            }
        }
        Audit::log('dnc.add', 'dnc', null, $n . ' numbers');
        Flash::set('success', t('dnc_added', $n));
        $this->redirect('/dnc');
    }

    public function import()
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Flash::set('error', t('upload_error'));
            $this->redirect('/dnc');
        }
        $tmp = Config::storage('uploads/dnc_' . Util::token(6));
        move_uploaded_file($_FILES['file']['tmp_name'], $tmp);
        try {
            $parsed = Importer::parseFile($tmp, $_FILES['file']['name']);
        } catch (Exception $e) {
            @unlink($tmp);
            Flash::set('error', $e->getMessage());
            $this->redirect('/dnc');
        }
        @unlink($tmp);
        $n = 0;
        foreach ($parsed['rows'] as $row) {
            foreach ($row as $cell) {
                if (Util::normalizePhone($cell) !== '' && strlen(preg_replace('/\D/', '', $cell)) >= 8) {
                    if (Dnc::add($cell, 'import ' . $_FILES['file']['name'], 'import', Auth::user()['id'])) {
                        $n++;
                    }
                    break;
                }
            }
        }
        Audit::log('dnc.import', 'dnc', null, $n . ' numbers');
        Flash::set('success', t('dnc_added', $n));
        $this->redirect('/dnc');
    }

    public function export()
    {
        Auth::requireLogin();
        $rows = $this->db->all('SELECT phone, reason, source, created_at FROM dnc ORDER BY id');
        $out = array();
        foreach ($rows as $r) {
            $out[] = array($r['phone'], $r['reason'], $r['source'], $r['created_at']);
        }
        Exporter::send(Request::str('format', 'xlsx', 8) === 'csv' ? 'csv' : 'xlsx', 'dnc_list', array(t('phone'), t('reason'), t('source'), t('created_at')), $out);
    }

    public function delete($p)
    {
        $this->requireRole(Auth::ROLE_OPERATOR);
        $this->csrf();
        $this->db->exec('DELETE FROM dnc WHERE id = ?', array((int)$p['id']));
        Audit::log('dnc.delete', 'dnc', (int)$p['id']);
        Flash::set('success', t('deleted'));
        $this->redirect('/dnc');
    }
}

class ReportController extends Controller
{
    private function range()
    {
        $from = Request::str('from', '', 20);
        $to = Request::str('to', '', 20);
        $fromTs = $from !== '' ? strtotime(Util::toAsciiDigits($from)) : strtotime('-7 days');
        $toTs = $to !== '' ? strtotime(Util::toAsciiDigits($to)) : time();
        if ($fromTs === false) {
            $fromTs = strtotime('-7 days');
        }
        if ($toTs === false) {
            $toTs = time();
        }
        return array(date('Y-m-d 00:00:00', $fromTs), date('Y-m-d 23:59:59', $toTs));
    }

    private function query(array $range, $campaignId)
    {
        $where = 'a.started_at BETWEEN ? AND ?';
        $params = array($range[0], $range[1]);
        if ($campaignId > 0) {
            $where .= ' AND a.campaign_id = ?';
            $params[] = $campaignId;
        }
        return array($where, $params);
    }

    public function index()
    {
        Auth::requireLogin();
        $range = $this->range();
        $cid = Request::int('campaign_id', 0, 0);
        list($where, $params) = $this->query($range, $cid);
        $summary = $this->db->one("SELECT COUNT(*) total, SUM(answered_at IS NOT NULL) answered, SUM(result='noanswer') noanswer, SUM(result='busy') busy, SUM(result IN ('failed','congestion')) failed, SUM(result='machine') machine, SUM(result='dnc') dnc, SUM(duration_sec) talk, AVG(CASE WHEN answered_at IS NOT NULL THEN duration_sec END) avg_talk FROM call_attempts a WHERE $where", $params);
        $byDay = $this->db->all("SELECT DATE(a.started_at) d, COUNT(*) total, SUM(answered_at IS NOT NULL) answered FROM call_attempts a WHERE $where GROUP BY DATE(a.started_at) ORDER BY d", $params);
        $byHour = $this->db->all("SELECT HOUR(a.started_at) h, COUNT(*) total, SUM(answered_at IS NOT NULL) answered FROM call_attempts a WHERE $where GROUP BY HOUR(a.started_at) ORDER BY h", $params);
        $byCampaign = $this->db->all("SELECT c.id, c.name, COUNT(*) total, SUM(a.answered_at IS NOT NULL) answered, SUM(a.duration_sec) talk, SUM(a.dtmf IS NOT NULL AND a.dtmf <> '') pressed FROM call_attempts a JOIN campaigns c ON c.id = a.campaign_id WHERE $where GROUP BY c.id, c.name ORDER BY total DESC LIMIT 50", $params);
        $dtmf = $this->db->all("SELECT LEFT(a.dtmf,1) k, COUNT(*) n FROM call_attempts a WHERE $where AND a.dtmf IS NOT NULL AND a.dtmf <> '' GROUP BY LEFT(a.dtmf,1) ORDER BY n DESC", $params);
        $campaigns = $this->db->all("SELECT id, name FROM campaigns WHERE kind = 'campaign' ORDER BY id DESC LIMIT 200");
        $this->view('reports', array('range' => $range, 'cid' => $cid, 'summary' => $summary, 'byDay' => $byDay, 'byHour' => $byHour, 'byCampaign' => $byCampaign, 'dtmf' => $dtmf, 'campaigns' => $campaigns));
    }

    public function export()
    {
        Auth::requireLogin();
        $range = $this->range();
        $cid = Request::int('campaign_id', 0, 0);
        list($where, $params) = $this->query($range, $cid);
        $rows = $this->db->all("SELECT a.*, c.name campaign, cc.phone, cc.name contact_name, cc.result_tag FROM call_attempts a JOIN campaigns c ON c.id = a.campaign_id JOIN campaign_contacts cc ON cc.id = a.contact_id WHERE $where ORDER BY a.started_at LIMIT 100000", $params);
        $out = array();
        foreach ($rows as $r) {
            $out[] = array($r['started_at'], $r['campaign'], $r['phone'], $r['contact_name'], $r['attempt_no'], t('st_' . $r['result']), $r['answered_at'], $r['ended_at'], $r['duration_sec'], $r['dtmf'], $r['result_tag'], $r['hangup_cause'], $r['amd_result'], $r['channel']);
        }
        Audit::log('report.export', 'report', null, $range[0] . '..' . $range[1]);
        Exporter::send(Request::str('format', 'xlsx', 8) === 'csv' ? 'csv' : 'xlsx', 'calls_' . substr($range[0], 0, 10) . '_' . substr($range[1], 0, 10),
            array(t('started_at'), t('campaign'), t('phone'), t('name'), t('attempt'), t('status'), t('answered_at'), t('ended_at'), t('duration'), t('dtmf'), t('result_tag'), t('hangup_cause'), 'AMD', t('channel')), $out);
    }
}
