<?php
class Banks extends Controller{
    private $bankModel;
    private $authmodel;
    private $reusablemodel;

    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        $this->reusablemodel = $this->model('Reusables');
        checkrights($this->authmodel,'banks');
        $this->bankModel = $this->model('Bank');
    }
    public function index()
    {
        $banks = $this->bankModel->getBanks();
        $data = [
            'banks' => $banks,
            'subaccounts' => $this->bankModel->GetSubaccounts()
        ];
        $this->view('banks/index',$data);
    }
    public function add()
    {
       $data = [
           'bankname' => '',
           'account' => '',
           'openingbal' => '',
           'asof' => '',
           'name_err' => '',
           'asof_err' => '',
           'account_err' => '',
       ];
       $this->view('banks/add',$data);
    }
    public function create()
    {
        //check if post
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'bankname' => trim(strtolower($_POST['bankname'])),
                'account' => trim(strtolower($_POST['account'])),
                'openingbal' => trim($_POST['openingbal']),
                'asof' => !empty($_POST['asof']) ? trim($_POST['asof']) : NULL,
                'name_err' => '',
                'asof_err' => '',
                'account_err' => '',
            ];
            //validate
            if (empty($data['bankname'])) {
                $data['name_err'] = 'Enter Bank Name';
            }
            if (!empty($data['openingbal']) && empty($data['asof'])) {
                $data['asof_err'] = 'Select Opening Balance Date';
            }
            if (!empty($data['account'])) {
                if (!$this->bankModel->checkExists($data['account'],'')) {
                    $data['account_err'] = 'Account Already Exists';
                }  
            }
            if (empty($data['name_err']) && empty($data['asof_err']) && empty($data['account_err'])) {
                if ($this->bankModel->create($data)) {
                    flash('bank_msg','Bank Created Successfully!');
                    redirect('banks');
                }
                else{
                    flash('bank_msg','Something Went Wrong!','alert custom-danger');
                    redirect('banks');
                }
            }
            else{
                $this->view('banks/add',$data);
            }
        }
        else{
            $data = [
                'bankname' => '',
                'account' => '',
                'openingbal' => '',
                'asof' => '',
                'name_err' => '',
                'asof_err' => '',
                'account_err' => '',
            ];
            $this->view('banks/add',$data);
        }
    }
    public function edit($id)
    {
        $bank = $this->bankModel->getbank($id);
        $data = [
            'bank' => $bank,
            'name_err' => '',
            'account_err' => ''
        ];
        //check if congregation is same
        if ($data['bank']->congregationId != $_SESSION['congId']) {
            redirect('banks');
        }
        elseif ($data['bank']->congregationId == $_SESSION['congId'] || $_SESSION['userType'] > 2) {
            $this->view('banks/edit',$data);
        }
    }
    public function update()
    {
         //check if post
         if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $bank = $this->bankModel->getbank($_POST['id']);
            $data = [
                'bank' => $bank,
                'id' => trim($_POST['id']),
                'bankname' => trim(strtolower($_POST['bankname'])),
                'account' => trim(strtolower($_POST['account'])),
                'name_err' => '',
                'account_err' => '',
            ];
        //    print_r($data['bank']);
            //validate
            if (empty($data['bankname'])) {
                $data['name_err'] = 'Enter Bank Name';
            }
            if (!empty($data['account'])) {
                if (!$this->bankModel->checkExists($data['account'],$data['id'])) {
                    $data['account_err'] = 'Account Already Exists';
                }  
            }
            if (empty($data['name_err']) && empty($data['account_err'])) {
                if ($this->bankModel->update($data)) {
                    flash('bank_msg','Bank Updated Successfully!');
                    redirect('banks');
                }
                else{
                    flash('bank_msg','Something Went Wrong!','alert custom-danger');
                    redirect('banks');
                }
            }
            else{
                $this->view('banks/edit',$data);
            }
        }
        else{
            $data = [
                'id' => '',
                'bankname' => '',
                'account' => '',
                'name_err' => '',
                'account_err' => '',
            ];
            $this->view('banks/edit',$data);
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : null,
                'bankname' => trim(strtolower($_POST['bankname'])),
            ];

            if(is_null($data['id'])){
                flash('bank_msg','No selection detected!',alerterrorclass());
                redirect('banks');
                exit;
            }
                    
            if(!$this->bankModel->checkreferenced($data['id'])){
                flash('bank_msg','Cannot delete as bank referenced elsewhere',alerterrorclass());
                redirect('banks');
                exit;
            }

            if ($this->bankModel->delete($data)) {
                flash('bank_msg','Bank Deleted Successfully!');
                redirect('banks');
                exit;
            }
            else{
                flash('bank_msg','Something Went Wrong!',alerterrorclass());
                redirect('banks');
                exit;
            }
            
        }
        else{
            redirect('banks');
        }
    }

    public function subaccount()
    {
        $data = [
            'banks' => $this->reusablemodel->GetBanks(),
            'accounts' => $this->reusablemodel->GetAccountsAll(),
            'id' => '',
            'isedit' => false,
            'name' => '',
            'bank' => '',
            'account' => '',
            'districtgroup' => ''
        ];
        $this->view('banks/subaccount',$data);
    }

    public function getdistrictorgroup()
    {
        $type = isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null;
        $data = array();
        $results = $this->bankModel->GetDistrictOrGroup($type);
        foreach($results as $result):
            array_push($data,[
                'id' => $result->ID,
                'label' => strtoupper($result->ColumnName),
            ]);
        endforeach;

        echo json_encode($data);
    }

    public function createupdatesubaccount()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $fields = json_decode(file_get_contents('php://input'));
            $data = [
                'id' => isset($fields->id) && !empty(trim($fields->id)) ? (int)trim($fields->id) : null,
                'isedit' => converttobool($fields->isedit),
                'name' => isset($fields->name) && !empty(trim($fields->name)) ? strtolower(trim($fields->name)) : null,
                'bank' => isset($fields->bank) && !empty(trim($fields->bank)) ? trim($fields->bank) : null,
                'account' => isset($fields->glaccount) && !empty(trim($fields->glaccount)) ? trim($fields->glaccount) : null,
                'districtgroup' => isset($fields->districtgroup) && !empty(trim($fields->districtgroup)) ? strtolower(trim($fields->districtgroup)) : null,
                'param' => isset($fields->param) && !empty(trim($fields->param)) ? trim($fields->param) : null,
            ];

            if(is_null($data['name']) || is_null($data['bank']) || is_null($data['account']) 
              || is_null($data['districtgroup']) || is_null($data['param'])){

                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Fill all required fields']);
                exit;  
            }

            if(!$this->bankModel->CheckSubAccountExists($data)){
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Sub-account name exists']);
                exit; 
            }

            if(!$this->bankModel->CreateUpdateSubAccount($data)){
                http_response_code(500);
                echo json_encode(['success' => false,'message' => 'Unable to save this transaction. Contact admin']);
                exit;
            }

            http_response_code(201);
            echo json_encode(['success' => true,'message' => 'Saved successfully']);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit();
        }
    }

    public function editsubaccount($id)
    {
        $subaccount = $this->bankModel->GetSubAccount($id);
        $data = [
            'banks' => $this->reusablemodel->GetBanks(),
            'accounts' => $this->reusablemodel->GetAccountsAll(),
            'id' => $subaccount->ID,
            'isedit' => true,
            'name' => strtoupper($subaccount->AccountName),
            'bank' => $subaccount->BankId,
            'account' => $subaccount->AccountId,
            'districtgroup' => $subaccount->GroupDistrict,
            'param' => !is_null($subaccount->GroupId) ? $subaccount->GroupId : $subaccount->DistrictId,
            'results' => $this->bankModel->GetDistrictOrGroup($subaccount->GroupDistrict)
        ];
        $this->view('banks/subaccount',$data);
    }

    public function deletesubaccount()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : null,
                'subaccount' => trim(strtolower($_POST['subaccount'])),
            ];

            if(is_null($data['id'])){
                flash('bank_msg','No selection detected!',alerterrorclass());
                redirect('banks');
                exit;
            }
                    
            if ($this->bankModel->deletesubaccount($data)) {
                flash('bank_msg','Sub Account Deleted Successfully!');
                redirect('banks');
                exit;
            }
            else{
                flash('bank_msg','Something Went Wrong!',alerterrorclass());
                redirect('banks');
                exit;
            }
        }
        else{
            redirect('banks');
        }
    }

    public function openingbalance()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            $data = [
                'subaccount' => isset($_POST['subaccount']) && !empty(trim($_POST['subaccount'])) ? (int)trim($_POST['subaccount']) : null,
                'asof' => isset($_POST['asof']) && !empty(trim($_POST['asof'])) ? date('Y-m-d',strtotime(trim($_POST['asof']))) : null,
                'balance' => isset($_POST['balance']) && !empty(trim($_POST['balance'])) ? floatval(trim($_POST['balance'])) : null,
            ];
            
            if(is_null($data['subaccount']) || is_null($data['asof']) || is_null($data['balance'])){
                flash('bank_msg','Provide all required fields!',alerterrorclass());
                redirect('banks');
                exit;
            }

            if(!$this->bankModel->SetOpeningBalance($data)){
                flash('bank_msg','Something Went Wrong while creating the opening balance!',alerterrorclass());
                redirect('banks');
                exit;
            }

            flash('bank_msg','Sub account opening balance set successfully!');
            redirect('banks');
            exit;
        }
    }

    public function subaccountbalances()
    {
        $data = [
            'entries' => $this->bankModel->GetSubAccountOpeningBalances(),
        ];
        $this->view('banks/subaccountbalances',$data);
    }

    public function editbalance($id)
    {
        $entry = $this->bankModel->GetOpeningBalanceEntry($id);
        $data = [
            'subaccounts' => $this->bankModel->GetSubaccounts(),
            'id' => $entry->ID,
            'subaccount' => $entry->SubAccountId,
            'asof' => $entry->TransactionDate,
            'balance' => $entry->Amount,
            'isedit' => true,
            'subaccount_err' => '',
            'asof_err' => '',
            'balance_err' => ''         
        ];
        $this->view('banks/editbalance',$data);
    }

    public function updatebalance()
    {
        $entry = $this->bankModel->GetOpeningBalanceEntry($_POST['id']);
        if(!$entry){
            flash('subaccountbal_msg','Invalid entry selected!',alerterrorclass());
            redirect('banks/subaccountbalances');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'subaccounts' => $this->bankModel->GetSubaccounts(),
                'id' => isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : null,
                'subaccount' => $entry->SubAccountId,
                'asof' => isset($_POST['asof']) && !empty(trim($_POST['asof'])) ? date('Y-m-d',strtotime(trim($_POST['asof']))) : null,
                'balance' => isset($_POST['balance']) && !empty(trim($_POST['balance'])) ? floatval(trim($_POST['balance'])) : null,
                'isedit' => true,
                'subaccount_err' => '',
                'asof_err' => '',
                'balance_err' => ''      
            ];
        //    print_r($data['bank']);
            //validate
            if (is_null($data['subaccount'])) {
                $data['subaccount_err'] = 'Enter Sub Account';
            }
            if (is_null($data['asof'])) {
                $data['asof_err'] = 'Enter As Of Date';
            }
            if(is_null($data['balance'])){
                $data['balance_err'] = 'Enter Balance Amount';
            }

            if (empty($data['subaccount_err']) && empty($data['asof_err']) && empty($data['balance_err'])) {
                if ($this->bankModel->UpdateSubAccountOpeningBalance($data)) {
                    flash('subaccountbal_msg','Opening Balance Updated Successfully!');
                    redirect('banks/subaccountbalances');
                }
                else{
                    flash('subaccountbal_msg','Something Went Wrong!','alert custom-danger');
                    redirect('banks/subaccountbalances');
                }
            }
            else{
                $this->view('banks/editbalance',$data);
            }
        }
        else{
            $data = [
                'id' => '',
                'subaccount' => '',
                'account' => '',
                'name_err' => '',
                'account_err' => '',
            ];
            $this->view('banks/edit',$data);
        }
    }

    public function deletebalanceentry()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : null,
            ];

            if(is_null($data['id'])){
                flash('subaccountbal_msg','No selection detected!',alerterrorclass());
                redirect('banks/subaccountbalances');
                exit;
            }
                    
            if ($this->bankModel->DeleteSubAccountOpeningBalance($data)) {
                flash('subaccountbal_msg','Entry Deleted Successfully!');
                redirect('banks/subaccountbalances');
                exit;
            }
            else{
                flash('subaccountbal_msg','Something Went Wrong!',alerterrorclass());
                redirect('banks/subaccountbalances');
                exit;
            }
        }
        else{
            redirect('banks/subaccountbalances');
        }
    }
}