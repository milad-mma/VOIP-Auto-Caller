<?php
/**
 * CSV / XLSX export (streams to the browser).
 */
class XlsxWriter
{
    /** Build an .xlsx file at $path from $header + $rows. Returns false if ZipArchive is missing. */
    public static function write($path, array $header, array $rows, $sheetName = 'Sheet1')
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        $z = new ZipArchive();
        if ($z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $z->addFromString('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>');
        $z->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>');
        $z->addFromString('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="' . htmlspecialchars(mb_substr($sheetName, 0, 30, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>');
        $z->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
            '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" applyFont="1"/></cellXfs>' .
            '</styleSheet>');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="' . (I18n::isRtl() ? '1' : '0') . '"/></sheetViews><sheetData>';
        $xml .= self::row(1, $header, 1);
        $n = 2;
        foreach ($rows as $r) {
            $xml .= self::row($n, $r, 0);
            $n++;
        }
        $xml .= '</sheetData></worksheet>';
        $z->addFromString('xl/worksheets/sheet1.xml', $xml);
        $z->close();
        return true;
    }

    private static function row($n, array $cells, $style)
    {
        $out = '<row r="' . $n . '">';
        $col = 0;
        foreach ($cells as $v) {
            $ref = self::colName($col) . $n;
            $v = (string)$v;
            if ($v !== '' && preg_match('/^-?\d{1,15}(\.\d+)?$/', $v) && substr($v, 0, 1) !== '0') {
                $out .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $v . '</v></c>';
            } else {
                $out .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</t></is></c>';
            }
            $col++;
        }
        return $out . '</row>';
    }

    private static function colName($i)
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = (int)(($i - $m - 1) / 26);
        }
        return $s;
    }
}

class Exporter
{
    public static function send($format, $baseName, array $header, array $rows)
    {
        $baseName = Util::safeName($baseName, 40);
        if ($format === 'xlsx') {
            $tmp = Config::storage('tmp/' . $baseName . '_' . getmypid() . '_' . mt_rand(1000, 9999) . '.xlsx');
            if (XlsxWriter::write($tmp, $header, $rows)) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
                header('Content-Length: ' . filesize($tmp));
                readfile($tmp);
                @unlink($tmp);
                exit;
            }
            // fall back to csv
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens Persian text correctly
        fputcsv($out, $header);
        foreach ($rows as $r) {
            fputcsv($out, array_map(function ($v) {
                $v = (string)$v;
                // keep leading zeros in Excel: prefix long digit strings with = formula? no, keep plain; user can import as text
                return $v;
            }, $r));
        }
        fclose($out);
        exit;
    }
}
