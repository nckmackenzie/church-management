<?php
class Suppliers extends Controller 
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'suppliers');
        $this->suppliermodel = $this->model('Supplier');
    }

    //index
    public function index()
    {
        $data = [
            'suppliers' => $this->suppliermodel->GetSuppliers()
        ];
        $this->view('suppliers/index',$data);
        exit;
    }
}