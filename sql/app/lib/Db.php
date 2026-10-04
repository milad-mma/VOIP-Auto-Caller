<?php
/**
 * Thin PDO wrapper. Prepared statements only.
 */
class Db
{
    /** @var PDO */
    private $pdo;
    private $cfg;
    private static $instance;

    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
        $this->connect();
    }

    public static function get()
    {
        if (!self::$instance) {
            $c = Config::get('db');
            self::$instance = new Db($c);
        }
        return self::$instance;
    }

    private function connect()
    {
        $c = $this->cfg;
        $dsn = 'mysql:';
        if (!empty($c['socket'])) {
            $dsn .= 'unix_socket=' . $c['socket'] . ';';
        } else {
            $dsn .= 'host=' . (isset($c['host']) ? $c['host'] : 'localhost') . ';';
            if (!empty($c['port'])) {
                $dsn .= 'port=' . (int)$c['port'] . ';';
            }
        }
        $dsn .= 'dbname=' . $c['name'] . ';charset=utf8mb4';
        $opts = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        );
        try {
            $this->pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
        } catch (PDOException $e) {
            // utf8mb4 may be unsupported in the DSN on very old builds; retry with utf8
            $dsn2 = str_replace('charset=utf8mb4', 'charset=utf8', $dsn);
            $this->pdo = new PDO($dsn2, $c['user'], $c['pass'], $opts);
        }
        try {
            $this->pdo->exec("SET NAMES utf8mb4");
        } catch (Exception $e) {
            $this->pdo->exec("SET NAMES utf8");
        }
        $this->pdo->exec("SET time_zone = '" . self::tzOffset() . "'");
    }

    /** Reconnect (used by long running daemon) */
    public function reconnect()
    {
        $this->pdo = null;
        $this->connect();
    }

    public function ping()
    {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            try {
                $this->reconnect();
                return true;
            } catch (Exception $e2) {
                return false;
            }
        }
    }

    private static function tzOffset()
    {
        $off = date('P');
        return $off;
    }

    public function pdo()
    {
        return $this->pdo;
    }

    /** @return PDOStatement */
    public function run($sql, array $params = array())
    {
        $st = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($params as $k => $v) {
            $type = PDO::PARAM_STR;
            if (is_int($v)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($v)) {
                $type = PDO::PARAM_INT;
                $v = $v ? 1 : 0;
            } elseif ($v === null) {
                $type = PDO::PARAM_NULL;
            }
            if (is_int($k)) {
                $st->bindValue($i, $v, $type);
                $i++;
            } else {
                $st->bindValue(':' . ltrim($k, ':'), $v, $type);
            }
        }
        $st->execute();
        return $st;
    }

    public function all($sql, array $params = array())
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function one($sql, array $params = array())
    {
        $r = $this->run($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public function val($sql, array $params = array())
    {
        $r = $this->run($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public function exec($sql, array $params = array())
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function insert($table, array $data)
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' .
            implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->run($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update($table, array $data, $where, array $whereParams = array())
    {
        $set = array();
        foreach (array_keys($data) as $c) {
            $set[] = '`' . $c . '`=?';
        }
        $sql = 'UPDATE `' . $table . '` SET ' . implode(',', $set) . ' WHERE ' . $where;
        return $this->run($sql, array_merge(array_values($data), $whereParams))->rowCount();
    }

    public function begin()
    {
        $this->pdo->beginTransaction();
    }

    public function commit()
    {
        $this->pdo->commit();
    }

    public function rollback()
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function lastId()
    {
        return (int)$this->pdo->lastInsertId();
    }

    public function tableExists($t)
    {
        // SHOW TABLES does not accept bound parameters on MariaDB/MySQL (native prepares)
        $n = $this->val('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', array($t));
        return (int)$n > 0;
    }
}
