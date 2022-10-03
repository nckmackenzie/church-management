<?php
class Groupbudgets extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['userType'] == 3 || $_SESSION['userType'] == 4) {
            redirect('');
        }
        else{
            $this->budgetModel = $this->model('Groupbudget');
        }
    }
    public function index()
    {
        $budgets = $this->budgetModel->index();
        $data = ['budgets' => $budgets];
        $this->view('groupbudgets/index',$data);
    }
    public function add()
    {
        $years = $this->budgetModel->getFiscalYears();
        $accounts = $this->budgetModel->getAccounts();
        $groups = $this->budgetModel->getGroups();
        $data = [
            'years' => $years,
            'groups' => $groups,
            'accounts' => $accounts
        ];
        $this->view('groupbudgets/add',$data);
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
            $this->view('groupbudgets/edit',$data);
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
                'year' => trim($_POST['year']),
                'groupname' => trim($_POST['groupname'])
            ];
            if (!empty($data['id'])) {
                if ($this->budgetModel->delete($data)) {
                    flash('budget_msg','Deleted Successfully!');
                    redirect('groupbudgets');
                }
            }
        }
    }
}
