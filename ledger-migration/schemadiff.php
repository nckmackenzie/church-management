<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
// GENERATED (task 8.9 follow-up 3): compares the table / view / routine definitions of two mysqldump --no-data files (runbook step 1.5).
//   php ledger-migration/schemadiff.php prod_schema.sql dev_schema_snapshot.sql (git-ignored, produced on the dev machine)
// Prints ONLY IN A / ONLY IN B / DIFFERS per object; AUTO_INCREMENT counters and whitespace are ignored.
function blocks($f) {
    $s = file_get_contents($f);
    $raw = $s;
    $s = preg_replace('~/\*!\d+ [^*]*\*/;?~', '', $s);
    $out = [];
    if (preg_match_all('~CREATE TABLE `([^`]+)` \((.*?)\n\) ENGINE=([^;]*);~s', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $b) { $out['table ' . $b[1]] = preg_replace('~AUTO_INCREMENT=\d+ ~', '', $b[2] . ') ' . $b[3]); }
    }
    if (preg_match_all('~CREATE[^;]*?(?:FUNCTION|PROCEDURE) `([^`]+)`(.*?)\n(?:DELIMITER|END ?;;|END)~s', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $b) { $out['routine ' . $b[1]] = preg_replace('~\s+~', ' ', $b[2]); }
    }
    if (preg_match_all('~VIEW `([^`]+)` AS (select .*?)\*/;~s', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $b) { $out['view ' . $b[1]] = preg_replace('~\s+~', ' ', $b[2]); }
    }
    return $out;
}
$a = blocks($argv[1]); $b = blocks($argv[2]);
echo count($a), ' objects in A, ', count($b), " in B\n";
foreach ($b as $k => $v) { if (!isset($a[$k])) { echo "ONLY IN B: $k\n"; } elseif ($a[$k] !== $v) { echo "DIFFERS: $k\n"; } }
foreach ($a as $k => $v) { if (!isset($b[$k])) { echo "ONLY IN A: $k\n"; } }
