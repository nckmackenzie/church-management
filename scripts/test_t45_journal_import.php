<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
chdir(__DIR__.'/../app');require 'boostrap.php';require_once 'models/Journal.php';
$pdo=(new Database)->pdo();$_SESSION=['congId'=>11,'userId'=>64,'isParish'=>0];$pass=0;$fail=0;$ids=[];
function t45ok($n,$v,$x=''){global $pass,$fail;if($v){$pass++;echo "PASS $n\n";}else{$fail++;echo "FAIL $n".($x!==''?": $x":'')."\n";}}
function t45ins($sql,$p=[]){global $pdo,$ids;$s=$pdo->prepare($sql);$s->execute($p);$id=(int)$pdo->lastInsertId();$ids[]=$id;return $id;}
function t45row($row,$date='2026-10-20'){return array_merge(['_row'=>2,'date'=>$date,'account'=>'cash at hand','debit'=>'10.00','credit'=>'','description'=>'test'],$row);}
try{
 $exact=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES('zz t45 exact',26,2,1,1,0,11,0)");
 $dup1=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES('zz t45 duplicate',26,2,1,1,0,0,0)");
 $dup2=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES(' zz  t45   duplicate ',27,2,1,1,0,11,0)");
 $foreign=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES('zz t45 foreign',26,2,1,1,0,2,0)");
 $inactive=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES('zz t45 inactive',26,2,1,0,0,11,0)");
 $deleted=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES('zz t45 deleted',26,2,1,1,1,11,0)");
 $report=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES('zz t45 reporting',26,2,0,1,0,11,0)");
 $child=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES('zz t45 reporting child',?,2,1,1,0,11,0)",[$report]);
 $root=t45ins("INSERT INTO tblaccounttypes(accountType,parentId,accountTypeId,isSubCategory,active,deleted,congregationId,isBank) VALUES('zz t45 root',0,NULL,0,1,0,0,0)");
 $m=new Journal;
 $v=$m->validateImportRows([t45row(['account'=>'zz t45 exact'])]);t45ok('exact visible name matches',$v['entries'][0]['accountId']===$exact,json_encode($v));
 $v=$m->validateImportRows([t45row(['account'=>'  ZZ   T45  EXACT  '])]);t45ok('case and repeated spaces are ignored',$v['entries'][0]['accountId']===$exact);
 $v=$m->validateImportRows([t45row(['account'=>'zz t45 foreign'])]);t45ok('other-congregation-only name is not matched',$v['entries'][0]['accountId']===null&&strpos($v['entries'][0]['reason'],'not available to your congregation')!==false,$v['entries'][0]['reason']);
 $v=$m->validateImportRows([t45row(['account'=>'zz t45 duplicate'])]);t45ok('two visible normalized names are ambiguous',$v['entries'][0]['accountId']===null&&strpos($v['entries'][0]['reason'],'matches 2 accounts')!==false,$v['entries'][0]['reason']);
 foreach(['inactive'=>'inactive','deleted'=>'deleted','reporting'=>'reporting account','root'=>'root account'] as $name=>$reason){$v=$m->validateImportRows([t45row(['account'=>'zz t45 '.$name])]);t45ok("$name account reason",$v['entries'][0]['accountId']===null&&stripos($v['entries'][0]['reason'],$reason)!==false,$v['entries'][0]['reason']);}
 $amounts=[['0','','greater than zero'],['-1','','greater than zero'],['1.001','','2 decimal'],['word','','greater than zero'],['1','1','exactly one'],['','','exactly one']];
 foreach($amounts as $i=>$a){$v=$m->validateImportRows([t45row(['account'=>'zz t45 exact','debit'=>$a[0],'credit'=>$a[1]])]);t45ok('invalid amount/side case '.($i+1),stripos($v['entries'][0]['reason'],$a[2])!==false,$v['entries'][0]['reason']);}
 $v=$m->validateImportRows([t45row(['account'=>'zz t45 exact','date'=>'31/02/2026'])]);t45ok('bad date rejected',strpos($v['entries'][0]['reason'],'invalid')!==false,$v['entries'][0]['reason']);
 $v=$m->validateImportRows([t45row(['account'=>'zz t45 exact','date'=>'2024-01-15'])]);t45ok('closed year shown early',stripos($v['entries'][0]['reason'],'closed')!==false,$v['entries'][0]['reason']);
 $v=$m->validateImportRows([t45row(['account'=>'zz t45 exact','date'=>'1900-01-01'])]);t45ok('date with no financial year shown early',stripos($v['entries'][0]['reason'],'No financial year')!==false,$v['entries'][0]['reason']);
 $v=$m->validateImportRows([['_row'=>2,'date'=>'','account'=>'','debit'=>'','credit'=>'','description'=>''],t45row(['account'=>'zz t45 exact'])]);t45ok('blank rows ignored',count($v['entries'])===1);
 try{$m->validateImportRows(array_fill(0,JournalImport::MAX_ROWS+1,t45row([])));t45ok('row cap enforced',false);}catch(LengthException $e){t45ok('row cap enforced',true);}
 $v=$m->validateImportRows([t45row(['account'=>'zz t45 exact','debit'=>'10.25']),t45row(['account'=>'cash at hand','debit'=>'','credit'=>'10.25'])]);t45ok('balanced totals are exact',$v['totals']['balanced']&&$v['totals']['difference']==='0.00',json_encode($v['totals']));
 $bad=$m->createupdate(['isedit'=>false,'journalno'=>0,'date'=>'2026-10-20','entries'=>[(object)['accountid'=>0,'debit'=>'10','credit'=>'','desc'=>'x'],(object)['accountid'=>34,'debit'=>'','credit'=>'10','desc'=>'x']]]);t45ok('save refuses a line without an account',!$bad['success']&&stripos(implode(' ',$bad['errors']),'choose an account')!==false,json_encode($bad));
 $bad=$m->createupdate(['isedit'=>false,'journalno'=>0,'date'=>'2026-10-20','entries'=>[(object)['accountid'=>34,'debit'=>'10','credit'=>'','desc'=>'x'],(object)['accountid'=>$exact,'debit'=>'','credit'=>'9','desc'=>'x']]]);t45ok('save refuses unbalanced lines',!$bad['success']&&stripos(implode(' ',$bad['errors']),'do not equal')!==false,json_encode($bad));
 $bad=$m->createupdate(['isedit'=>false,'journalno'=>0,'date'=>'2026-10-20','entries'=>[(object)['accountid'=>$foreign,'debit'=>'10','credit'=>'','desc'=>'x'],(object)['accountid'=>34,'debit'=>'','credit'=>'10','desc'=>'x']]]);t45ok('save refuses forged outside-list account ID',!$bad['success']&&stripos(implode(' ',$bad['errors']),'journal account list')!==false,json_encode($bad));
}finally{
 if($ids){$pdo->exec('DELETE FROM tblaccounttypes WHERE ID IN ('.implode(',',array_reverse($ids)).')');}
}
echo "\n$pass passed, $fail failed\n";exit($fail?1:0);
