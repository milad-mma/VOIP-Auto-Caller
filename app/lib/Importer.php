<?php
/**
 * CSV / XLSX parsing and contact import.
 */
class XlsxReader
{
    /** @return array of rows (arrays of strings) for the first sheet, or throws */
    public static function read($file, $maxRows = 200000)
    {
        $shared = array();
        $sheetXml = null;
        $ssXml = null;
        $wbXml = null;
        $relsXml = null;
        if (class_exists('ZipArchive')) {
            $z = new ZipArchive();
            if ($z->open($file) !== true) {
                throw new RuntimeException('xlsx: cannot open zip');
            }
            $ssXml = $z->getFromName('xl/sharedStrings.xml');
            $wbXml = $z->getFromName('xl/workbook.xml');
            $relsXml = $z->getFromName('xl/_rels/workbook.xml.rels');
            $sheetName = self::firstSheetPath($wbXml, $relsXml);
            $sheetXml = $z->getFromName($sheetName);
            if ($sheetXml === false) {
                $sheetXml = $z->getFromName('xl/worksheets/sheet1.xml');
            }
            $z->close();
        } else {
            $unzip = Util::which('unzip');
            if (!$unzip) {
                throw new RuntimeException('xlsx: neither php-zip nor unzip available');
            }
            $ssXml = Util::exec($unzip . ' -p ' . escapeshellarg($file) . ' xl/sharedStrings.xml');
            $wbXml = Util::exec($unzip . ' -p ' . escapeshellarg($file) . ' xl/workbook.xml');
            $relsXml = Util::exec($unzip . ' -p ' . escapeshellarg($file) . ' xl/_rels/workbook.xml.rels');
            $sheetName = self::firstSheetPath($wbXml, $relsXml);
            $sheetXml = Util::exec($unzip . ' -p ' . escapeshellarg($file) . ' ' . escapeshellarg($sheetName));
            if (trim($sheetXml) === '' || strpos($sheetXml, '<') !== 0) {
                $sheetXml = Util::exec($unzip . ' -p ' . escapeshellarg($file) . ' xl/worksheets/sheet1.xml');
            }
        }
        if (!$sheetXml || strpos($sheetXml, '<') !== 0) {
            throw new RuntimeException('xlsx: worksheet not found');
        }
        if ($ssXml && strpos($ssXml, '<') === 0) {
            $shared = self::parseShared($ssXml);
        }
        return self::parseSheet($sheetXml, $shared, $maxRows);
    }

    private static function firstSheetPath($wbXml, $relsXml)
    {
        $default = 'xl/worksheets/sheet1.xml';
        if (!$wbXml || !$relsXml) {
            return $default;
        }
        try {
            $wb = new SimpleXMLElement($wbXml);
            $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $sheets = $wb->xpath('//m:sheets/m:sheet');
            if (!$sheets) {
                return $default;
            }
            $rid = null;
            foreach ($sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships') as $k => $v) {
                if ($k === 'id') {
                    $rid = (string)$v;
                }
            }
            if (!$rid) {
                return $default;
            }
            $rels = new SimpleXMLElement($relsXml);
            foreach ($rels->Relationship as $rel) {
                if ((string)$rel['Id'] === $rid) {
                    $t = (string)$rel['Target'];
                    $t = ltrim($t, '/');
                    if (strpos($t, 'xl/') !== 0) {
                        $t = 'xl/' . $t;
                    }
                    return $t;
                }
            }
        } catch (Exception $e) {
        }
        return $default;
    }

