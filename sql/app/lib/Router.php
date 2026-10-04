<?php
class Router
{
    private $routes = array();

    /** $pattern like '/campaigns/{id}/start' */
    public function add($method, $pattern, $handler)
    {
        if (strpos($pattern, '(?P<') !== false) {
            $regex = '#^' . $pattern . '$#';
        } else {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        }
        $this->routes[] = array('m' => $method, 'p' => $pattern, 'r' => $regex, 'h' => $handler);
    }

    public function get($p, $h)
    {
        $this->add('GET', $p, $h);
    }

    public function post($p, $h)
    {
        $this->add('POST', $p, $h);
    }

    public function any($p, $h)
    {
        $this->add('*', $p, $h);
    }

    public function dispatch($method, $path)
    {
        foreach ($this->routes as $r) {
            if ($r['m'] !== '*' && $r['m'] !== $method) {
                continue;
            }
            if (preg_match($r['r'], $path, $m)) {
                $params = array();
                foreach ($m as $k => $v) {
                    if (!is_int($k)) {
                        $params[$k] = $v;
                    }
                }
                list($cls, $fn) = explode('@', $r['h']);
                $ctl = new $cls();
                return call_user_func_array(array($ctl, $fn), array($params));
            }
        }
        http_response_code(404);
        if (Request::wantsJson()) {
            echo Util::json(array('ok' => false, 'error' => 'not_found'));
        } else {
            View::render('error', array('code' => 404, 'message' => I18n::t('not_found')));
        }
    }
}

/** Base controller */
class Controller
{
    /** @var Db */
    protected $db;

    public function __construct()
    {
        $this->db = Db::get();
    }

    protected function requireRole($role)
    {
        Auth::requireRole($role);
    }

    protected function csrf()
    {
        Auth::checkCsrf();
    }

    protected function json($d, $code = 200)
    {
        View::json($d, $code);
    }

    protected function view($t, array $v = array())
    {
        View::render($t, $v);
    }

    protected function redirect($p)
    {
        Util::redirect(View::url($p));
    }

    protected function notFound()
    {
        http_response_code(404);
        View::render('error', array('code' => 404, 'message' => I18n::t('not_found')));
        exit;
    }
}
