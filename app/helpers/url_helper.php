<?php
//url redirect
function redirect($page){
    header('location: ' . URLROOT . '/' . $page);
}
function encrypt($string){
    $newVal = ($string * 123456789);
    $key = 'MAL_979805';
    $result = '';
    $test = '';
    for ($i=0; $i <strlen($newVal) ; $i++) { 
        $char = substr($newVal, $i,1);
        $keychar = substr($key, ($i % strlen($key))-1,1);
        $char = chr(ord($char)+ord($keychar));
        //$test[$char]=ord($char)+ord($keychar);
        $result.=$char;
    }
    return urlencode(base64_encode($result));
}

function decrypt($string){
    $key = 'MAL_979805';
    $result = '';
    $string = base64_decode(urldecode($string));
    for ($i=0; $i < strlen($string) ; $i++) { 
        $char = substr($string, $i,1);
        $keychar = substr($key, ($i % strlen($key))-1,1);
        $char = chr(ord($char)-ord($keychar));
        $result.=$char;
    }
    $newVal = $result/123456789;
    return (int)$newVal;
}
function selectdCheck($value1,$value2)
   {
     if ($value1 == $value2) 
     {
      echo 'selected="selected"';
     } else 
     {
       echo '';
     }

     return;
}
function selectdCheckEdit($data,$fromdb,$value)
{
    if (!empty($data)) {
        if ($data == $value) {
           echo 'selected="selected"';
        }
        else{
            echo '';
        }
    }
    else{
         if ($fromdb == $value) {
            echo 'selected="selected"';
         }
         else{
            echo '';
         }
     }
     return;
}
function saveLog($connection,$activity){
    $currdate = date("Y/m/d");
    $sql=$connection->prepare("INSERT INTO tbllogs (userId,activity,activityDate,congregationId) VALUES(:user,:act,:actdate,:congid)");
    $sql->bindParam(':user',$_SESSION['userId']);
    $sql->bindParam(':act',$activity);
    $sql->bindParam(':actdate',$currdate);
    $sql->bindParam(':congid',$_SESSION['congId']);
    $sql->execute();
}
function getRecordExists($sql,$connection,$param)
{
    $stmt = $connection->prepare($sql);
    $stmt->execute([$param]);
    return $stmt->fetchColumn();
}
function saveToLedger($connection,$date,$account,$debit,$credit,$narration,$accountId,$type,$tid,$cong){
    $sql = "INSERT INTO tblledger (transactionDate,account,debit,credit,narration,accountId,
            transactionType,transactionId,congregationId) VALUES(?,?,?,?,?,?,?,?,?)";
    $stmt = $connection->prepare($sql);
    $stmt->execute([$date,$account,$debit,$credit,$narration,$accountId,$type,$tid,$cong]);
}
function saveToBanking($connection,$bank,$date,$debit,$credit,$method,$reference,$type,$tid,$cong){
    $sql = 'INSERT INTO tblbankpostings (bankId,transactionDate,debit,credit,transactionMethod,reference
            ,transactionType,transactionId,congregationId) VALUES(?,?,?,?,?,?,?,?,?)';
    $stmt =$connection->prepare($sql);
    $stmt->execute([$bank,$date,$debit,$credit,$method,$reference,$type,$tid,$cong]);
}