    private static function parseShared($xml)
    {
        $out = array();
        $r = new XMLReader();
        $r->XML($xml);
        $cur = null;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'si') {
                $cur = '';
            } elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 't' && $cur !== null) {
                $cur .= $r->readString();
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'si') {
                $out[] = $cur;
                $cur = null;
            }
        }
        return $out;
    }

    private static function colIndex($ref)
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $col = isset($m[1]) ? $m[1] : 'A';
        $n = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $n = $n * 26 + (ord($col[$i]) - 64);
        }
        return $n - 1;
    }

    private static function parseSheet($xml, array $shared, $maxRows)
    {
        $rows = array();
        $r = new XMLReader();
        $r->XML($xml);
        $row = null;
        $cellType = null;
        $cellCol = 0;
        $inV = false;
        $inIs = false;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT) {
                if ($r->localName === 'row') {
                    $row = array();
                } elseif ($r->localName === 'c' && $row !== null) {
                    $cellType = $r->getAttribute('t');
                    $cellCol = self::colIndex((string)$r->getAttribute('r'));
                    if ($r->isEmptyElement) {
                        $row[$cellCol] = '';
                    }
                } elseif ($r->localName === 'v' && $row !== null) {
                    $val = $r->readString();
                    if ($cellType === 's') {
                        $idx = (int)$val;
                        $val = isset($shared[$idx]) ? $shared[$idx] : '';
                    } elseif ($cellType === 'b') {
                        $val = $val === '1' ? 'TRUE' : 'FALSE';
                    } else {
                        $val = self::numberToString($val);
                    }
                    $row[$cellCol] = $val;
                } elseif ($r->localName === 't' && $row !== null && $cellType === 'inlineStr') {
                    $row[$cellCol] = (isset($row[$cellCol]) ? $row[$cellCol] : '') . $r->readString();
                }
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'row' && $row !== null) {
                if ($row) {
                    $max = max(array_keys($row));
                    $line = array();
                    for ($i = 0; $i <= $max; $i++) {
                        $line[] = isset($row[$i]) ? trim((string)$row[$i]) : '';
                    }
                    $rows[] = $line;
                    if (count($rows) >= $maxRows) {
                        break;
                    }
                }
                $row = null;
            }
        }
        return $rows;
    }

    /** "9.123456789E9" -> "9123456789"; keep other numbers untouched */
    public static function numberToString($v)
    {
        $v = trim((string)$v);
        if (preg_match('/^-?\d+(\.\d+)?[eE][+-]?\d+$/', $v)) {
            $f = (float)$v;
            if (abs($f) < 1e15 && floor($f) == $f) {
                return sprintf('%.0f', $f);
            }
        }
        if (preg_match('/^\d+\.0+$/', $v)) {
            return preg_replace('/\.0+$/', '', $v);
        }
        return $v;
    }
}

