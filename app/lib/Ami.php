<?php
/**
 * Minimal Asterisk Manager Interface client (no external deps, PHP 5.4+).
 * Works with Asterisk 11 ... 20 (Issabel 4 & 5).
 */
class AmiException extends Exception
{
}

class Ami
{
    private $host;
    private $port;
    private $user;
    private $secret;
    private $sock;
    private $buf = '';
    private $seq = 0;
    private $events = array();
    private $responses = array();
    public $connected = false;
    public $lastActivity = 0;

    public function __construct($host, $port, $user, $secret)
    {
        $this->host = $host;
        $this->port = (int)$port;
        $this->user = $user;
        $this->secret = $secret;
    }

    public static function fromConfig()
    {
        $c = Config::get('ami');
        return new Ami($c['host'], $c['port'], $c['user'], $c['secret']);
    }

    public function connect($timeout = 5)
    {
        $this->close();
        $errno = 0;
        $errstr = '';
        $this->sock = @stream_socket_client('tcp://' . $this->host . ':' . $this->port, $errno, $errstr, $timeout);
        if (!$this->sock) {
            throw new AmiException("AMI connect failed: $errstr ($errno)");
        }
        stream_set_timeout($this->sock, 5);
        $banner = fgets($this->sock, 1024);
        if ($banner === false || stripos($banner, 'Asterisk Call Manager') === false) {
            $this->close();
            throw new AmiException('AMI banner not received');
        }
        $this->buf = '';
        $this->connected = true;
        $this->lastActivity = time();
        $r = $this->action('Login', array('Username' => $this->user, 'Secret' => $this->secret, 'Events' => 'on'), 5);
        if (!$r || strtolower(isset($r['Response']) ? $r['Response'] : '') !== 'success') {
            $msg = isset($r['Message']) ? $r['Message'] : 'no response';
            $this->close();
            throw new AmiException('AMI login failed: ' . $msg);
        }
        stream_set_blocking($this->sock, false);
        return true;
    }

    public function close()
    {
        if ($this->sock) {
            @fclose($this->sock);
        }
        $this->sock = null;
        $this->connected = false;
    }

    public function isConnected()
    {
        return $this->connected && $this->sock && !feof($this->sock);
    }

    private function nextId()
    {
        $this->seq++;
        return 'ac' . getmypid() . '-' . $this->seq;
    }

