<?php
/** Task 9.6 finance comparison pack. Read-only and CLI-only. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

function usage(): void { fwrite(STDERR, "Usage: php scripts/pettycash_comparison.php --out <file.xlsx>\n"); }
if (count($argv) !== 3 || $argv[1] !== '--out' || trim($argv[2]) === '') { usage(); exit(2); }
$requested = $argv[2];
$original = getcwd();
$absolute = preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $requested) ? $requested : $original . DIRECTORY_SEPARATOR . $requested;
$parent = dirname($absolute);
if (!is_dir($parent) || !is_writable($parent)) { fwrite(STDERR, "Output directory does not exist or is not writable: $parent\n"); exit(2); }
if (strtolower(pathinfo($absolute, PATHINFO_EXTENSION)) !== 'xlsx') { fwrite(STDERR, "Output file must have the .xlsx extension.\n"); exit(2); }

chdir(__DIR__ . '/../app');
require 'config/config.php';
require 'libraries/Database.php';
require 'libraries/Ledger.php';
require 'libraries/AccountChart.php';
require 'libraries/FinancialReport.php';
require 'libraries/XlsxWriter.php';
require 'libraries/PettyCashComparison.php';

try {
    $db = (new Database)->pdo();
    $data = PettyCashComparison::data($db);
    $bytes = file_put_contents($absolute, XlsxWriter::buildWorkbook(PettyCashComparison::sheets($data)));
    if ($bytes === false) { throw new RuntimeException('Could not write the workbook.'); }
    echo "Petty cash comparison (congregation {$data['cong']})\n";
    foreach ($data['summary'] as $r) {
        echo sprintf("%s account %d: old %s, ledger %s, difference %s\n", $r['year'], $r['account'], FinancialReport::money($r['old']), FinancialReport::money($r['new']), FinancialReport::money($r['difference']));
    }
    echo count($data['ledgerOnly']) . " ledger line(s) have no matching petty cash row; " . count($data['pettyOnly']) . " petty cash row(s) have no matching ledger line.\n";
    echo "Wrote $absolute ($bytes bytes, 5 sheets).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
