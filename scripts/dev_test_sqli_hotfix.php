<?php
// DEV ONLY - never deploy. Captures/compares report HTTP responses using real sessions.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

$pdo = new PDO('mysql:host=localhost;dbname=cms;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
function q($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
require __DIR__ . '/dev_session_helper.php';

$baseUrl = 'http://localhost/cms';
$baselineDir = __DIR__ . '/logs/hotfix-sqli-baseline';
if (!is_dir($baselineDir) && !mkdir($baselineDir, 0777, true) && !is_dir($baselineDir)) {
    throw new RuntimeException('Cannot create baseline directory.');
}

function httpGet($path, array $params, $sid) {
    global $baseUrl;
    $url = $baseUrl . $path . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Cookie: PHPSESSID=' . $sid],
    ]);
    $started = microtime(true);
    $raw = curl_exec($ch);
    $elapsed = microtime(true) - $started;
    if ($raw === false) { throw new RuntimeException(curl_error($ch)); }
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [
        'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'elapsed' => $elapsed,
        'body' => substr($raw, $headerSize),
    ];
}

$congregationSession = mkSession(2, 2);
$parishSession = mkSession(1, 1);
$cases = [
    'contributions_all' => ['/reports/contributionsrpt', ['type'=>'1','start'=>'2024-01-01','end'=>'2026-10-24'], $congregationSession],
    'contributions_single' => ['/reports/contributionsrpt', ['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['30']], $congregationSession],
    'contributions_multi' => ['/reports/contributionsrpt', ['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['30','31','32']], $congregationSession],
    'contributions_district_all' => ['/reports/contributionsrpt', ['type'=>'3','start'=>'2024-01-01','end'=>'2026-10-24','account'=>[]], $congregationSession],
    'expenses_all' => ['/reports/expensesrpt', ['type'=>'1','start'=>'2024-01-01','end'=>'2026-10-24'], $congregationSession],
    'expenses_single' => ['/reports/expensesrpt', ['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['26']], $congregationSession],
    'expenses_multi' => ['/reports/expensesrpt', ['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['26','27','28']], $congregationSession],
    'parish_contributions_single' => ['/parishreports/contributionsrpt', ['congregations'=>['2'],'accounts'=>['30'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
    'parish_contributions_multi' => ['/parishreports/contributionsrpt', ['congregations'=>['2','8'],'accounts'=>['30','31','32'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
    'parish_contributions_all' => ['/parishreports/contributionsrpt', ['congregations'=>['1','2','8','11'],'accounts'=>['30','31','32','86'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
    'parish_bycontributor_single' => ['/parishreports/bycontributorrpt', ['congregations'=>['2'],'accounts'=>['30'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
    'parish_bycontributor_multi' => ['/parishreports/bycontributorrpt', ['congregations'=>['2','8'],'accounts'=>['30','31','32'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
    'parish_bycontributor_all' => ['/parishreports/bycontributorrpt', ['congregations'=>['1','2','8','11'],'accounts'=>['30','31','32','86'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
    'parish_expenses_single' => ['/parishreports/expensesrpt', ['congregations'=>['2'],'accounts'=>['26'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
    'parish_expenses_multi' => ['/parishreports/expensesrpt', ['congregations'=>['2','8'],'accounts'=>['26','27','28'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
    'parish_expenses_all' => ['/parishreports/expensesrpt', ['congregations'=>['1','2','8','11'],'accounts'=>['26','27','28','29'],'start'=>'2024-01-01','end'=>'2026-10-24'], $parishSession],
];

$mode = $argv[1] ?? 'baseline';
$manifest = [];
$tables = ['tblcontributions_header','tblcontributions_details','tblaccounttypes','tblmember','tblgroups','tbldistricts','tblservices','tblpaymentmethods','tblexpenses','tblcongregation'];
function tableSnapshot(array $tables) {
    global $pdo;
    $snapshot = [];
    foreach ($tables as $table) {
        $count = (int)$pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        $checksum = $pdo->query('CHECKSUM TABLE `' . $table . '`')->fetch();
        $snapshot[$table] = ['rows'=>$count, 'checksum'=>$checksum['Checksum']];
    }
    return $snapshot;
}
$beforeTables = $mode === 'baseline' ? null : tableSnapshot($tables);
$apacheErrorLog = 'E:/xampp/apache/logs/error.log';
$errorLogOffset = is_file($apacheErrorLog) ? filesize($apacheErrorLog) : 0;
try {
    foreach ($cases as $name => [$path, $params, $sid]) {
        $response = httpGet($path, $params, $sid);
        $file = $baselineDir . '/' . $name . '.html';
        if ($mode === 'baseline') {
            file_put_contents($file, $response['body']);
        } elseif (!is_file($file) || file_get_contents($file) !== $response['body']) {
            throw new RuntimeException($name . ': response differs from baseline');
        }
        if ($response['status'] !== 200 || preg_match('/SQLSTATE|Warning:|Fatal error:/i', $response['body'])) {
            throw new RuntimeException($name . ': invalid response status/body');
        }
        $manifest[$name] = [
            'path' => $path,
            'params' => $params,
            'status' => $response['status'],
            'bytes' => strlen($response['body']),
            'sha256' => hash('sha256', $response['body']),
            'seconds' => round($response['elapsed'], 4),
        ];
        echo "PASS $name ({$response['status']}, " . strlen($response['body']) . " bytes)\n";
    }
    if ($mode === 'baseline') {
        file_put_contents($baselineDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
    if ($mode !== 'baseline') {
        $payloads = ['1) OR 1=1 -- ','1 OR 1=1',"'",'1;SELECT SLEEP(3)','0x31','1e1','-1',''];
        $invalidCases = 0;
        $endpointInputs = [
            ['/reports/contributionsrpt',['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['30']],$congregationSession,['type','start','end'],['account']],
            ['/reports/expensesrpt',['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['26']],$congregationSession,['type','start','end'],['account']],
            ['/parishreports/contributionsrpt',['congregations'=>['2'],'accounts'=>['30'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,['start','end'],['congregations','accounts']],
            ['/parishreports/bycontributorrpt',['congregations'=>['2'],'accounts'=>['30'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,['start','end'],['congregations','accounts']],
            ['/parishreports/expensesrpt',['congregations'=>['2'],'accounts'=>['26'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,['start','end'],['congregations','accounts']],
        ];
        $assertRejected = function($path,$params,$sid,$label,$allowedStatuses = [400]) use (&$invalidCases) {
            $response = httpGet($path,$params,$sid);
            if (!in_array($response['status'],$allowedStatuses,true)) {
                throw new RuntimeException($label . ': expected HTTP ' . implode('/',$allowedStatuses) . ', got ' . $response['status']);
            }
            if (preg_match('/SQLSTATE|SQL syntax|Warning:|Fatal error:|PDOException/i',$response['body'])) {
                throw new RuntimeException($label . ': leaked SQL/PHP error text');
            }
            if ($response['elapsed'] > 3.0) {
                throw new RuntimeException($label . ': response was unexpectedly slow (' . round($response['elapsed'],3) . 's)');
            }
            $invalidCases++;
        };
        foreach ($endpointInputs as [$path,$base,$sid,$scalars,$lists]) {
            foreach ($scalars as $parameter) {
                foreach ($payloads as $payload) {
                    $params = $base; $params[$parameter] = $payload;
                    $assertRejected($path,$params,$sid,$path . ' ' . $parameter . '=' . var_export($payload,true));
                }
                $params = $base; $params[$parameter] = ['1'];
                $assertRejected($path,$params,$sid,$path . ' nested scalar ' . $parameter);
            }
            foreach ($lists as $parameter) {
                foreach ($payloads as $payload) {
                    $params = $base; $params[$parameter] = [$base[$parameter][0],$payload];
                    $assertRejected($path,$params,$sid,$path . ' ' . $parameter . '[]=' . var_export($payload,true));
                }
                $params = $base; $params[$parameter] = [[$base[$parameter][0]]];
                $assertRejected($path,$params,$sid,$path . ' nested list ' . $parameter);
                $params = $base; $params[$parameter] = [];
                $assertRejected($path,$params,$sid,$path . ' empty list ' . $parameter);
                $params = $base; $params[$parameter] = array_fill(0,251,$base[$parameter][0]);
                $assertRejected($path,$params,$sid,$path . ' application oversized list ' . $parameter);
                $params = $base; $params[$parameter] = array_fill(0,5000,$base[$parameter][0]);
                // Apache may enforce its request-line limit (414) before PHP can return 400.
                $assertRejected($path,$params,$sid,$path . ' oversized list ' . $parameter,[400,414]);
            }
        }
        $afterTables = tableSnapshot($tables);
        if ($beforeTables !== $afterTables) {
            throw new RuntimeException('A report request changed a source table: ' . json_encode(['before'=>$beforeTables,'after'=>$afterTables]));
        }
        clearstatcache(true,$apacheErrorLog);
        if (is_file($apacheErrorLog) && filesize($apacheErrorLog) > $errorLogOffset) {
            $handle = fopen($apacheErrorLog,'rb');
            fseek($handle,$errorLogOffset);
            $newErrors = stream_get_contents($handle);
            fclose($handle);
            if (preg_match('/PHP (Warning|Fatal|Parse|Notice)|SQLSTATE|PDOException/i',$newErrors)) {
                throw new RuntimeException('PHP error log contains a new warning/error: ' . trim($newErrors));
            }
        }
        echo 'PASS ' . $invalidCases . " invalid requests returned fast, clean HTTP 400 responses (414 where Apache rejected a 5,000-item request line first)\n";
        echo "PASS source-table row counts and CHECKSUM TABLE values unchanged\n";
        echo "PASS Apache PHP error log has no new warning/error\n";
    }
} finally {
    foreach ($sessions as $sid) {
        $file = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . $sid;
        if (is_file($file)) { unlink($file); }
    }
}
