<?php
class Budgets extends Controller{
    public function __construct()
    {
        $this->budgetModel = $this->model('Budget');
    }
    public function index()
    {
        
        $budgets = $this->budgetModel->index();
        $data = ['budgets' => $budgets];
        $this->view('church-budgets/index',$data);
    }
}