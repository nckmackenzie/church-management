<?php
// SQL-injection hotfix HTTP regression suite. Apache must be running and
// scripts/dev_session_helper.php must exist. This file itself is CLI-only.
// Task 9.7: the five report endpoints now answer JSON (the HTML bodies the 16 valid requests were once hashed against are gone),
// so a valid request must answer 200 + {success, columns, rows}; the 204 rejected-input cases are unchanged.
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
$pdo=(new Database)->pdo();
$_SESSION=['congId'=>11,'userId'=>64];
$base=URLROOT;$pass=0;$fail=0;$phpErrors=[];
function q($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);}
function chkSqli($name,$ok,$extra=''){global $pass,$fail;if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name $extra\n";}}
$helper=__DIR__.'/dev_session_helper.php';if(!is_file($helper)){exit("scripts/dev_session_helper.php is missing\n");}require_once $helper;
function httpSqli($path,array $params,$sid){global $base;$ch=curl_init($base.$path.'?'.http_build_query($params));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>'PHPSESSID='.$sid,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>15]);$started=microtime(true);$raw=curl_exec($ch);$elapsed=microtime(true)-$started;$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);$error=curl_error($ch);curl_close($ch);if($raw===false){throw new RuntimeException($error);}return [$code,substr((string)$raw,$hs),$elapsed];}
function tableSnapshotSqli($tables){global $pdo;$snapshot=[];foreach($tables as $table){$count=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();$checksum=$pdo->query('CHECKSUM TABLE `'.$table.'`')->fetch(PDO::FETCH_ASSOC);$snapshot[$table]=['rows'=>$count,'checksum'=>$checksum['Checksum']];}return $snapshot;}

