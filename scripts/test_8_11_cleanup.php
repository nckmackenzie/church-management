<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
chdir('E:/xampp/htdocs/cms/app');
require 'boostrap.php';
require_once 'models/Churchbudget.php';
require_once 'models/Groupbudget.php';
require_once 'models/Plan.php';
require_once 'models/Clearbanking.php';
require_once 'models/Journal.php';
require_once 'models/Bank.php';

$pdo=(new Database)->pdo();
$uid=(int)$pdo->query('SELECT ID FROM tblusers ORDER BY ID LIMIT 1')->fetchColumn();
$_SESSION=['userId'=>$uid,'userType'=>1,'congId'=>11,'isParish'=>0];
$pass=0;$fail=0;$ids=[];
function chk811($name,$ok,$extra=''){global $pass,$fail;if($ok){$pass++;echo "PASS $name\n";}else{$fail++;echo "FAIL $name $extra\n";}}
function ids811($rows){return array_map(fn($r)=>(int)$r->ID,$rows);}
function one811($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return $s->fetchColumn();}
function exec811($sql,$p=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($p);return (int)$pdo->lastInsertId();}
function account811($name,$o=[]){global $pdo,$ids;$v=$o+['accountTypeId'=>2,'parentId'=>26,'isBank'=>0,'isSubCategory'=>1,'deleted'=>0,'active'=>1,'congregationId'=>11,'forGroup'=>1];$s=$pdo->prepare('INSERT INTO tblaccounttypes(accountType,accountTypeId,parentId,isBank,isSubCategory,deleted,active,congregationId,forGroup) VALUES(?,?,?,?,?,?,?,?,?)');$s->execute([$name,$v['accountTypeId'],$v['parentId'],$v['isBank'],$v['isSubCategory'],$v['deleted'],$v['active'],$v['congregationId'],$v['forGroup']]);$id=(int)$pdo->lastInsertId();$ids['accounts'][]=$id;return $id;}

