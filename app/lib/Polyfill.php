<?php
/**
 * Polyfills so the code base runs on PHP 5.4 (Issabel 4 / CentOS 7 stock)
 * all the way to PHP 8.x (Issabel 5 and beyond).
 */

if (!function_exists('hash_equals')) {
    function hash_equals($known, $user)
    {
        $known = (string)$known;
        $user = (string)$user;
        if (strlen($known) !== strlen($user)) {
            return false;
        }
        $res = 0;
        for ($i = 0, $n = strlen($known); $i < $n; $i++) {
            $res |= ord($known[$i]) ^ ord($user[$i]);
        }
        return $res === 0;
    }
}

if (!function_exists('random_bytes')) {
    function random_bytes($length)
    {
        $length = (int)$length;
        if (function_exists('openssl_random_pseudo_bytes')) {
            $strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            if ($bytes !== false && strlen($bytes) === $length) {
                return $bytes;
            }
        }
        if (is_readable('/dev/urandom')) {
            $fp = fopen('/dev/urandom', 'rb');
            if ($fp) {
                $bytes = fread($fp, $length);
                fclose($fp);
                if (strlen($bytes) === $length) {
                    return $bytes;
                }
            }
        }
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }
}

if (!function_exists('random_int')) {
    function random_int($min, $max)
    {
        $range = $max - $min;
        if ($range <= 0) {
            return $min;
        }
        $bytes = random_bytes(4);
        $val = unpack('N', $bytes);
        $val = $val[1] & 0x7fffffff;
        return $min + ($val % ($range + 1));
    }
}

if (!defined('PASSWORD_BCRYPT')) {
    define('PASSWORD_BCRYPT', 1);
    define('PASSWORD_DEFAULT', PASSWORD_BCRYPT);
}

if (!function_exists('password_hash')) {
    function password_hash($password, $algo = 1, array $options = array())
    {
        $cost = isset($options['cost']) ? (int)$options['cost'] : 10;
        if ($cost < 4) {
            $cost = 4;
        }
        if ($cost > 31) {
            $cost = 31;
        }
        // 22 chars from the bcrypt alphabet
        $raw = random_bytes(16);
        $salt = substr(strtr(base64_encode($raw), '+', '.'), 0, 22);
        $hash = crypt($password, sprintf('$2y$%02d$%s', $cost, $salt));
        if (!is_string($hash) || strlen($hash) < 60) {
            return false;
        }
        return $hash;
    }
}

if (!function_exists('password_verify')) {
    function password_verify($password, $hash)
    {
        if (!is_string($hash) || $hash === '') {
            return false;
        }
        $calc = crypt($password, $hash);
        return hash_equals($hash, $calc);
    }
}

if (!function_exists('password_needs_rehash')) {
    function password_needs_rehash($hash, $algo = 1, array $options = array())
    {
        return false;
    }
}

if (!function_exists('array_column')) {
    function array_column(array $input, $columnKey, $indexKey = null)
    {
        $out = array();
        foreach ($input as $row) {
            if ($columnKey === null) {
                $val = $row;
            } elseif (is_array($row) && array_key_exists($columnKey, $row)) {
                $val = $row[$columnKey];
            } else {
                continue;
            }
            if ($indexKey !== null && is_array($row) && array_key_exists($indexKey, $row)) {
                $out[$row[$indexKey]] = $val;
            } else {
                $out[] = $val;
            }
        }
        return $out;
    }
}

if (!function_exists('boolval')) {
    function boolval($v)
    {
        return (bool)$v;
    }
}

if (!function_exists('mb_str_split')) {
    function mb_str_split($str, $len = 1, $enc = 'UTF-8')
    {
        $out = array();
        $n = mb_strlen($str, $enc);
        for ($i = 0; $i < $n; $i += $len) {
            $out[] = mb_substr($str, $i, $len, $enc);
        }
        return $out;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($h, $n)
    {
        return $n === '' || strpos($h, $n) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n)
    {
        return $n === '' || strpos($h, $n) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($h, $n)
    {
        return $n === '' || substr($h, -strlen($n)) === $n;
    }
}

if (!function_exists('array_key_last')) {
    function array_key_last(array $a)
    {
        if (empty($a)) {
            return null;
        }
        $k = array_keys($a);
        return $k[count($k) - 1];
    }
}

if (!defined('JSON_UNESCAPED_UNICODE')) {
    define('JSON_UNESCAPED_UNICODE', 256);
}
if (!defined('JSON_PRETTY_PRINT')) {
    define('JSON_PRETTY_PRINT', 128);
}
if (!defined('JSON_UNESCAPED_SLASHES')) {
    define('JSON_UNESCAPED_SLASHES', 64);
}
