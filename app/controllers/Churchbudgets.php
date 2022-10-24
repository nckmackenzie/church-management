<?php
class Churchbudgets extends Controller{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'church budget');
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
            'title' => 'Add budget',
            'years' => $years,
            'accounts' => $accounts,
            'isedit' => false,
            'id' => '',
            'yeartext' => '',
            'year' => '',
            'table' => [],
            'errmsg' => '',
        ];
        foreach($data['accounts'] as $account){
            array_push($data['table'],[
                'aid' => $account->ID,
                'name' => strtoupper($account->accountType),
                'amount' => ''
            ]);
        }
        $this->view('churchbudgets/add',$data);
        exit;
    }
    public function checkyear()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'year' => isset($_GET['year']) ? trim($_GET['year']) : null,
                'id' => !empty($_GET['id']) ? trim($_GET['id'])  : '',
            ];

           if(is_null($data['year'])) exit;

           $count = $this->budgetModel->CheckYear($data);

           echo json_encode($count);
 
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'title' => converttobool($_POST['isedit']) ? 'Edit budget' : 'Add budget',
                'years' => $this->budgetModel->getFiscalYears(),
                'isedit' => converttobool($_POST['isedit']),
                'year' => !converttobool($_POST['isedit']) ? (isset($_POST['year']) && !empty(trim($_POST['year'])) ? trim($_POST['year']) : '') : '',
                'id' =>  isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : '',
                'yeartext' =>  isset($_POST['yeartext']) && !empty(trim($_POST['yeartext'])) ? trim($_POST['yeartext']) : '',
                'table' => [],
                'accountsid' => isset($_POST['accountsid']) ? $_POST['accountsid'] : '',
                'accountsname' => isset($_POST['accountsname']) ? $_POST['accountsname'] : '',
                'amounts' => isset($_POST['amounts']) ? $_POST['amounts'] : '',
                'errmsg' => ''
            ];

            if(empty($data['year']) && !$data['isedit']){
                $data['errmsg'] = 'Select year';
            }else{
                if((int)$this->budgetModel->CheckYear($data) > 0){
                    $data['errmsg'] = 'Budget already exists for selected year';
                }
            }

            if(!empty($data['errmsg'])){
                $this->view('churchbudgets/add',$data);
                exit;
            }

            for($i = 0; $i < count($data['accountsid']); $i++){
                array_push($data['table'],[
                    'aid' => $data['accountsid'][$i],
                    'name' => $data['accountsname'][$i],
                    'amount' => !empty($data['amounts'][$i]) ? $data['amounts'][$i] : 0,
                ]);
            }

            if(!$this->budgetModel->CreateUpdate($data)){
                $data['errmsg'] = 'Unable to save budget. Retry or contact admin for help';
                $this->view('churchbudgets/add',$data);
                exit;
            }

            flash('budget_msg','Budget saved successfully!');
            redirect('churchbudgets');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function edit($id)
    {
        $years = $this->budgetModel->getFiscalYears();
        $header = $this->budgetModel->BudgetHeader($id);
        $details = $this->budgetModel->BudgetDetails($id);
        checkcenter($header->congregationId);

        if($this->budgetModel->CheckYearClosed($header->yearId)){
            redirect('users/deniedaccess');
            exit;
        }

        $data = [
            'header' => $header,
            'details' => $details,
            'years' => $years,
            'title' => 'Edit budget',
            'isedit' => true,
            'year' => $header->yearId,
            'id' =>  $header->ID,
            'yeartext' =>  $header->budgetName,
            'table' => [],
            'errmsg' => ''
        ];

        foreach($details as $detail){
            array_push($data['table'],[
                'aid' => $detail->tid,
                'name' => $detail->accountType,
                'amount' => $detail->amount
            ]);
        }
        $this->view('churchbudgets/add',$data);
        exit;
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