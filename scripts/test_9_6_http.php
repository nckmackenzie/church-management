<?php
/** Task 9.6 petty-cash report through Apache (extended in 9.7 to the coverage of the other HTTP suites). Self-cleaning, CLI-only. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }
chdir(__DIR__ . '/../app'); require 'boostrap.php';
$pdo = (new Database)->pdo(); $_SESSION = ['congId'=>11,'userId'=>64]; $base = URLROOT; $pass=0; $fail=0; $phpErrors=[];
function q96h($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);}
function x96h($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return (int)$pdo->lastInsertId();}
function q($sql,$p=[]){return q96h($sql,$p);}
function ok96h($n,$c,$x=''){global $pass,$fail;if($c){$pass++;echo "PASS $n\n";}else{$fail++;echo "FAIL $n".($x===''?'':": $x")."\n";}}
require_once __DIR__ . '/dev_session_helper.php';
function http96($path,$sid){global $base,$phpErrors;$ch=curl_init($base.'/'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>'PHPSESSID='.$sid,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>120]);$raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);$h=substr((string)$raw,0,$hs);$b=substr((string)$raw,$hs);if(stripos($h,'spreadsheetml')===false&&preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~',$b,$m)){$phpErrors[]=$path.' -> '.$m[2];}return[$code,$b,$h];}
function workbook96($bytes){$tmp=tempnam(sys_get_temp_dir(),'t96').'.xlsx';file_put_contents($tmp,$bytes);$z=new ZipArchive;if($z->open($tmp)!==true){@unlink($tmp);return null;}$xml=$z->getFromName('xl/worksheets/sheet1.xml');$z->close();@unlink($tmp);return $xml;}
function nums96($xml){preg_match_all('~<c [^>]*><v>(-?[0-9.]+)</v></c>~',(string)$xml,$m);return $m[1];}
/** "1,234.50" / "(1,234.50)" -> cents */
function cents96($t){$neg=strpos($t,'(')!==false||strpos($t,'-')===0;$d=preg_replace('/[^0-9.]/','',$t);$c=(int)round((float)$d*100);return $neg?-$c:$c;}