$congregationSession=mkSession(2,2);$parishSession=mkSession(1,1);
$cases=[
 'contributions_all'=>['/reports/contributionsrpt',['type'=>'1','start'=>'2024-01-01','end'=>'2026-10-24'],$congregationSession,'ac4c00e2813258bbbce040a06ee7ad962ae1b35309f1b403b46c778809495be6'],
 'contributions_single'=>['/reports/contributionsrpt',['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['30']],$congregationSession,'ac4c00e2813258bbbce040a06ee7ad962ae1b35309f1b403b46c778809495be6'],
 'contributions_multi'=>['/reports/contributionsrpt',['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['30','31','32']],$congregationSession,'ac4c00e2813258bbbce040a06ee7ad962ae1b35309f1b403b46c778809495be6'],
 'contributions_district_all'=>['/reports/contributionsrpt',['type'=>'3','start'=>'2024-01-01','end'=>'2026-10-24','account'=>[]],$congregationSession,'9c704c82a585ebd1f61991dcea06263ee57326b03a193571788fcb4f1dcf73c8'],
 'expenses_all'=>['/reports/expensesrpt',['type'=>'1','start'=>'2024-01-01','end'=>'2026-10-24'],$congregationSession,'a726e4546a2c420db9c2cfdd56155b63a101f8467b675561ccae854dae5c2845'],
 'expenses_single'=>['/reports/expensesrpt',['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['26']],$congregationSession,'a726e4546a2c420db9c2cfdd56155b63a101f8467b675561ccae854dae5c2845'],
 'expenses_multi'=>['/reports/expensesrpt',['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['26','27','28']],$congregationSession,'a726e4546a2c420db9c2cfdd56155b63a101f8467b675561ccae854dae5c2845'],
 'parish_contributions_single'=>['/parishreports/contributionsrpt',['congregations'=>['2'],'accounts'=>['30'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'0dffaf1d5f7b6e67d468161d77495085095d22345832457ea8498428adbe95a4'],
 'parish_contributions_multi'=>['/parishreports/contributionsrpt',['congregations'=>['2','8'],'accounts'=>['30','31','32'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'0dffaf1d5f7b6e67d468161d77495085095d22345832457ea8498428adbe95a4'],
 'parish_contributions_all'=>['/parishreports/contributionsrpt',['congregations'=>['1','2','8','11'],'accounts'=>['30','31','32','86'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'8669e077779e0dedc507bb9e6038e3eef08a0d8930c8742d31529151498f29a4'],
 'parish_bycontributor_single'=>['/parishreports/bycontributorrpt',['congregations'=>['2'],'accounts'=>['30'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'0dffaf1d5f7b6e67d468161d77495085095d22345832457ea8498428adbe95a4'],
 'parish_bycontributor_multi'=>['/parishreports/bycontributorrpt',['congregations'=>['2','8'],'accounts'=>['30','31','32'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'0dffaf1d5f7b6e67d468161d77495085095d22345832457ea8498428adbe95a4'],
 'parish_bycontributor_all'=>['/parishreports/bycontributorrpt',['congregations'=>['1','2','8','11'],'accounts'=>['30','31','32','86'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'0dffaf1d5f7b6e67d468161d77495085095d22345832457ea8498428adbe95a4'],
 'parish_expenses_single'=>['/parishreports/expensesrpt',['congregations'=>['2'],'accounts'=>['26'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'7f30d11d6318f2aa0f341a518f57eddfe19c71aa0c9bfa2fbabdfa456176847b'],
 'parish_expenses_multi'=>['/parishreports/expensesrpt',['congregations'=>['2','8'],'accounts'=>['26','27','28'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'7f30d11d6318f2aa0f341a518f57eddfe19c71aa0c9bfa2fbabdfa456176847b'],
 'parish_expenses_all'=>['/parishreports/expensesrpt',['congregations'=>['1','2','8','11'],'accounts'=>['26','27','28','29'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,'7f30d11d6318f2aa0f341a518f57eddfe19c71aa0c9bfa2fbabdfa456176847b'],
];
$tables=['tblcontributions_header','tblcontributions_details','tblaccounttypes','tblmember','tblgroups','tbldistricts','tblservices','tblpaymentmethods','tblexpenses','tblcongregation'];
$before=tableSnapshotSqli($tables);$apacheErrorLog='E:/xampp/apache/logs/error.log';$errorOffset=is_file($apacheErrorLog)?filesize($apacheErrorLog):0;
try{
 foreach($cases as $name=>[$path,$params,$sid,$hash]){[$code,$body]=httpSqli($path,$params,$sid);$j=json_decode($body,true);chkSqli("valid request $name",$code===200&&is_array($j)&&($j['success']??false)===true&&!empty($j['columns'])&&is_array($j['rows']??null)&&!preg_match('/SQLSTATE|Warning:|Fatal error:/i',$body),"status $code body ".substr($body,0,200));}
 $payloads=['1) OR 1=1 -- ','1 OR 1=1',"'",'1;SELECT SLEEP(3)','0x31','1e1','-1',''];
 $inputs=[
  ['/reports/contributionsrpt',['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['30']],$congregationSession,['type','start','end'],['account']],
  ['/reports/expensesrpt',['type'=>'2','start'=>'2024-01-01','end'=>'2026-10-24','account'=>['26']],$congregationSession,['type','start','end'],['account']],
  ['/parishreports/contributionsrpt',['congregations'=>['2'],'accounts'=>['30'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,['start','end'],['congregations','accounts']],
  ['/parishreports/bycontributorrpt',['congregations'=>['2'],'accounts'=>['30'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,['start','end'],['congregations','accounts']],
  ['/parishreports/expensesrpt',['congregations'=>['2'],'accounts'=>['26'],'start'=>'2024-01-01','end'=>'2026-10-24'],$parishSession,['start','end'],['congregations','accounts']],
 ];
 $reject=function($path,$params,$sid,$name,$statuses=[400]){[$code,$body,$elapsed]=httpSqli($path,$params,$sid);chkSqli($name,in_array($code,$statuses,true)&&!preg_match('/SQLSTATE|SQL syntax|Warning:|Fatal error:|PDOException/i',$body)&&$elapsed<3,"status $code time ".round($elapsed,3));};
 foreach($inputs as [$path,$baseParams,$sid,$scalars,$lists]){
  foreach($scalars as $parameter){foreach($payloads as $payload){$params=$baseParams;$params[$parameter]=$payload;$reject($path,$params,$sid,"reject $path $parameter ".var_export($payload,true));}$params=$baseParams;$params[$parameter]=['1'];$reject($path,$params,$sid,"reject $path nested scalar $parameter");}
  foreach($lists as $parameter){foreach($payloads as $payload){$params=$baseParams;$params[$parameter]=[$baseParams[$parameter][0],$payload];$reject($path,$params,$sid,"reject $path {$parameter}[] ".var_export($payload,true));}$params=$baseParams;$params[$parameter]=[[$baseParams[$parameter][0]]];$reject($path,$params,$sid,"reject $path nested $parameter");$params=$baseParams;$params[$parameter]=[];$reject($path,$params,$sid,"reject $path empty $parameter");$params=$baseParams;$params[$parameter]=array_fill(0,251,$baseParams[$parameter][0]);$reject($path,$params,$sid,"reject $path 251-item $parameter");$params=$baseParams;$params[$parameter]=array_fill(0,5000,$baseParams[$parameter][0]);$reject($path,$params,$sid,"reject $path 5000-item $parameter",[400,414]);}
 }
 $after=tableSnapshotSqli($tables);foreach($tables as $table){chkSqli("$table unchanged",$before[$table]===$after[$table],json_encode(['before'=>$before[$table],'after'=>$after[$table]]));}
 clearstatcache(true,$apacheErrorLog);$newErrors='';if(is_file($apacheErrorLog)&&filesize($apacheErrorLog)>$errorOffset){$h=fopen($apacheErrorLog,'rb');fseek($h,$errorOffset);$newErrors=stream_get_contents($h);fclose($h);}chkSqli('Apache PHP error log has no new warning/error',!preg_match('/PHP (Warning|Fatal|Parse|Notice)|SQLSTATE|PDOException/i',$newErrors),trim($newErrors));
}catch(Throwable $e){$fail++;echo 'FAIL unexpected exception '.$e->getMessage()."\n";}
finally{foreach($sessions as $sid){$file=session_save_path().DIRECTORY_SEPARATOR.'sess_'.$sid;if(is_file($file)){@unlink($file);}}}
echo "\n$pass passed, $fail failed\n";exit($fail?1:0);
