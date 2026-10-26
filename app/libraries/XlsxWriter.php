<?php
/**
 * Minimal .xlsx writer (task 9.3, decision 141): one sheet, real numbers, a few styles, no dependency (no PhpSpreadsheet, no
 * ZipArchive: the package is a "stored" ZIP written here), so it works on the shared host that deploys by git pull.
 *
 * Cells (build them with the helpers):
 *   XlsxWriter::s('text', $bold)      text (always written as a string, never as a formula)
 *   XlsxWriter::m($cents, $bold)      money from integer cents, shown 1,234.56 / (1,234.56)
 *   XlsxWriter::n($number, $bold)     plain number (counts, numbers of entries)
 *   XlsxWriter::d('2026-10-24')       date, shown dd-mm-yyyy
 *   null / ''                         empty cell
 * A row is an array of cells; a plain string / int is accepted for convenience (string -> text, int -> number).
 */
class XlsxWriter
{
    public static function s($text, bool $bold = false): array { return ['t' => 's', 'v' => (string) $text, 'b' => $bold]; }
    public static function m(?int $cents, bool $bold = false): ?array { return $cents === null ? null : ['t' => 'm', 'v' => $cents, 'b' => $bold]; }
    public static function n($number, bool $bold = false): ?array { return $number === null || $number === '' ? null : ['t' => 'n', 'v' => $number + 0, 'b' => $bold]; }
    public static function d(?string $ymd): ?array { return ($ymd === null || $ymd === '') ? null : ['t' => 'd', 'v' => $ymd, 'b' => false]; }

    /** "A".."Z","AA".. for a 0-based column index */
    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) { $s = chr(65 + ($i - 1) % 26) . $s; }
        return $s;
    }

    /** XML text with characters illegal in XML 1.0 removed */
    private static function esc(string $v): string
    {
        $v = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $v) ?? '';
        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function serial(string $ymd): ?int
    {
        $d = DateTime::createFromFormat('!Y-m-d', $ymd);
        if (!$d) { return null; }
        return (int) round(($d->getTimestamp() - gmmktime(0, 0, 0, 12, 30, 1899)) / 86400);
    }

    /**
     * @param array $rows   list of rows (see the cell helpers)
     * @param array $widths optional column widths (characters)
     */
    public static function build(string $sheetName, array $rows, array $widths = []): string
    {
        return self::buildWorkbook([['name' => $sheetName, 'rows' => $rows, 'widths' => $widths]]);
    }

    /** Build a workbook with one or more sheets. Each sheet has name, rows and optional widths. */
    public static function buildWorkbook(array $sheets): string
    {
        if (!$sheets) { $sheets = [['name' => 'Report', 'rows' => [], 'widths' => []]]; }
        $files = [];
        $sheetXml = [];
        $sheetTags = [];
        $rels = [];
        $overrides = [];
        foreach (array_values($sheets) as $i => $sheet) {
            $n = $i + 1;
            $name = self::sheetName((string) ($sheet['name'] ?? ('Sheet ' . $n)));
            $sheetTags[] = '<sheet name="' . self::esc($name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $rels[] = '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $overrides[] = '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetXml[$n] = self::worksheet((array) ($sheet['rows'] ?? []), (array) ($sheet['widths'] ?? []));
        }
        $styleRel = count($sheets) + 1;
        $files['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . implode('', $overrides) . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
        $files['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $files['xl/workbook.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . implode('', $sheetTags) . '</sheets></workbook>';
        $files['xl/_rels/workbook.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . implode('', $rels) . '<Relationship Id="rId' . $styleRel . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $files['xl/styles.xml'] = self::styles();
        foreach ($sheetXml as $n => $xml) { $files['xl/worksheets/sheet' . $n . '.xml'] = $xml; }
        return self::zip($files);
    }

    private static function sheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name);
        return mb_substr(trim($name) === '' ? 'Report' : trim($name), 0, 31);
    }

    private static function worksheet(array $rows, array $widths): string
    {
        // styles: 0 normal, 1 bold, 2 money, 3 bold money, 4 date, 5 number, 6 bold number
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($widths) {
            $xml .= '<cols>';
            foreach (array_values($widths) as $i => $w) { $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $w . '" customWidth="1"/>'; }
            $xml .= '</cols>';
        }
        $xml .= '<sheetData>';
        $r = 0;
        foreach ($rows as $row) {
            $r++;
            $xml .= '<row r="' . $r . '">';
            foreach (array_values((array) $row) as $c => $cell) {
                if ($cell === null || $cell === '') { continue; }
                if (!is_array($cell)) { $cell = is_int($cell) || is_float($cell) ? ['t' => 'n', 'v' => $cell, 'b' => false] : ['t' => 's', 'v' => (string) $cell, 'b' => false]; }
                $ref = self::col($c) . $r;
                $bold = !empty($cell['b']);
                switch ($cell['t']) {
                    case 'm':
                        $xml .= '<c r="' . $ref . '" s="' . ($bold ? 3 : 2) . '"><v>' . self::decimal((int) $cell['v']) . '</v></c>';
                        break;
                    case 'n':
                        $xml .= '<c r="' . $ref . '" s="' . ($bold ? 6 : 5) . '"><v>' . (0 + $cell['v']) . '</v></c>';
                        break;
                    case 'd':
                        $ser = self::serial((string) $cell['v']);
                        $xml .= $ser === null ? '' : '<c r="' . $ref . '" s="4"><v>' . $ser . '</v></c>';
                        break;
                    default:
                        $xml .= '<c r="' . $ref . '" s="' . ($bold ? 1 : 0) . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $cell['v']) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00;(#,##0.00)"/><numFmt numFmtId="165" formatCode="dd\-mm\-yyyy"/></numFmts>'
                . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="7">'
                . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
                . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
                . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
                . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
                . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    private static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '') . intdiv(abs($cents), 100) . '.' . str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** a "stored" (uncompressed) ZIP archive */
    private static function zip(array $files): string
    {
        $out = ''; $central = ''; $n = 0;
        $time = 0; $date = (1 << 5) | 1 | ((2020 - 1980) << 9);   // 2020-01-01 00:00, fixed so the bytes are reproducible
        foreach ($files as $name => $data) {
            $crc = crc32($data); $len = strlen($data); $off = strlen($out);
            $out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0) . $name . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $off) . $name;
            $n++;
        }
        return $out . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($central), strlen($out), 0);
    }

    /** Send the workbook as a download and stop. */
    public static function download(string $filename, string $sheetName, array $rows, array $widths = []): void
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
        $bin = self::build($sheetName, $rows, $widths);
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $bin;
        exit;
    }

    /** Send a multi-sheet workbook as a download and stop. */
    public static function downloadWorkbook(string $filename, array $sheets): void
    {
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
        $bin = self::buildWorkbook($sheets);
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bin));
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo $bin;
        exit;
    }
}
