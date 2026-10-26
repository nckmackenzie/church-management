<?php
/** Small, dependency-free reader for the journal import's CSV and first XLSX worksheet. */
class JournalImport
{
    const MAX_BYTES = 2097152;
    const MAX_ROWS = 500;
    const MAX_XML_BYTES = 8388608;

    /** @return array<int,array<string,mixed>> */
    public static function read(string $path, string $extension): array
    {
        if ($extension === 'csv') { return self::csv($path); }
        if ($extension === 'xlsx') { return self::xlsx($path); }
        throw new InvalidArgumentException('Only .csv and .xlsx files are supported');
    }

    private static function rows(array $matrix): array
    {
        if (!$matrix) { throw new InvalidArgumentException('The file is empty'); }
        $header = array_shift($matrix);
        $aliases = ['date' => 'date', 'account' => 'account', 'glaccount' => 'account', 'glaccountname' => 'account',
                    'debit' => 'debit', 'credit' => 'credit', 'description' => 'description', 'narration' => 'description'];
        $map = [];
        foreach ($header as $i => $name) {
            $key = preg_replace('/[^a-z]/', '', strtolower(trim((string) $name)));
            if (isset($aliases[$key])) { $map[$i] = $aliases[$key]; }
        }
        foreach (['account', 'debit', 'credit'] as $required) {
            if (!in_array($required, $map, true)) {
                throw new InvalidArgumentException("The file needs an '$required' column");
            }
        }
        if (count($matrix) > self::MAX_ROWS) {
            throw new LengthException('The file has more than ' . self::MAX_ROWS . ' data rows');
        }
        if (!$matrix) { throw new InvalidArgumentException('The file has a header but no data rows'); }
        $out = [];
        foreach ($matrix as $number => $cells) {
            $row = ['_row' => $number + 2, 'date' => '', 'account' => '', 'debit' => '', 'credit' => '', 'description' => ''];
            foreach ($map as $i => $key) { $row[$key] = $cells[$i] ?? ''; }
            $out[] = $row;
        }
        return $out;
    }

    private static function csv(string $path): array
    {
        $h = fopen($path, 'rb');
        if (!$h) { throw new RuntimeException('The CSV file could not be read'); }
        $matrix = [];
        while (($row = fgetcsv($h)) !== false) {
            if (!$matrix && isset($row[0])) { $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]); }
            $matrix[] = $row;
            if (count($matrix) > self::MAX_ROWS + 1) { break; }
        }
        fclose($h);
        return self::rows($matrix);
    }

    private static function xlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) { throw new RuntimeException('XLSX files are not available on this server'); }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) { throw new InvalidArgumentException('The XLSX file is not a valid workbook'); }
        try {
            foreach (['xl/worksheets/sheet1.xml', 'xl/sharedStrings.xml', 'xl/styles.xml'] as $part) {
                $stat = $zip->statName($part);
                if ($stat !== false && (int)($stat['size'] ?? 0) > self::MAX_XML_BYTES) {
                    throw new LengthException('The XLSX workbook expands beyond the safe import limit');
                }
            }
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheet === false) { throw new InvalidArgumentException('The XLSX workbook has no first worksheet'); }
            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                $sx = @simplexml_load_string($sharedXml,'SimpleXMLElement',LIBXML_NONET);
                if ($sx) { foreach ($sx->si as $si) { $shared[] = self::richText($si); } }
            }
            $dateStyles = self::dateStyles((string) $zip->getFromName('xl/styles.xml'));
            $xml = @simplexml_load_string($sheet,'SimpleXMLElement',LIBXML_NONET);
            if (!$xml) { throw new InvalidArgumentException('The XLSX worksheet is invalid'); }
            $matrix = [];
            foreach ($xml->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $cell) {
                    $ref = (string) $cell['r'];
                    preg_match('/^[A-Z]+/', $ref, $m);
                    $col = self::columnIndex($m[0] ?? 'A');
                    $type = (string) $cell['t']; $style = (int) $cell['s'];
                    if ($type === 'inlineStr') { $value = self::richText($cell->is); }
                    elseif ($type === 's') { $value = $shared[(int) $cell->v] ?? ''; }
                    else { $value = isset($cell->v) ? (string) $cell->v : ''; }
                    if ($value !== '' && isset($dateStyles[$style]) && is_numeric($value)) { $value = self::excelDate((float) $value); }
                    $cells[$col] = $value;
                }
                if ($cells) { $matrix[] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells); }
                if (count($matrix) > self::MAX_ROWS + 1) { break; }
            }
            return self::rows($matrix);
        } finally { $zip->close(); }
    }

    private static function richText(SimpleXMLElement $node): string
    {
        if (isset($node->t)) { return (string) $node->t; }
        $s = ''; foreach ($node->r as $run) { $s .= (string) $run->t; } return $s;
    }

    /** @return array<int,true> */
    private static function dateStyles(string $styles): array
    {
        if ($styles === '') { return []; }
        $xml = @simplexml_load_string($styles,'SimpleXMLElement',LIBXML_NONET); if (!$xml) { return []; }
        $formats = [];
        if (isset($xml->numFmts)) { foreach ($xml->numFmts->numFmt as $f) { $formats[(int)$f['numFmtId']] = strtolower((string)$f['formatCode']); } }
        $out = []; $i = 0;
        if (isset($xml->cellXfs)) foreach ($xml->cellXfs->xf as $xf) {
            $id = (int) $xf['numFmtId']; $code = $formats[$id] ?? '';
            if (($id >= 14 && $id <= 22) || (strpos($code, 'd') !== false && (strpos($code, 'y') !== false || strpos($code, 'm') !== false))) { $out[$i] = true; }
            $i++;
        }
        return $out;
    }

    private static function columnIndex(string $letters): int
    {
        $n = 0; foreach (str_split($letters) as $c) { $n = $n * 26 + ord($c) - 64; } return max(0, $n - 1);
    }

    private static function excelDate(float $serial): string
    {
        $days = (int) floor($serial);
        $d = new DateTime('1899-12-30', new DateTimeZone('UTC'));
        $d->modify(($days >= 0 ? '+' : '') . $days . ' days');
        return $d->format('Y-m-d');
    }
}