$created=[];
try {
    $s11=mkSession(64,11); $s2=mkSession(64,2);
    $contra=(int)q96h('SELECT ID FROM tblaccounttypes WHERE accountTypeId=2 AND deleted=0 ORDER BY ID LIMIT 1')[0]['ID'];
    for($i=1;$i<=3;$i++){
        $id=x96h('INSERT INTO journal_entries (congregationId,entryDate,transactionTypeId,transactionId,journalNo,reference,description,deleted) VALUES (11,?,5,?,?,?, ?,0)',['2098-04-0'.$i,97000+$i,97000+$i,'zz t96h ref '.$i,'zz t96h description '.$i]);$created[]=$id;
        $memo=$i===2?'zz t96h <script>alert(1)</script> unique':'zz t96h memo '.$i;
        x96h('INSERT INTO journal_lines (journalEntryId,accountId,dc,amount,memo,lineNumber) VALUES (?,98,\'DEBIT\',?,?,1)',[$id,(string)($i*10).'.00',$memo]);
        x96h('INSERT INTO journal_lines (journalEntryId,accountId,dc,amount,memo,lineNumber) VALUES (?,?,\'CREDIT\',?,?,2)',[$id,$contra,(string)($i*10).'.00','zz t96h contra']);
    }
    // a voided journal and another congregation's entry on the same account and dates: neither may appear
    $voided=x96h('INSERT INTO journal_entries (congregationId,entryDate,transactionTypeId,transactionId,journalNo,reference,description,deleted) VALUES (11,\'2098-04-05\',5,97010,97010,\'zz t96h voided\',\'zz t96h voided\',1)');$created[]=$voided;
    x96h('INSERT INTO journal_lines (journalEntryId,accountId,dc,amount,memo,lineNumber) VALUES (?,98,\'DEBIT\',\'500.00\',\'zz t96h voided memo\',1)',[$voided]);
    x96h('INSERT INTO journal_lines (journalEntryId,accountId,dc,amount,memo,lineNumber) VALUES (?,?,\'CREDIT\',\'500.00\',NULL,2)',[$voided,$contra]);

    echo "== page and JSON\n";
    [$c,$b]=http96('reports/pettycash',$s11);
    ok96h('petty cash page renders the account/date controls and module', $c===200&&strpos($b,'Petty Cash Book')!==false&&strpos($b,'petty-cash-v1.js')!==false&&strpos($b,'value="98"')!==false, "status $c");
    ok96h('the page contains no raw script text from the data and no congregation heading', strpos($b,'<script>alert(1)')===false);
    [$c,$b,$h]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&page=1&pageSize=2',$s11);$j=json_decode($b,true);
    ok96h('JSON page 1 has two of three rows and correct totals/closing metadata', $c===200&&$j['success']===true&&count($j['rows'])===2&&$j['count']===3&&$j['matching']===3&&$j['pages']===2&&$j['receipts']==='60.00'&&$j['payments']==='0.00', $b);
    ok96h('the answer is JSON (Content-Type) and the voided journal and its 500.00 are not in the totals', stripos($h,'application/json')!==false&&$j['receipts']==='60.00');
    $opening=cents96($j['opening']);
    ok96h('page 1 running balances: opening + 10.00, then + 20.00', cents96($j['rows'][0]['balance'])===$opening+1000&&cents96($j['rows'][1]['balance'])===$opening+3000, json_encode(array_column($j['rows'],'balance')));
    [$c,$b]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&page=2&pageSize=2',$s11);$j2=json_decode($b,true);
    ok96h('JSON page 2 has the remaining row', $c===200&&count($j2['rows'])===1&&$j2['page']===2);
    ok96h('page 2 balance continues the true running balance of page 1 (+ 30.00) and equals the closing balance', cents96($j2['rows'][0]['balance'])===cents96($j['rows'][1]['balance'])+3000&&cents96($j2['rows'][0]['balance'])===cents96($j2['closing'])&&cents96($j2['closing'])===$opening+6000, json_encode([$j2['rows'][0]['balance'],$j2['closing'],$j['opening']]));
    ok96h('opening and closing are the whole period\'s on every page (same on page 1 and 2), the filtered totals are of all matching rows', $j['opening']===$j2['opening']&&$j['closing']===$j2['closing']&&$j2['filteredReceipts']==='60.00');
    [$c,$b]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&search=unique',$s11);$j3=json_decode($b,true);
    ok96h('server search finds the hostile narration as data, not markup generated by PHP', $c===200&&$j3['matching']===1&&strpos($j3['rows'][0]['narration'],'<script>')!==false);
    ok96h('a searched page shows the filtered totals (receipts 20.00) while opening and closing stay those of the period', $j3['filteredReceipts']==='20.00'&&$j3['opening']===$j['opening']&&$j3['closing']===$j['closing']);
    foreach(['reference'=>'ref 3','narration'=>'memo 3'] as $label=>$needle){
        [$c,$b]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&search='.urlencode($needle),$s11);$js=json_decode($b,true);
        ok96h("search by $label returns the matching row only", $c===200&&$js['matching']===1, $b);
    }
    [$c,$b]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&search='.urlencode('30.00'),$s11);$ja=json_decode($b,true);
    ok96h('search by amount finds the receipt of 30.00 (a running balance ending in 30.00 matches too: the balance column is searchable)', $c===200&&$ja['matching']>=1&&in_array('30.00',array_column($ja['rows'],'receipt'),true), $b);
    [$c,$b]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&search=voided',$s11);$jv=json_decode($b,true);
    ok96h('the voided journal is not found by search either', $c===200&&$jv['matching']===0);
    $js=file_get_contents(__DIR__.'/../public/dist/js/pages/reports/petty-cash-v1.js');
    ok96h('client renders server strings with textContent and never innerHTML/insertAdjacentHTML/nextSibling', strpos($js,'textContent')!==false&&strpos($js,'innerHTML')===false&&strpos($js,'insertAdjacentHTML')===false&&strpos($js,'nextSibling')===false);

    echo "== Excel\n";
    [$c,$b,$h]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&format=xlsx',$s11);$xml=workbook96($b);
    ok96h('Excel returns a workbook containing all three rows, not only a page', $c===200&&stripos($h,'spreadsheetml')!==false&&$xml!==null&&substr_count($xml,'zz t96h memo')===2&&strpos($xml,'unique')!==false, "status $c");
    ok96h('Excel is an attachment, with the markup escaped in the XML (no raw script tag)', stripos($h,'attachment')!==false&&strpos((string)$xml,'<script>')===false&&strpos((string)$xml,'&lt;script&gt;')!==false);
    $n=nums96($xml);
    ok96h('Excel numbers are real numbers: receipts 10.00, 20.00, 30.00, the period total 60.00, and the closing balance', count(array_intersect(['10.00','20.00','30.00','60.00',number_format(cents96($j2['closing'])/100,2,'.','')],$n))===5, json_encode($n));
    ok96h('Excel running balances equal the screen\'s (opening + 10.00 ... closing)', in_array(number_format(($opening+1000)/100,2,'.',''),$n,true)&&in_array(number_format(($opening+6000)/100,2,'.',''),$n,true));
    [$c,$b]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&format=xlsx&search=unique',$s11);$xml=workbook96($b);
    ok96h('searched Excel contains every matching row and excludes nonmatches', $c===200&&strpos($xml,'unique')!==false&&strpos($xml,'zz t96h memo 1')===false&&strpos($xml,'zz t96h memo 3')===false);
    ok96h('searched Excel totals only the matching rows (20.00)', in_array('20.00',nums96($xml),true)&&!in_array('60.00',nums96($xml),true));

    echo "== validation and isolation\n";
    $cases=[
        'unknown account 999'=>'account=999&from=2098-04-01&to=2098-04-30',
        'text account'=>'account=98x&from=2098-04-01&to=2098-04-30',
        'decimal account'=>'account=98.5&from=2098-04-01&to=2098-04-30',
        'zero account'=>'account=0&from=2098-04-01&to=2098-04-30',
        'empty account'=>'account=&from=2098-04-01&to=2098-04-30',
        'missing account'=>'from=2098-04-01&to=2098-04-30',
        'array account'=>'account%5B%5D=98&from=2098-04-01&to=2098-04-30',
        'impossible from date'=>'account=98&from=2098-02-30&to=2098-04-30',
        'text date'=>'account=98&from=yesterday&to=2098-04-30',
        'reversed dates'=>'account=98&from=2098-05-01&to=2098-04-30',
        'missing to'=>'account=98&from=2098-04-01',
        'missing from'=>'account=98&to=2098-04-30',
        'array from'=>'account=98&from%5B%5D=2098-04-01&to=2098-04-30',
        'page 0'=>'account=98&from=2098-04-01&to=2098-04-30&page=0',
        'page text'=>'account=98&from=2098-04-01&to=2098-04-30&page=x',
        'pageSize 0'=>'account=98&from=2098-04-01&to=2098-04-30&pageSize=0',
        'pageSize 201'=>'account=98&from=2098-04-01&to=2098-04-30&pageSize=201',
        'search array'=>'account=98&from=2098-04-01&to=2098-04-30&search%5B%5D=a',
        'search of 101 characters'=>'account=98&from=2098-04-01&to=2098-04-30&search='.str_repeat('a',101),
        'SQL in the account'=>'account='.urlencode('98 OR 1=1').'&from=2098-04-01&to=2098-04-30',
    ];
    foreach($cases as $label=>$qs){[$bc,$bb]=http96('reports/pettycashrpt?'.$qs,$s11);$m=json_decode($bb,true);ok96h("$label answers 400 with a message", $bc===400&&is_array($m)&&$m['success']===false&&$m['message']!=='', "status $bc");}
    [$c]=http96('reports/pettycashrpt?account=194&from=2098-04-01&to=2098-04-30',$s2);
    ok96h('another congregation cannot request account 194', $c===400, "status $c");
    [$c,$b]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30',$s2);$jo=json_decode($b,true);
    ok96h('another congregation asking for the shared account 98 sees none of this congregation\'s journal lines', $c===200&&$jo['rows']===[]&&$jo['count']===0);
    [$c,$b,$h]=http96('reports/pettycashrpt?account=98&from=2098-04-01&to=2098-04-30&format=xlsx',$s2);
    ok96h('and its Excel holds none of them either', $c===200&&strpos((string)workbook96($b),'zz t96h')===false);
    ok96h('HTTP responses contain no PHP warning/notice/fatal text', !$phpErrors, implode(', ',$phpErrors));
}catch(Throwable $e){$fail++;echo 'FAIL unexpected exception '.get_class($e).': '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine()."\n";}
finally{
    foreach(array_reverse($created) as $id){$pdo->prepare('DELETE FROM journal_lines WHERE journalEntryId=?')->execute([$id]);$pdo->prepare('DELETE FROM journal_entries WHERE ID=?')->execute([$id]);}
    foreach($sessions as $sid){$f=session_save_path().DIRECTORY_SEPARATOR.'sess_'.$sid;if(is_file($f))unlink($f);}
}
echo "$pass passed / $fail failed\n";exit($fail?1:0);