class Importer
{
    /**
     * Parse an uploaded file into rows.
     * @return array('rows' => array, 'type' => 'csv'|'xlsx')
     */
    public static function parseFile($path, $originalName)
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $magic = file_get_contents($path, false, null, 0, 4);
        if ($ext === 'xlsx' || $ext === 'xlsm' || $magic === "PK\x03\x04") {
            return array('rows' => XlsxReader::read($path), 'type' => 'xlsx');
        }
        if ($ext === 'xls') {
            throw new RuntimeException('legacy .xls is not supported, save as .xlsx or .csv');
        }
        $text = Util::toUtf8(file_get_contents($path));
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $lines = explode("\n", $text);
        $delim = self::detectDelimiter(isset($lines[0]) ? $lines[0] : '');
        $rows = array();
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line, $delim, '"', '\\');
            $row = array_map(function ($v) {
                return trim(Util::toAsciiDigits((string)$v));
            }, $row);
            $rows[] = $row;
            if (count($rows) > 200000) {
                break;
            }
        }
        return array('rows' => $rows, 'type' => 'csv');
    }

    private static function detectDelimiter($line)
    {
        $c = array(',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t"), '|' => substr_count($line, '|'));
        arsort($c);
        $k = key($c);
        return $c[$k] > 0 ? $k : ',';
    }

    /** Guess which columns are phone / name / audio from a header row */
    public static function guessMapping(array $header)
    {
        $m = array('phone' => -1, 'name' => -1, 'audio' => -1, 'has_header' => false);
        $pat = array(
            'phone' => '/(phone|mobile|tel|number|شماره|موبایل|تلفن|همراه)/iu',
            'name' => '/(^name$|fullname|full_name|نام|customer|مشتری)/iu',
            'audio' => '/(audio|voice|sound|file|صدا|صوت|فایل)/iu',
        );
        foreach ($header as $i => $h) {
            $h = trim((string)$h);
            foreach ($pat as $k => $re) {
                if ($m[$k] === -1 && preg_match($re, $h)) {
                    $m[$k] = $i;
                    $m['has_header'] = true;
                }
            }
        }
        if ($m['phone'] === -1) {
            // pick the column whose first values look like phone numbers
            foreach ($header as $i => $h) {
                if (preg_match('/^[\d\s\-\+\(\)]{6,}$/', Util::toAsciiDigits((string)$h))) {
                    $m['phone'] = $i;
                    break;
                }
            }
            if ($m['phone'] === -1) {
                $m['phone'] = count($header) - 1;
            }
        }
        return $m;
    }

    /**
     * Insert rows as campaign contacts.
     * $map: array phone=>colIdx, name=>colIdx|-1, audio=>colIdx|-1, has_header=>bool
     * @return array stats
     */
    public static function importContacts($campaignId, array $rows, array $map, array $opts = array())
    {
        $db = Db::get();
        $cc = Settings::get('country_code', '98');
        $skipDnc = !isset($opts['skip_dnc']) || $opts['skip_dnc'];
        $dedupe = !isset($opts['dedupe']) || $opts['dedupe'];
        $stats = array('added' => 0, 'invalid' => 0, 'dnc' => 0, 'duplicate' => 0, 'audio_missing' => 0);
        $header = null;
        if (!empty($map['has_header']) && $rows) {
            $header = array_shift($rows);
        }
        $audioCache = array();
        $existing = array();
        if ($dedupe) {
            foreach ($db->all('SELECT phone FROM campaign_contacts WHERE campaign_id = ?', array((int)$campaignId)) as $r) {
                $existing[$r['phone']] = true;
            }
        }
        $dncSet = array();
        if ($skipDnc) {
            foreach ($db->all('SELECT phone FROM dnc') as $r) {
                $dncSet[$r['phone']] = true;
            }
        }
        $pi = (int)$map['phone'];
        $ni = isset($map['name']) ? (int)$map['name'] : -1;
        $ai = isset($map['audio']) ? (int)$map['audio'] : -1;
        $now = Util::now();
        $db->begin();
        try {
            $st = $db->pdo()->prepare('INSERT INTO campaign_contacts (campaign_id, phone, raw_phone, name, audio_id, extra, status, updated_at) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($rows as $row) {
                $raw = isset($row[$pi]) ? $row[$pi] : '';
                $phone = Util::normalizePhone($raw, $cc);
                if ($phone === '') {
                    $stats['invalid']++;
                    continue;
                }
                if ($dedupe && isset($existing[$phone])) {
                    $stats['duplicate']++;
                    continue;
                }
                $status = CallStatus::PENDING;
                if ($skipDnc && isset($dncSet[$phone])) {
                    $status = CallStatus::DNC;
                    $stats['dnc']++;
                }
                $name = $ni >= 0 && isset($row[$ni]) ? mb_substr(trim($row[$ni]), 0, 128, 'UTF-8') : '';
                $audioId = null;
                if ($ai >= 0 && isset($row[$ai]) && trim($row[$ai]) !== '') {
                    $an = trim($row[$ai]);
                    $an = preg_replace('/\.(wav|mp3|gsm|ogg)$/i', '', $an);
                    if (!array_key_exists($an, $audioCache)) {
                        $audioCache[$an] = $db->val('SELECT id FROM audio_files WHERE name = ?', array($an));
                    }
                    if ($audioCache[$an]) {
                        $audioId = (int)$audioCache[$an];
                    } else {
                        $stats['audio_missing']++;
                    }
                }
                $extra = array();
                if ($header) {
                    foreach ($row as $i => $v) {
                        if ($i === $pi || $i === $ni || $i === $ai) {
                            continue;
                        }
                        if ($v !== '' && isset($header[$i]) && $header[$i] !== '') {
                            $extra[mb_substr($header[$i], 0, 40, 'UTF-8')] = mb_substr($v, 0, 200, 'UTF-8');
                        }
                    }
                }
                $st->execute(array((int)$campaignId, $phone, mb_substr($raw, 0, 64, 'UTF-8'), $name, $audioId, $extra ? Util::json($extra) : null, $status, $now));
                $existing[$phone] = true;
                if ($status === CallStatus::PENDING) {
                    $stats['added']++;
                }
            }
            $db->commit();
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
        Campaign::refreshCounters($campaignId);
        return $stats;
    }
}
