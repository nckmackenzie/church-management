<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
$pdo=(new Database)->pdo();
$_SESSION=['congId'=>11,'userId'=>64];
$base=URLROOT;$pass=0;$fail=0;
function q811h($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC);}
function q($sql,$p=[]){return q811h($sql,$p);}
function chk811h($name,$ok,$extra=''){global $pass,$fail;if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name $extra\n";}}
$helper=__DIR__.'/dev_session_helper.php';if(!is_file($helper)){exit("scripts/dev_session_helper.php is missing\n");}require_once $helper;
function http811($method,$path,$sid,$body=null){global $base,$phpErrors;$ch=curl_init($base.'/'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>'PHPSESSID='.$sid,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>60]);if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($body??[]));}$raw=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);$body=substr((string)$raw,$hs);if(preg_match('~(<b>)?(Warning|Notice|Fatal error|Deprecated|Parse error)(</b>)?:~',$body,$m)){$phpErrors[]=$path.' -> '.$m[2];}return [$code,$body,substr((string)$raw,0,$hs)];}
function script811($html,$needle){return stripos($html,$needle)!==false;}

$admin11=mkSession(64,11);$admin2=mkSession(64,2);
$pages=[
 ['plans',''],['churchbudgets/add','dist/js/pages/budgets/lcc-budget.js'],['groupbudgets/add','dist/js/pages/budgets/group-budget.js'],
 ['banks','dist/js/pages/bank/balance.js'],['clearbankings','dist/js/pages/bankings/clearbankings-v2.js'],['clearbankings/modify','dist/js/pages/bankings/modify-cleared-v1.js'],
 ['contributions/add','dist/js/pages/contributions-v2.js'],['banktransactions/add','dist/js/pages/banktransactions/index-v1.js'],
 ['expenses/add','dist/js/pages/expenses/add-expense-v9.js'],['groupcollections/add','dist/js/pages/groupcollections/index-v2.js'],
 ['groupfunds/add','dist/js/pages/groupfunds/add-v5.js'],['journals','dist/js/pages/journals/index-v1.js'],
 ['members/transfer','dist/js/pages/members/transfers-v1.js'],['invoicereports','dist/js/pages/reports/invoices/invoicereports-v2.js'],
 ['trialbalance/drill','dist/js/pages/reports/is/trial-balance-v1.js'],['trialbalance','dist/js/pages/reports/is/trial-balance-v1.js'],['reports/balancesheet','']
];
$editableExpense=(int)$pdo->query('SELECT ID FROM tblexpenses WHERE congregationId=11 AND deleted=0 AND status=0 ORDER BY ID LIMIT 1')->fetchColumn();
$pendingFund=(int)$pdo->query('SELECT ID FROM tblfundrequisition WHERE CongregationId=11 AND Deleted=0 AND Status=0 ORDER BY ID LIMIT 1')->fetchColumn();
if($editableExpense){$pages[]=['expenses/edit/'.$editableExpense,'dist/js/pages/expenses/add-expense-v9.js'];}
if($pendingFund){$pages[]=['groupfunds/approve/'.$pendingFund,'dist/js/pages/groupfunds/approve-v6.js'];}
$created=[];
try{
 foreach($pages as [$path,$script]){$r=http811('GET',$path,$admin11);chk811h("render $path",$r[0]===200,"status {$r[0]}");if($script){chk811h("$path loads current script",script811($r[1],$script));$asset=http811('GET',substr($script,0),$admin11);chk811h("current script exists: $script",$asset[0]===200,"status {$asset[0]}");}}

 $s=$pdo->prepare('INSERT INTO tblaccounttypes(accountType,accountTypeId,parentId,isBank,isSubCategory,deleted,active,congregationId,forGroup) VALUES(\'zz t811 http foreign\',2,26,0,1,0,1,2,1)');$s->execute();$foreign=(int)$pdo->lastInsertId();$created['account']=$foreign;
 $year=(int)$pdo->query('SELECT ID FROM tblfiscalyears ORDER BY ID LIMIT 1')->fetchColumn();$hid=(int)$pdo->query('SELECT IFNULL(MAX(ID),0)+1 FROM tblchurchbudget_header')->fetchColumn();
 $pdo->prepare('INSERT INTO tblchurchbudget_header(ID,budgetName,yearId,congregationId) VALUES(?,?,?,11)')->execute([$hid,'zz t811 http',$year]);$created['header']=$hid;
 $valid=(int)$pdo->query('SELECT ID FROM tblaccounttypes WHERE deleted=0 AND active=1 AND isBank=0 AND isSubCategory=1 AND accountTypeId IN(1,2) AND congregationId IN(0,11) ORDER BY congregationId DESC,ID LIMIT 1')->fetchColumn();
 $pdo->prepare('INSERT INTO tblchurchbudget_details(ID,accountId,amount) VALUES(?,?,1)')->execute([$hid,$valid]);
 $before=(int)$pdo->query("SELECT accountId FROM tblchurchbudget_details WHERE ID=$hid")->fetchColumn();
 $post=['isedit'=>1,'id'=>$hid,'yeartext'=>'zz','accountsid'=>[$foreign],'accountsname'=>['forged'],'amounts'=>[2]];
 $r=http811('POST','churchbudgets/createupdate',$admin11,$post);
 chk811h('real POST refuses another-congregation account with field message',$r[0]===200&&stripos($r[1],'active income or expense posting account')!==false&&(int)$pdo->query("SELECT accountId FROM tblchurchbudget_details WHERE ID=$hid")->fetchColumn()===$before,"status {$r[0]}");
 $post['accountsid']=[$valid];
 $r=http811('POST','churchbudgets/createupdate',$admin2,$post);
 chk811h('real POST from another congregation cannot edit the budget',$r[0]===200&&(int)$pdo->query("SELECT accountId FROM tblchurchbudget_details WHERE ID=$hid")->fetchColumn()===$before,"status {$r[0]}");

 $expense=(int)$pdo->query('SELECT ID FROM tblexpenses WHERE CongregationId=11 AND deleted=0 ORDER BY ID LIMIT 1')->fetchColumn();
 $r=http811('GET','journals/getJournalDetails?id='.$expense.'&type=2',$admin11);chk811h('journal details type 2 remains available',$expense>0&&$r[0]===200,"expense $expense status {$r[0]}");
 $r=http811('GET','journals/getJournalDetails?id=1&type=1',$admin11);$j=json_decode($r[1],true);chk811h('journal details rejects another type with 400 JSON',$r[0]===400&&is_array($j)&&($j['success']??null)===false,"status {$r[0]}");
 chk811h('rendered pages emitted no PHP warnings',$phpErrors===[],implode(', ',$phpErrors));
}catch(Throwable $e){$fail++;echo 'FAIL unexpected exception '.$e->getMessage()."\n";}
finally{
 if(isset($created['header'])){$pdo->prepare('DELETE FROM tblchurchbudget_details WHERE ID=?')->execute([$created['header']]);$pdo->prepare('DELETE FROM tblchurchbudget_header WHERE ID=?')->execute([$created['header']]);}
 if(isset($created['account'])){$pdo->prepare('DELETE FROM tblaccounttypes WHERE ID=?')->execute([$created['account']]);}
 foreach($sessions as $sid){$f=session_save_path().DIRECTORY_SEPARATOR.'sess_'.$sid;if(is_file($f)){@unlink($f);}}
}
echo "\n$pass passed, $fail failed\n";exit($fail?1:0);
