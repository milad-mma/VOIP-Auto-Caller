<?php
class AuthController extends Controller
{
    public function loginForm()
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        View::render('login', array('error' => null), null);
    }

    public function login()
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $u = Request::str('username', '', 64);
        $p = (string)Request::post('password', '');
        $res = Auth::login($u, $p);
        if (is_array($res)) {
            $to = !empty($_SESSION['after_login']) ? $_SESSION['after_login'] : View::url('/');
            unset($_SESSION['after_login']);
            if (strpos($to, '/login') !== false || strpos($to, '://') !== false) {
                $to = View::url('/');
            }
            Util::redirect($to);
        }
        usleep(400000);
        View::render('login', array('error' => t($res), 'username' => $u), null);
    }

    public function logout()
    {
        Auth::checkCsrf();
        Auth::logout();
        $this->redirect('/login');
    }

    public function lang($p)
    {
        $l = in_array($p['lang'], array('fa', 'en'), true) ? $p['lang'] : 'fa';
        $_SESSION['lang'] = $l;
        $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : View::url('/');
        if (strpos($back, '://') !== false && strpos($back, (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '')) === false) {
            $back = View::url('/');
        }
        Util::redirect($back);
    }
}

class DashboardController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $campaigns = $this->db->all("SELECT * FROM campaigns WHERE kind = 'campaign' AND status IN ('running','paused','scheduled') ORDER BY priority DESC, id DESC LIMIT 20");
        $recent = $this->db->all("SELECT * FROM campaigns WHERE kind = 'campaign' AND status IN ('completed','stopped') ORDER BY finished_at DESC LIMIT 5");
        $today = $this->db->one(
            "SELECT COUNT(*) total, SUM(answered_at IS NOT NULL) answered, SUM(result='busy') busy, SUM(result='noanswer') noanswer, SUM(result IN ('failed','congestion')) failed, SUM(duration_sec) talk FROM call_attempts WHERE started_at >= CURDATE()"
        );
        $daemon = $this->db->one('SELECT * FROM daemon_status WHERE id = 1');
        $this->view('dashboard', array('campaigns' => $campaigns, 'recent' => $recent, 'today' => $today, 'daemon' => $daemon));
    }

    public function live()
    {
        Auth::requireLogin();
        $daemon = $this->db->one('SELECT * FROM daemon_status WHERE id = 1');
        $age = $daemon && $daemon['heartbeat_at'] ? time() - strtotime($daemon['heartbeat_at']) : null;
        $active = $this->db->all(
            "SELECT cc.id, cc.phone, cc.name, cc.status, cc.attempts, cc.answered_at, cc.dtmf, c.name campaign, c.id campaign_id, cc.last_attempt_at FROM campaign_contacts cc JOIN campaigns c ON c.id = cc.campaign_id WHERE cc.status IN ('dialing','answered') ORDER BY cc.last_attempt_at DESC LIMIT 50"
        );
        foreach ($active as &$a) {
            $a['elapsed'] = $a['answered_at'] ? time() - strtotime($a['answered_at']) : ($a['last_attempt_at'] ? time() - strtotime($a['last_attempt_at']) : 0);
        }
        unset($a);
        $campaigns = $this->db->all("SELECT id, name, status, total_contacts, cnt_done, cnt_answered, last_error, concurrent FROM campaigns WHERE kind='campaign' AND status IN ('running','paused','scheduled') ORDER BY priority DESC, id DESC");
        $today = $this->db->one(
            "SELECT COUNT(*) total, SUM(answered_at IS NOT NULL) answered, SUM(result='busy') busy, SUM(result='noanswer') noanswer, SUM(result IN ('failed','congestion')) failed, SUM(duration_sec) talk FROM call_attempts WHERE started_at >= CURDATE()"
        );
        $this->json(array(
            'ok' => true,
            'daemon' => array(
                'alive' => $age !== null && $age < 30,
                'ami' => $daemon ? (int)$daemon['ami_connected'] === 1 : false,
                'heartbeat_age' => $age,
                'active_calls' => $daemon ? (int)$daemon['active_calls'] : 0,
                'error' => $daemon ? $daemon['last_error'] : null,
            ),
            'active' => $active,
            'campaigns' => $campaigns,
            'today' => $today,
            'ts' => date('H:i:s'),
        ));
    }
}
