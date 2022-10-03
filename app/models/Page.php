<?php
class Page {

    private $db;

    public function __construct()
    {
         $this->db = new Database;
    }

    public function getCongregation()
    {
        $this->db->query('SELECT ID,UCASE(CongregationName) AS CongregationName FROM tblcongregation
                          WHERE (deleted=0)');
        return $this->db->resultSet();
    }
}