    /**
     * Send an action. If $wait > 0, blocks until the matching response arrives (events are queued).
     * @return array|null
     */
    public function action($name, array $params = array(), $wait = 5, $actionId = null)
    {
        if (!$this->sock) {
            throw new AmiException('not connected');
        }
        if ($actionId === null) {
            $actionId = $this->nextId();
        }
        $pkt = 'Action: ' . $name . "\r\n" . 'ActionID: ' . $actionId . "\r\n";
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    $pkt .= $k . ': ' . Util::oneLine($vv, 1024) . "\r\n";
                }
            } else {
                $pkt .= $k . ': ' . Util::oneLine($v, 1024) . "\r\n";
            }
        }
        $pkt .= "\r\n";
        $n = @fwrite($this->sock, $pkt);
        if ($n === false || $n < strlen($pkt)) {
            $this->connected = false;
            throw new AmiException('AMI write failed');
        }
        $this->lastActivity = time();
        if ($wait <= 0) {
            return array('ActionID' => $actionId);
        }
        $deadline = microtime(true) + $wait;
        while (microtime(true) < $deadline) {
            $this->pump(200);
            if (isset($this->responses[$actionId])) {
                $r = $this->responses[$actionId];
                unset($this->responses[$actionId]);
                return $r;
            }
        }
        return null;
    }

    /** Read from the socket for up to $ms milliseconds, queueing packets. */
    public function pump($ms = 100)
    {
        if (!$this->sock) {
            return;
        }
        $r = array($this->sock);
        $w = null;
        $e = null;
        $sec = (int)floor($ms / 1000);
        $usec = ($ms % 1000) * 1000;
        $n = @stream_select($r, $w, $e, $sec, $usec);
        if ($n === false) {
            return;
        }
        if ($n > 0) {
            $data = @fread($this->sock, 65536);
            if ($data === '' || $data === false) {
                if (feof($this->sock)) {
                    $this->connected = false;
                }
                return;
            }
            $this->lastActivity = time();
            $this->buf .= $data;
            $this->parse();
        }
    }

    private function parse()
    {
        while (($pos = strpos($this->buf, "\r\n\r\n")) !== false) {
            $raw = substr($this->buf, 0, $pos);
            $this->buf = substr($this->buf, $pos + 4);
            $pkt = array();
            foreach (explode("\r\n", $raw) as $line) {
                $c = strpos($line, ':');
                if ($c === false) {
                    continue;
                }
                $k = trim(substr($line, 0, $c));
                $v = trim(substr($line, $c + 1));
                if (isset($pkt[$k])) {
                    // repeated keys (e.g. Output, ChanVariable) -> keep last but also collect all
                    if (!isset($pkt[$k . '_list'])) {
                        $pkt[$k . '_list'] = array($pkt[$k]);
                    }
                    $pkt[$k . '_list'][] = $v;
                }
                $pkt[$k] = $v;
            }
            if (isset($pkt['Event'])) {
                $this->events[] = $pkt;
                if (count($this->events) > 5000) {
                    array_splice($this->events, 0, 1000);
                }
            } elseif (isset($pkt['Response'])) {
                $id = isset($pkt['ActionID']) ? $pkt['ActionID'] : 'noid';
                $this->responses[$id] = $pkt;
                if (count($this->responses) > 500) {
                    array_splice($this->responses, 0, 100);
                }
            }
        }
    }

    /** Pop queued events */
    public function events()
    {
        $e = $this->events;
        $this->events = array();
        return $e;
    }

    /** Pop a stray response (e.g. OriginateResponse comes as Event, not here) */
    public function popResponse($actionId)
    {
        if (isset($this->responses[$actionId])) {
            $r = $this->responses[$actionId];
            unset($this->responses[$actionId]);
            return $r;
        }
        return null;
    }

    public function ping()
    {
        $r = $this->action('Ping', array(), 3);
        return $r && isset($r['Response']) && strtolower($r['Response']) === 'success';
    }

    /**
     * Async originate. Returns ActionID (response arrives later as OriginateResponse event).
     */
    public function originate($channel, $context, $exten, $priority, $timeoutMs, $callerId, array $vars = array(), $actionId = null, $account = 'autocaller')
    {
        $params = array(
            'Channel' => $channel,
            'Context' => $context,
            'Exten' => $exten,
            'Priority' => $priority,
            'Timeout' => (int)$timeoutMs,
            'CallerID' => $callerId,
            'Account' => $account,
            'Async' => 'true',
        );
        $v = array();
        foreach ($vars as $k => $val) {
            $v[] = $k . '=' . str_replace(array(',', "\r", "\n"), array(' ', '', ''), (string)$val);
        }
        if ($v) {
            $params['Variable'] = $v;
        }
        $r = $this->action('Originate', $params, 0, $actionId);
        return $r['ActionID'];
    }

    public function hangup($channel)
    {
        return $this->action('Hangup', array('Channel' => $channel), 2);
    }

    public function command($cmd)
    {
        $r = $this->action('Command', array('Command' => $cmd), 5);
        if (!$r) {
            return '';
        }
        if (isset($r['Output'])) {
            return isset($r['Output_list']) ? implode("\n", $r['Output_list']) : $r['Output'];
        }
        return isset($r['Message']) ? $r['Message'] : '';
    }

    /** Count of active channels matching our account (rough concurrency safety) */
    public function coreShowChannelsCount()
    {
        $out = $this->command('core show channels count');
        if (preg_match('/(\d+)\s+active\s+call/i', $out, $m)) {
            return (int)$m[1];
        }
        return null;
    }
}
