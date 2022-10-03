<?php
class Churchbudgets extends Controller{
    public function __construct()
    {
        $this->budgetModel = $this->model('Churchbudget');
    }
    public function index()
    {
        $budgets = $this->budgetModel->index();
        $data = ['budgets' => $budgets];
        $this->view('churchbudgets/index',$data);
    }
    public function add()
    {
        $years = $this->budgetModel->getFiscalYears();
        $accounts = $this->budgetModel->getAccounts();
        $data = [
            'years' => $years,
            'accounts' => $accounts
        ];
        $this->view('churchbudgets/add',$data);
    }
    public function import()
    {
        // $data = [
        //     'year' => trim($_POST['fiscalyear']),
        //     'file' => $_FILES['formfile']['name']
        // ];
        $year = trim($_POST['fiscalyear']);
        $file = $_FILES['formfile']['name'];
        if ($this->budgetModel->create($year,$file)) {
            flash('budget_msg','Budget Imported Successfully!');
            redirect('churchbudgets');
        }
        // print_r($data);
    }
    public function edit($id)
    {
        $header = $this->budgetModel->budgetHeader($id);
        $details = $this->budgetModel->budgetDetails($id);
        $data = [
            'header' => $header,
            'details' => $details
        ];
        if ($header->congregationId != $_SESSION['congId'] || $_SESSION['userId'] == 3 
            || $_SESSION['userId'] == 4) {
            redirect('mains');
        }else{
            $this->view('churchbudgets/edit',$data);
        }
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'id' => trim($_POST['id']),
                'amount' => trim($_POST['amount'])
            ];
            $this->budgetModel->update($data);
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = [
                'id' => trim($_POST['id']),
                'year' => trim($_POST['year'])
            ];
            if (!empty($data['id'])) {
                if ($this->budgetModel->delete($data)) {
                    flash('budget_msg','Deleted Successfully!');
                    redirect('churchbudgets');
                }
            }
        }
    }
}