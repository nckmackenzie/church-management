<?php

function GetMenu($con)
{
    $sql = 'SELECT DISTINCT f.Module 
            FROM   tbluserrights r INNER JOIN tblforms f on r.FormId = f.ID
            WHERE  (r.UserId = :usid) AND (ParishNav = :pnav)';
    $stmt = $con->prepare($sql);
    $stmt->bindValue(':usid',$_SESSION['userId']);
    $stmt->bindValue(':pnav',$_SESSION['isParish']);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_OBJ);
    $modules = array();
    foreach($results as $result) {
        array_push($modules,$result->Module);
    }
    return $modules;
}
function GetMembersMenu($con,$menu)
{
    $sql = 'SELECT f.FormName,
                   f.Path
            FROM   tbluserrights r inner join tblforms f on r.FormId = f.ID
            WHERE  r.UserId = :usid AND (f.Module = :menu)
            ORDER BY f.MenuOrder';
    $stmt = $con->prepare($sql);
    $stmt->bindValue(':usid',$_SESSION['userId']);
    $stmt->bindValue(':menu',$menu);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_OBJ);
}