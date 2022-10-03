<?php 
class Budget {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    public function CheckRights($form)
    {
        if (getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0) {
            return true;
        }else{
            return false;
        }
    }
    public function index()
    {
        $this->db->query('SELECT DISTINCT h.ID,
                                          ucase(f.yearName) as yearName,
                                          (SELECT IFNULL(SUM(amount),0) as amount from tblchurchbudget_details where ID=h.ID) AS BudgetAmount
                          FROM tblchurchbudget_header h INNER join tblchurchbudget_details d on 
                                          h.ID=d.ID inner join tblfiscalyears f on h.yearId=f.ID
                          WHERE (h.congregationId=:id)');
        $this->db->bind(':id',$_SESSION['congId']);
        return $this->db->resultSet();
    }
}