<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
chdir(__DIR__.'/../app');require 'boostrap.php';
$pdo=(new Database)->pdo();$_SESSION=['congId'=>11,'userId'=>64];$base=URLROOT;$pass=0;$fail=0;$phpErrors=[];
function q($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);}
require_once __DIR__.'/dev_session_helper.php';
function h45ok($n,$v,$x=''){global $pass,$fail;if($v){$pass++;echo "PASS $n\n";}else{$fail++;echo "FAIL $n".($x!==''?": $x":'')."\n";}}
function h45($path,$sid,$fields){global $base,$phpErrors;$ch=curl_init($base.'/'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>'PHPSESSID='.$sid,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>60]);$raw=(string)curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);$body=substr($raw,$hs);if(preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~',$body,$m))$phpErrors[]=$path.' -> '.$m[2];return [$code,$body,json_decode($body,true)];}
$tmp=[];$apache='E:/xampp/apache/logs/error.log';$off=is_file($apache)?filesize($apache):0;
try{
 $s11=mkSession(64,11);$s2=mkSession(64,2);$mark='<img src=x onerror=alert(1)>';
 $xlsx=tempnam(sys_get_temp_dir(),'t45').'.xlsx';$tmp[]=$xlsx;
 file_put_contents($xlsx,XlsxWriter::build('Journal',[
  [XlsxWriter::s('Date'),XlsxWriter::s('Account'),XlsxWriter::s('Debit'),XlsxWriter::s('Credit'),XlsxWriter::s('Description')],
  [XlsxWriter::d('2026-10-20'),XlsxWriter::s(' CASH   AT HAND '),XlsxWriter::n(12.34),null,XlsxWriter::s($mark)],
  [XlsxWriter::d('2026-10-20'),XlsxWriter::s('co-op bank'),null,XlsxWriter::n(12.34),XlsxWriter::s('credit')]
 ]));
 [$c,$b,$j]=h45('journals/validateimport',$s11,['date'=>'2026-10-20','file'=>new CURLFile($xlsx,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','journal.xlsx')]);
 h45ok('real XLSX upload accepted',$c===200&&($j['success']??false),"$c $b");
 h45ok('XLSX date cells and account IDs fill the grid response',($j['entries'][0]['date']??'')==='2026-10-20'&&($j['entries'][0]['accountId']??0)===34&&($j['entries'][1]['accountId']??0)===105,json_encode($j));
 h45ok('XLSX totals balance',($j['totals']['balanced']??false)===true&&($j['totals']['difference']??'')==='0.00',json_encode($j['totals']??null));
 h45ok('markup is returned as data for textContent rendering',($j['entries'][0]['narration']??'')===$mark&&strpos(file_get_contents(__DIR__.'/../public/dist/js/pages/journals/functionalities.js'),'innerHTML')===false);
 $csv=tempnam(sys_get_temp_dir(),'t45').'.csv';$tmp[]=$csv;file_put_contents($csv,"date,account,debit,credit,description\n2026-10-20,equity,1,,foreign\n");
 [$c,$b,$j11]=h45('journals/validateimport',$s11,['date'=>'2026-10-20','file'=>new CURLFile($csv,'text/csv','journal.csv')]);
 h45ok('CSV upload accepted for congregation 11',$c===200&&($j11['success']??false),"$c $b");
 h45ok('other congregation account is empty with not-available reason',array_key_exists('accountId',$j11['entries'][0]??[])&&$j11['entries'][0]['accountId']===null&&stripos($j11['entries'][0]['reason']??'','not available to your congregation')!==false,json_encode($j11));
 [$c,$b,$j2]=h45('journals/validateimport',$s2,['date'=>'2026-10-20','file'=>new CURLFile($csv,'text/csv','journal.csv')]);
 h45ok('same CSV under congregation 2 matches its own account', $c===200&&($j2['entries'][0]['accountId']??0)===101,json_encode($j2));
 $txt=tempnam(sys_get_temp_dir(),'t45').'.txt';$tmp[]=$txt;file_put_contents($txt,"account,debit,credit\ncash at hand,1,\n");
 [$c,$b]=h45('journals/validateimport',$s11,['date'=>'2026-10-20','file'=>new CURLFile($txt,'text/plain','journal.txt')]);h45ok('unsupported file type has a clear refusal',$c===415&&stripos($b,'Only .csv and .xlsx')!==false,"$c $b");
 $large=tempnam(sys_get_temp_dir(),'t45').'.csv';$tmp[]=$large;file_put_contents($large,str_repeat('x',JournalImport::MAX_BYTES+1));
 [$c,$b]=h45('journals/validateimport',$s11,['date'=>'2026-10-20','file'=>new CURLFile($large,'text/csv','large.csv')]);h45ok('file-size cap has a clear refusal',$c===413&&stripos($b,'2 MiB')!==false,"$c $b");
 $many=tempnam(sys_get_temp_dir(),'t45').'.csv';$tmp[]=$many;$contents="date,account,debit,credit,description\n".str_repeat("2026-10-20,cash at hand,1,,row\n",JournalImport::MAX_ROWS+1);file_put_contents($many,$contents);
 [$c,$b]=h45('journals/validateimport',$s11,['date'=>'2026-10-20','file'=>new CURLFile($many,'text/csv','many.csv')]);h45ok('row cap has a clear refusal',$c===413&&stripos($b,'more than '.JournalImport::MAX_ROWS)!==false,"$c $b");
 // Final save is JSON rather than multipart.
 $ch=curl_init($base.'/journals/createupdate');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>'PHPSESSID='.$s11,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['date'=>'2026-10-20','isEdit'=>false,'entries'=>[['accountid'=>101,'debit'=>'1','credit'=>'','desc'=>'forged'],['accountid'=>34,'debit'=>'','credit'=>'1','desc'=>'forged']]]),CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);$raw=(string)curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);$body=substr($raw,$hs);
 h45ok('forged account ID in final save is refused',$code===422&&stripos($body,'journal account list')!==false,"$code $body");
 h45ok('HTTP bodies contain no PHP warnings',!$phpErrors,implode(', ',$phpErrors));
}finally{foreach($tmp as $f)if(is_file($f))@unlink($f);foreach($sessions as $sid)@unlink(session_save_path().DIRECTORY_SEPARATOR.'sess_'.$sid);}
if(is_file($apache)){$tail=(string)file_get_contents($apache,false,null,$off);h45ok('Apache emitted no T45 PHP warning',!preg_match('/PHP (Warning|Notice|Fatal error|Deprecated|Parse error).*journals/i',$tail),$tail);}
echo "\n$pass passed, $fail failed\n";exit($fail?1:0);
