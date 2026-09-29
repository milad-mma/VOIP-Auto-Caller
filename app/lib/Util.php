<?php
/**
 * Small helpers used everywhere. PHP 5.4 compatible.
 */
class Util
{
    /** HTML escape */
    public static function h($s)
    {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }

    public static function json($data, $pretty = false)
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode($data, $flags);
    }

    /** Convert Persian/Arabic-Indic digits to ASCII */
    public static function toAsciiDigits($s)
    {
        $fa = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        $ar = array('٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
        $en = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        return str_replace($ar, $en, str_replace($fa, $en, (string)$s));
    }

    /** Convert ASCII digits to Persian for display */
    public static function toFaDigits($s)
    {
        $fa = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        $en = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        return str_replace($en, $fa, (string)$s);
    }

    /**
     * Normalize a phone number to national format (e.g. 09123456789 / 02112345678).
     * $countryCode default 98 (Iran). Returns '' when nothing usable remains.
     * Handles: +98..., 0098..., 98..., 9123456789 (missing leading 0), spaces, dashes,
     * scientific notation from Excel (9.12346E+9 is NOT recoverable and returns '').
     */
    public static function normalizePhone($raw, $countryCode = '98')
    {
        $s = self::toAsciiDigits(trim((string)$raw));
        if ($s === '') {
            return '';
        }
        if (preg_match('/[eE]\+?\d+$/', $s)) {
            // Excel scientific notation - digits are lost
            return '';
        }
        $plus = (substr($s, 0, 1) === '+');
        $digits = preg_replace('/\D+/', '', $s);
        if ($digits === '') {
            return '';
        }
        $cc = preg_replace('/\D+/', '', (string)$countryCode);
        if ($cc !== '') {
            if (substr($digits, 0, strlen($cc) + 2) === '00' . $cc) {
                $digits = '0' . substr($digits, strlen($cc) + 2);
            } elseif ($plus && substr($digits, 0, strlen($cc)) === $cc) {
                $digits = '0' . substr($digits, strlen($cc));
            } elseif (substr($digits, 0, strlen($cc)) === $cc && strlen($digits) >= strlen($cc) + 10) {
                $digits = '0' . substr($digits, strlen($cc));
            }
        }
        if (substr($digits, 0, 1) !== '0' && strlen($digits) >= 10) {
            $digits = '0' . $digits;
        }
        // Collapse "00" -> "0" (e.g. 009123 typed by mistake)
        if (substr($digits, 0, 2) === '00') {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) < 3 || strlen($digits) > 20) {
            return '';
        }
        return $digits;
    }

    /** Only characters that are safe inside a dial string */
    public static function dialSafe($s)
    {
        return preg_replace('/[^0-9A-Za-z#*+]/', '', (string)$s);
    }

    /** Safe file base name (no path, ascii only) */
    public static function safeName($s, $max = 64)
    {
        $s = (string)$s;
        $s = preg_replace('/[^A-Za-z0-9_\-]/', '_', $s);
        $s = trim($s, '_');
        if ($s === '') {
            $s = 'file';
        }
        return substr($s, 0, $max);
    }

    /** Strip CR/LF and control chars (protect .call / AMI protocol) */
    public static function oneLine($s, $max = 255)
    {
        $s = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$s);
        return substr(trim($s), 0, $max);
    }

    public static function token($bytes = 24)
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function now()
    {
        return date('Y-m-d H:i:s');
    }

    public static function intOr($v, $default = 0, $min = null, $max = null)
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            $v = $default;
        }
        $v = (int)$v;
        if ($min !== null && $v < $min) {
            $v = $min;
        }
        if ($max !== null && $v > $max) {
            $v = $max;
        }
        return $v;
    }

    public static function floatOr($v, $default = 0.0, $min = null, $max = null)
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            $v = $default;
        }
        $v = (float)$v;
        if ($min !== null && $v < $min) {
            $v = $min;
        }
        if ($max !== null && $v > $max) {
            $v = $max;
        }
        return $v;
    }

    /** Detect text encoding of an uploaded file and convert to UTF-8 */
    public static function toUtf8($text)
    {
        if (substr($text, 0, 3) === "\xEF\xBB\xBF") {
            return substr($text, 3);
        }
        if (substr($text, 0, 2) === "\xFF\xFE" || substr($text, 0, 2) === "\xFE\xFF") {
            $out = @iconv('UTF-16', 'UTF-8//IGNORE', $text);
            return $out === false ? $text : $out;
        }
        if (function_exists('mb_check_encoding') && mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }
        foreach (array('Windows-1256', 'ISO-8859-6', 'Windows-1252') as $enc) {
            $out = @iconv($enc, 'UTF-8//IGNORE', $text);
            if ($out !== false && $out !== '') {
                return $out;
            }
        }
        return $text;
    }

    public static function formatDuration($sec)
    {
        $sec = (int)$sec;
        if ($sec < 60) {
            return $sec . 's';
        }
        return sprintf('%d:%02d', floor($sec / 60), $sec % 60);
    }

    public static function humanSize($bytes)
    {
        $bytes = (float)$bytes;
        $units = array('B', 'KB', 'MB', 'GB');
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $i ? 1 : 0) . ' ' . $units[$i];
    }

    /** Is "HH:MM" */
    public static function isHm($s)
    {
        return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$s);
    }

    public static function clientIp()
    {
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return '0.0.0.0';
    }

    public static function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    public static function exec($cmd, &$rc = null)
    {
        $out = array();
        $rc = 0;
        @exec($cmd . ' 2>&1', $out, $rc);
        return implode("\n", $out);
    }

    public static function which($bin)
    {
        foreach (array('/usr/bin', '/usr/sbin', '/bin', '/sbin', '/usr/local/bin') as $d) {
            if (is_executable($d . '/' . $bin)) {
                return $d . '/' . $bin;
            }
        }
        return null;
    }
}