function deleteLedgerBanking($connection,$type,$tid){
    $sql = 'DELETE FROM tblledger WHERE (transactionType=?) AND (transactionId=?)';
    $stmt=$connection->prepare($sql);
    if ($stmt->execute([$type,$tid])) {
        $sql = 'DELETE FROM tblbankpostings WHERE (transactionType=?) AND (transactionId=?)';
        $stmt=$connection->prepare($sql);
        $stmt->execute([$type,$tid]);
    }
}
function generateId($sql,$connection,$param)
{
    $stmt = $connection->prepare($sql);
    $stmt->execute([$param]);
    $result = $stmt->fetchColumn();
    if ($result == 0) {
        return 1;
    }
    else{
        return $result + 1;
    }
}
function paymentMethods($connection)
{
    $stmt = $connection->prepare('SELECT * FROM tblpaymentmethods');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
function getBanks($connection,$param)
{
    $stmt = $connection->prepare('SELECT ID,UCASE(accountType) AS accountType
                                  FROM tblaccounttypes WHERE (isBank=1) AND (deleted=0)
                                       AND (congregationId=?) ORDER BY accountType');
    $stmt->execute([$param]);
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
function getBanksAll($connection)
{
    $stmt = $connection->prepare("SELECT a.ID,
                                         UCASE(CONCAT(accountType,'-',c.CongregationName)) AS accountType
                                         FROM tblaccounttypes a inner join tblcongregation c on
                                         a.congregationId = c.ID
                                  WHERE (isBank=1) AND (a.deleted=0)       
                                  ORDER BY accountType");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}
function getYearId($connection,$date){
    $result = '';
    $sql = "SELECT ID FROM tblfiscalyears WHERE ? BETWEEN startDate AND endDate AND deleted=0";
    $stmt=$connection->prepare($sql);
    $stmt->execute([$date]);
    $result = $stmt->fetchColumn();
    return $result;
}
function getLastId($connection,$table){
    $id = '';
    $sql = "SELECT COUNT(ID) FROM $table";
    $stmt=$connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchColumn();
    if ($result == 0) {
        $id = 1;
    }
    else {
        $sql = "SELECT ID FROM $table ORDER BY ID DESC LIMIT 1";
        $stmt=$connection->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchColumn();
        $id = $result + 1;
    }
    return $id;
}
function encryptId($id)
{
    $encryptedId = $id * (2020 * 7);
    return (int)$encryptedId;
}
function decryptId($encryptedId)
{
    $id = $encryptedId / (2020 * 7);
    return (int)$id;
}
function checkExistsMod($connection,$sql,$param)
{
    $stmt = $connection->prepare($sql);
    $stmt->execute($param);
    return $stmt->fetchColumn();
}
function checkedCheckEdit($data,$fromdb,$value)
{
    if (!empty($data)) {
        if ($data == $value) {
           echo 'checked';
        }
        else{
            echo '';
        }
    }
    else{
         if ($fromdb == $value) {
            echo 'checked';
         }
         else{
            echo '';
         }
     }
     return;
}
function calculateVat($type,$net){
    if (intval($type) == 1) {
        return array($net,$_SESSION['zero'],$net);
    }
    elseif (intval($type) == 2) {
        $vat = (0.16 * floatval($net)) / 1.16;
        $exc = $net - $vat;
        return array($exc,$vat,$net);
    }
    elseif (intval($type) == 3) {
        $vat = floatval($net) * 0.16;
        $inc = floatval($net) + floatval($vat);
        return array($net,$vat,$inc);
    }
}
function getUserAccess($con,$userid,$form,$congnav){
    $sql = 'SELECT COUNT(u.ID) as totalCount 
            FROM   tbluserrights u inner join tblforms f on u.FormId = f.ID 
            WHERE  (UserId = ?) AND (f.ParishNav = ?) AND (f.FormName=?)';
    $stmt = $con->prepare($sql);
    $stmt->execute([$userid,$congnav,$form]);
    return $stmt->fetchColumn();
}
//convert value from string to boolean
function converttobool($val){
    $converted = filter_var($val, FILTER_VALIDATE_BOOLEAN);
    return $converted;
}
//Get value from Database
function getdbvalue($con,$sql,$arr){
    $stmt = $con->prepare($sql);
    $stmt->execute($arr);
    return $stmt->fetchColumn();
}
//format id to 4 digits
function formatStringId($val){
    switch (strlen($val)) {
        case 1:
            return '000'.$val;
            break;
        case 2:
            return '00'.$val;
            break;
        case 3:
            return '0'.$val;
            break;
        case 2:
            return $val;
            break;
        default:
            return $val;
            break;
    } 
}

//add css classes for data validation
function inputvalidation($data,$err,$touch){
    if (!empty($err)){
        return 'is-invalid';
    }elseif (empty($err) && !empty($data) && $touch === true){ 
        return 'is-valid';
    }
}

//disable other congregations edit
function checkcenter($cong){
    if(intval($_SESSION['congId']) !== intval($cong)){
        redirect('users/deniedaccess');
        exit();
    }
}

//get unique no from database;
function getuniqueid($con,$field,$table,$cid,$bycenter = true){
    $sql = "SELECT COUNT(*) FROM $table WHERE Deleted = 0";
    if($bycenter){
        $sql .= " AND (congregationId = :cid)";
    }
    $stmt = $con->prepare($sql);
    if($bycenter){
        $stmt->bindValue(':cid',$cid);
    }
    $stmt->execute();
    if((int)$stmt->fetchColumn() === 0){
        return 1;
    }else{
        $sql = "SELECT 
                    $field 
                FROM 
                    $table 
                WHERE 
                    deleted = 0";
        if($bycenter){
            $sql .= " AND (congregationId = :cid)";
        }
        $sql .=" ORDER BY $field DESC";
        $stmt = $con->prepare($sql);
        if($bycenter){
            $stmt->bindValue(':cid',$cid);
        }
        if($stmt->execute()){
            return (int)$stmt->fetchColumn() + 1;
        }else{
            return 0;
        }
    }
}