$year=(int)$pdo->query('SELECT ID FROM tblfiscalyears ORDER BY ID LIMIT 1')->fetchColumn();
$group=(int)$pdo->query('SELECT ID FROM tblgroups WHERE congregationId=11 ORDER BY ID LIMIT 1')->fetchColumn();
try{
    $valid=account811('zz t811 valid');
    $income=account811('zz t811 income',['accountTypeId'=>1,'parentId'=>9]);
    $inactive=account811('zz t811 inactive',['active'=>0]);
    $deleted=account811('zz t811 deleted',['deleted'=>1]);
    $reporting=account811('zz t811 reporting',['isSubCategory'=>0]);
    $bank=account811('zz t811 bank',['accountTypeId'=>3,'parentId'=>33,'isBank'=>1]);
    $wrong=account811('zz t811 wrong',['accountTypeId'=>3,'parentId'=>23]);
    $foreign=account811('zz t811 foreign',['congregationId'=>2]);
    $notGroup=account811('zz t811 nongroup',['forGroup'=>0]);

    $c=new Churchbudget;$g=new Groupbudget;$p=new Plan;
    $ci=ids811($c->getAccounts());$gi=ids811($g->getAccounts());$pi=ids811($p->GetAccounts());
    chk811('church budget list contains valid income/expense posting accounts',in_array($valid,$ci,true)&&in_array($income,$ci,true));
    chk811('church budget list excludes forged/foreign/deleted/inactive/reporting/bank/wrong type',!array_intersect([$foreign,$deleted,$inactive,$reporting,$bank,$wrong],$ci));
    chk811('plan list applies the same account rules',in_array($valid,$pi,true)&&!array_intersect([$foreign,$deleted,$inactive,$reporting,$bank,$wrong],$pi));
    chk811('group list additionally excludes forGroup=0',in_array($valid,$gi,true)&&!in_array($notGroup,$gi,true));
    foreach(['forged'=>99999999,'other congregation'=>$foreign,'deleted'=>$deleted,'inactive'=>$inactive,'reporting'=>$reporting,'bank'=>$bank,'wrong type'=>$wrong] as $label=>$id){
        chk811("church budget server refuses $label",$c->accountProblem([$id])!==null);
        chk811("plan server refuses $label",$p->accountProblem($id)!==null);
    }
    chk811('group budget server refuses forGroup=0',$g->accountProblem([$notGroup])!==null);

    $ch=(int)one811('SELECT IFNULL(MAX(ID),0)+1 FROM tblchurchbudget_header');$ids['church'][]=$ch;
    exec811('INSERT INTO tblchurchbudget_header(ID,budgetName,yearId,congregationId) VALUES(?,?,?,11)',[$ch,'zz t811',$year]);
    exec811('INSERT INTO tblchurchbudget_details(ID,accountId,amount) VALUES(?,?,1)',[$ch,$inactive]);
    chk811('church budget unchanged inactive legacy line is accepted',$c->Update(['id'=>$ch,'accountsid'=>[$inactive],'amounts'=>[2]]));
    chk811('church budget changed invalid legacy line is refused',!$c->Update(['id'=>$ch,'accountsid'=>[$deleted],'amounts'=>[2]]));

    exec811('INSERT INTO tblgroupbudget_header(groupId,fiscalYearId,congregationId) VALUES(?,?,11)',[$group,$year]);$gh=(int)$pdo->lastInsertId();$ids['group'][]=$gh;
    exec811('INSERT INTO tblgroupbudget_details(ID,accountId,amount) VALUES(?,?,1)',[$gh,$inactive]);
    chk811('group budget unchanged inactive legacy line is accepted',$g->Update(['id'=>$gh,'accountsid'=>[$inactive],'amounts'=>[2]]));
    chk811('group budget changed invalid line is refused',!$g->Update(['id'=>$gh,'accountsid'=>[$notGroup],'amounts'=>[2]]));

    $planData=['level'=>1,'year'=>$year,'planname'=>'zz t811','theme'=>1,'meetingdate'=>'','activity'=>1,'reason'=>'test','costestimate'=>1,'fromdate'=>'2026-01-01','todate'=>'2026-01-02','actualdate'=>'','account'=>$foreign,'office'=>1,'officeother'=>'','collaboratorName'=>'','results'=>'','actualcost'=>'','evidence'=>'','remarks'=>'','filename'=>'','status'=>0];
    chk811('plan create refuses a foreign account',!$p->create($planData));
    exec811('INSERT INTO tblworkplan(`Level`,FiscalYearId,WorkPlanName,Theme,Activty,Reason,EstimatedCost,FromDate,ToDate,AccountId,OfficeResponsible,WorkPlanStatus,CongregationId) VALUES(1,?,?,?,?,1,1,\'2026-01-01\',\'2026-01-02\',?,1,0,11)',[$year,'zz t811 legacy',1,1,$inactive]);$wp=(int)$pdo->lastInsertId();$ids['plans'][]=$wp;
    $planData['id']=$wp;$planData['account']=$inactive;$planData['planname']='zz t811 legacy';
    chk811('plan unchanged inactive legacy account is accepted',$p->update($planData));
    $planData['account']=$deleted;chk811('plan changed invalid account is refused',!$p->update($planData));

    $activeBank=account811('zz t811 active bank',['accountTypeId'=>3,'parentId'=>33,'isBank'=>1]);
    $inactiveWith=account811('zz t811 inactive with rows',['accountTypeId'=>3,'parentId'=>33,'isBank'=>1,'active'=>0]);
    $inactiveWithout=account811('zz t811 inactive without',['accountTypeId'=>3,'parentId'=>33,'isBank'=>1,'active'=>0]);
    $foreignBank=account811('zz t811 foreign bank',['accountTypeId'=>3,'parentId'=>33,'isBank'=>1,'active'=>0,'congregationId'=>2]);
    $method=(int)$pdo->query('SELECT ID FROM tbltransactionmethods ORDER BY ID LIMIT 1')->fetchColumn();
    exec811('INSERT INTO tblbankpostings(bankId,transactionDate,debit,credit,transactionMethod,transactionType,transactionId,congregationId,deleted) VALUES(?,CURDATE(),1,0,?,1,987654,11,0)',[$inactiveWith,$method]);$ids['bankpostings'][]=(int)$pdo->lastInsertId();
    exec811('INSERT INTO tblbankpostings(bankId,transactionDate,debit,credit,transactionMethod,transactionType,transactionId,congregationId,deleted) VALUES(?,CURDATE(),1,0,?,1,987655,2,0)',[$foreignBank,$method]);$ids['bankpostings'][]=(int)$pdo->lastInsertId();
    $banks=ids811((new Clearbanking)->getBanks());
    chk811('Clear Banking lists active and inactive-with-rows banks',in_array($activeBank,$banks,true)&&in_array($inactiveWith,$banks,true));
    chk811('Clear Banking omits inactive-without-rows and foreign banks',!in_array($inactiveWithout,$banks,true)&&!in_array($foreignBank,$banks,true));

    chk811('Journal details refuses non-expense type',(new Journal)->getJournalDetails(['id'=>1,'type'=>1])===null);
    exec811('INSERT INTO tblbanksubaccounts(AccountName,BankId,AccountId,GroupDistrict,Deleted) VALUES(?,?,?,\'Group\',0)',['zz t811 sub',$activeBank,$valid]);$sub=(int)$pdo->lastInsertId();$ids['subs'][]=$sub;
    $grouped=(new Bank)->getSubaccountsByBank();
    chk811('Bank subaccounts are grouped under their bank',isset($grouped[$activeBank])&&in_array($sub,array_map(fn($r)=>(int)$r->ID,$grouped[$activeBank]),true));
}catch(Throwable $e){$fail++;echo 'FAIL unexpected exception '.$e->getMessage()."\n";}
finally{
    foreach(array_reverse($ids['subs']??[]) as $id){exec811('DELETE FROM tblbanksubaccounts WHERE ID=?',[$id]);}
    foreach(array_reverse($ids['bankpostings']??[]) as $id){exec811('DELETE FROM tblbankpostings WHERE ID=?',[$id]);}
    foreach(array_reverse($ids['plans']??[]) as $id){exec811('DELETE FROM tblworkplan WHERE ID=?',[$id]);}
    foreach(array_reverse($ids['group']??[]) as $id){exec811('DELETE FROM tblgroupbudget_details WHERE ID=?',[$id]);exec811('DELETE FROM tblgroupbudget_header WHERE ID=?',[$id]);}
    foreach(array_reverse($ids['church']??[]) as $id){exec811('DELETE FROM tblchurchbudget_details WHERE ID=?',[$id]);exec811('DELETE FROM tblchurchbudget_header WHERE ID=?',[$id]);}
    foreach(array_reverse($ids['accounts']??[]) as $id){exec811('DELETE FROM tblaccounttypes WHERE ID=?',[$id]);}
}
echo "\n$pass passed, $fail failed\n";
exit($fail?1:0);
