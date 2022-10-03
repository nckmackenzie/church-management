<?php
class Accounts extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else {
            $this->accountModel = $this->model('Account');
        }
    }
    public function index()
    {
        $form = 'G/L Accounts';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->accountModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $accounts = $this->accountModel->index();
        $data = ['accounts' => $accounts];
        $this->view('accounts/index',$data);
    }
    public function add()
    {
        $form = 'G/L Accounts';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->accountModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $accounttypes = $this->accountModel->getAccountTypes();
        $data = [
            'accountname' => '',
            'accounttypes' => $accounttypes,
            'accounts' => '',
            'accounttype' => '',
            'description' => '',
            'forgroup' => '',
            'subcategory' => '',
            'check' => '',
            'name_err' => '',
            'account_err' => ''
        ];
        $this->view('accounts/add',$data);
    }
    public function getsubcategory()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST =filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $main = trim($_POST['main']);
            $accounts = $this->accountModel->getAccounts($main);
            foreach ($accounts as $account ) {
                echo '<option value="'.$account->ID.'">'.$account->accountType.'</option>';
            }
        }
        else{
            redirect('mains');
        }
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           
            $accounttypes = $this->accountModel->getAccountTypes();
            $data = [
                'id' => '',
                'accountname' => trim($_POST['accountname']),
                'accounttypes' => $accounttypes,
                'accounttype' => trim($_POST['accounttype']),
                'accounts' => '',
                'check' => isset($_POST['check']) ? 1 : 0,
                'subcategory' => !empty($_POST['subcategory']) ? trim($_POST['subcategory']) : NULL,
                'description' => trim($_POST['description']),
                'forgroup' => isset($_POST['forgroup']) ? 1 : 0,
                'name_err' => '',
                'account_err' => ''
            ];
            
            if ($data['check'] == 1) {
                $accounts = $this->accountModel->getAccounts($data['accounttype']);
                $data['accounts'] = $accounts;
            }
            else{
                if (!$this->accountModel->checkExists($data)) {
                    $data['name_err'] = 'Account Already Exists';
                }
            }
            if (empty($data['accountname'])) {
                $data['name_err'] = 'Enter Account Name';
            }
            if ($data['check'] == 1 && empty($data['subcategory'])) {
                $data['account_err'] = 'Select Subcategory';
            }
            if (empty($data['name_err']) && empty($data['account_err'])) {
                if ($this->accountModel->create($data)) {
                    flash('account_msg','Account Created Successfully');
                    redirect('accounts');
                }
            }
            else{
                $this->view('accounts/add',$data);
            }
        }
    }
    public function edit($id)
    {
        $form = 'G/L Accounts';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->accountModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $account = $this->accountModel->getAccount($id);
        $accounttypes = $this->accountModel->getAccountTypes();
        $data = [
            'id' => '',
            'account' => $account,
            'accountname' => '',
            'accounttypes' => $accounttypes,
            'accounts' => '',
            'accounttype' => '',
            'description' => '',
            'forgroup' => '',
            'subcategory' => '',
            'check' => '',
            'name_err' => '',
            'account_err' => ''
        ];
        if ($account->isSubCategory == 1) {
            $accounts = $this->accountModel->getAccounts($account->accountTypeId);
            $data['accounts'] = $accounts;
        }
        // print_r($data['account']);
        $this->view('accounts/edit',$data);
    }
}