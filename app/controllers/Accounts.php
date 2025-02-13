<?php
class Accounts extends Controller{
    private $authmodel;
    private $accountModel;
    private $congregationModel;
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'g/l accounts');
        $this->accountModel = $this->model('Account');
        $this->congregationModel = $this->model('Congregation');
    }
    public function index()
    {
        $accounts = $this->accountModel->index();
        $data = ['accounts' => $accounts];
        $this->view('accounts/index',$data);
    }
    public function add()
    {
        $accounttypes = $this->accountModel->getAccountTypes();
        $data = [
            'accountname' => '',
            'congregationName' => strtoupper($this->congregationModel->getCongregation($_SESSION['congId'])->CongregationName),
            'accounttypes' => $accounttypes,
            'accounts' => '',
            'accounttype' => '',
            'description' => '',
            'congregation' => '',
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
                'description' => !empty($_POST['description']) ? trim($_POST['description']) : NULL,
                'forgroup' => isset($_POST['forgroup']) ? 1 : 0,
                'congregation' => converttobool($_SESSION['isParish']) ? (!empty($_POST['congregation']) ? (int)$_POST['congregation'] : 0) : $_SESSION['congId'],
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

            if($data['check'] == 1 && $data['subcategory']){
                $subaccount = $this->accountModel->getAccount($data['subcategory']);
                $data['forgroup'] = $subaccount->forGroup;
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
        $account = $this->accountModel->getAccount($id);
        $accounttypes = $this->accountModel->getAccountTypes();
        $data = [
            'id' => (int)$account->ID,
            'congregationName' => strtoupper($this->congregationModel->getCongregation($_SESSION['congId'])->CongregationName),
            // 'account' => $account,
            'accountname' => ucwords($account->accountType),
            'accounttypes' => $accounttypes,
            'accounts' => '',
            'accounttype' => (int)$account->accountTypeId,
            'description' => ucwords($account->description),
            'congregation' => (int)$account->congregationId,
            'forgroup' => converttobool($account->forGroup),
            'issub' => converttobool($account->isSubCategory),
            'subcategory' => (int)$account->parentId,
            'active' => converttobool($account->active),
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

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           
            $accounttypes = $this->accountModel->getAccountTypes();
            $data = [
                'id' => trim($_POST['id']),
                'accountname' => trim(strtolower($_POST['accountname'])),
                'initialname' => trim(strtolower($_POST['initialname'])),
                'accounttypes' => $accounttypes,
                'accounttype' => trim($_POST['accounttype']),
                'accounts' => '',
                'check' => isset($_POST['check']) ? 1 : 0,
                'subcategory' => !empty($_POST['subcategory']) ? trim($_POST['subcategory']) : NULL,
                'description' => !empty($_POST['description']) ? trim($_POST['description']) : NULL,
                'forgroup' => isset($_POST['forgroup']) ? 1 : 0,
                'congregation' => converttobool($_SESSION['isParish']) ? (!empty($_POST['congregation']) ? (int)$_POST['congregation'] : 0) : $_SESSION['congId'],
                'active' => isset($_POST['active']) ? 1 : 0,
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
                if ($this->accountModel->update($data)) {
                    flash('account_msg','Account Updated Successfully');
                    redirect('accounts');
                }else{
                    flash('account_msg','Something went wrong creating the account','alert custom-danger alert-dismissible fade show');
                    redirect('accounts');
                }
            }
            else{
                $this->view('accounts/add',$data);
            }
        }
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $id = !empty(trim($_POST['id'])) ? trim(strtolower($_POST['id'])) : null;

            if(is_null($id)){
                flash('account_msg','Unable to get selected account','alert custom-danger alert-dismissible fade show');
                redirect('accounts');
                exit;
            }

            if(!$this->accountModel->delete($id))
            {
                flash('account_msg','Cannot delete as account is referenced elsewhere','alert custom-danger alert-dismissible fade show');
                redirect('accounts');
                exit;
            }

            flash('account_msg','Deleted successfully!');
            redirect('accounts');
            exit;            
        }
    }
}