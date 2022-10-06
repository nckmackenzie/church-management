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
                'isedit' => converttobool($_POST['isedit']),
                'year' => isset($_POST['year']) && !empty(trim($_POST['year'])) ? trim($_POST['year']) : '',
                'id' =>  isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : '',
                'yeartext' =>  isset($_POST['yeartext']) && !empty(trim($_POST['yeartext'])) ? trim($_POST['yeartext']) : '',
                'table' => [],
                'accountsid' => isset($_POST['accountsid']) ? $_POST['accountsid'] : '',
                'accountsname' => isset($_POST['accountsname']) ? $_POST['accountsname'] : '',
                'amounts' => isset($_POST['amounts']) ? $_POST['amounts'] : '',
                'errmsg' => ''
            ];

            if(empty($data['year'])){
                $data['errmsg'] = 'Select year';
            }else{
                if((int)$this->budgetModel->CheckYear($data) > 0){
                    $data['errmsg'] = 'Budget already exists for selected year';;
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