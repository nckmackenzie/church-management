<?php
class Expenses extends Controller{
    private $authmodel;
    private $expenseModel;
    private $reusemodel;

    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'expenses');
        $this->expenseModel = $this->model('Expense');
        $this->reusemodel = $this->model('Reusables');
    }

    public function index()
    {
        $expenses = $this->expenseModel->getExpenses();
        $data = ['expenses' => $expenses];
        $this->view('expenses/index',$data);
    }

    public function getvoucherno($date)
    {
        $yearId = $this->reusemodel->GetFiscalYear($date);
        $yearName = $this->reusemodel->GetYearName($yearId);
        if(!$this->reusemodel->CheckPrefixable($yearId)){
            return $this->expenseModel->GetVoucherNo($yearId);
        }else{
            return $yearName . '/' . $this->expenseModel->GetVoucherNo($yearId);
        }
    }

    public function voucherno()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $date = isset($_GET['txndate']) && !empty(trim($_GET['txndate'])) ? date('Y-m-d',strtotime($_GET['txndate'])) : date('Y-m-d', strtotime($_SESSION['processdate']));
            echo json_encode($this->getvoucherno($date));
        }
    }

    public function add()
    {
        // $accounts = $this->reusemodel->GetAccounts(2);
        $accounts = $this->expenseModel->GetAccounts(1);
        $date = date('Y-m-d',strtotime($_SESSION['processdate']));
        $paymethods = $this->reusemodel->PaymentMethods();
        $banks = $this->reusemodel->GetBanks();
        // $voucherno = $this->expenseModel->VoucherNo();
        $voucherno = $this->getvoucherno($date);
        $data = [
            'voucherno' => $voucherno,
            'date' => $date,
            'accounts' => $accounts,
            'expensetype' => '',
            'account' => '',
            'paymethods' => $paymethods,
            'paymethod' => '',
            'deductfrom' => 'petty cash',
            'banks' => $banks,
            'bank' => '',
            'amount' => '',
            'reference' => '',
            'description' => '',
            'filename' => '',
            'date_err' => '',
            'amount_err' => '',
            'ref_err' => '',
            'desc_err' => '',
            'bank_err' => '',
            'filename_err' => ''
        ];
        $this->view('expenses/add',$data);
        exit;
    }

    public function checkoverspent()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : '',
                'aid' =>   isset($_GET['aid']) && !empty(trim($_GET['aid'])) ? (int)trim($_GET['aid']) : '',
                'type' =>   isset($_GET['type']) && !empty(trim($_GET['type'])) ? (int)trim($_GET['type']) : '',
                'gid' =>   isset($_GET['gid'])  ? (int)trim($_GET['gid']) : '',
            ];

            if(empty($data['edate']) || empty($data['aid']) || empty($data['type'])) exit;
            if($data['type'] === 2 && empty($data['gid'])) exit;

            echo json_encode($this->expenseModel->CheckOverSpent($data));
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
    public function getcostcentre()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
           $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           $category = trim($_POST['category']);
           if ($category == 1) {
               echo '<option value="0">CHURCH</option>';
           }
           else{
                $groups = $this->expenseModel->getGroup();
                foreach ($groups as $group ) {
                    echo '<option value="'.$group->ID.'">'.$group->groupName.'</option>';
                }
           }
        }
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $accounts = $this->expenseModel->GetAccounts($_POST['expensetype']);
            $paymethods = $this->reusemodel->PaymentMethods();
            $banks = $this->reusemodel->GetBanks();
            $voucherno = $this->expenseModel->VoucherNo();
            $data = [
            'voucherno' => $voucherno,
            'voucher' => trim($_POST['voucher']),
            'date' => trim($_POST['date']),
            'expensetype' => trim($_POST['expensetype']),
            'accounts' => $accounts,
            'account' => !empty($_POST['account']) ? trim($_POST['account']) : '',
            'costcentre' => !empty($_POST['costcentre']) ? trim($_POST['costcentre']) : '',
            'paymethods' => $paymethods,
            'paymethod' => !empty($_POST['paymethod']) ? trim($_POST['paymethod']) : '',
            'deductfrom' => !empty($_POST['cashtype']) ? trim($_POST['cashtype']) : '',
            'banks' => $banks,
            'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : NULL,
            'amount' => trim($_POST['amount']),
            'reference' => trim($_POST['reference']),
            'description' => trim($_POST['description']),
            'file' => isset($_FILE) ? $_FILES['file'] : false,
            'filename' => '',
            'hasattachment' => 0,
            'date_err' => '',
            'amount_err' => '',
            'ref_err' => '',
            'desc_err' => '',
            'bank_err' => '',
            'filename_err' => ''
            ];
            
            $fileTmpName = '';
            $fileDesination = '';

            // var_dump($_FILES);
            
            if ($_FILES['file']['size'] > 0) {
                $fileName = $_FILES['file']['name'];
                $fileTmpName = $_FILES['file']['tmp_name'];
                $fileSize = $_FILES['file']['size'];
                $fileError = $_FILES['file']['error'];

                $fileExt = explode('.', $fileName);
                $fileActualExt = strtolower(end($fileExt));
                $allowed = array('jpg','jpeg','png','pdf');

                if (in_array($fileActualExt,$allowed)) {
                    if ($fileError === 0) {
                      if ($fileSize < 1000000) {
                        $fileNameNew = uniqid('',true).'.'.$fileActualExt;
                        $data['filename'] = $fileNameNew;
                        $des = getcwd();
                        $fileDesination = $des.'/img/'.$fileNameNew;
                        $data['hasattachment'] = 1;
                      }else {
                          $data['filename_err'] = "File size is too big";
                      }
                    }else {
                        $data['filename_err']= 'An error occurred during file upload';
                    }
                  }else{
                    $data['filename_err'] = 'Invalid File Type';
                  }
            }
            
            if (empty($data['date'])) {
                $data['date_err'] = 'Select Date';
            }
            if (empty($data['amount'])) {
                $data['amount_err'] = 'Enter Amount';
            }
            if (empty($data['reference'])) {
                $data['ref_err'] = 'Enter Payment Reference';
            }
            if (empty($data['description'])) {
                $data['desc_err'] = 'Enter Brief Description On Expense';
            }
            if ($data['paymethod'] > 2 && (empty($data['bank']) || $data['bank'] == NULL)) {
                $data['bank'] = 'Select Bank';
            }
            if (empty($data['date_err']) && empty($data['amount_err']) && empty($data['ref_err']) 
                && empty ($data['desc_err']) && empty($data['bank_err']) && empty($data['filename_err'])) {
                
                if($data['hasattachment'] === 0){
                    if ($this->expenseModel->create($data)) {
                        flash('expense_msg',"Expense Added Successfully!");
                        redirect('expenses');
                    }
                    else{
                        flash('expense_msg',"Expense wasnt added!",'alert custom-danger');
                        redirect('expenses');
                    }
                }
                elseif ($data['hasattachment'] === 1) {
                    if (move_uploaded_file($fileTmpName,$fileDesination)) {
                        if ($this->expenseModel->create($data)) {
                            flash('expense_msg',"Expense Added Successfully!");
                            redirect('expenses');
                        }
                        else{
                            flash('expense_msg',"Expense wasnt added!",'alert custom-danger');
                            redirect('expenses');
                    }
                    }else {
                        flash('expense_msg','Something went wrong with file upload','alert custom-danger');
                        redirect('expenses');
                    }
                }    
                
            }
            else{
                $this->view('expenses/add',$data);
            }
        }
        else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function getrequisitions(){
        $groupid = isset($_GET['group']) && !empty(trim($_GET['group'])) ? (int)trim($_GET['group']) : null;
        $data = array();

        $balances = $this->expenseModel->GetGroupBalance($groupid);
        foreach($balances as $balance){
            array_push($data,[
                'id' => $balance->ID,
                'label' => $balance->Formated
            ]);
        }
        echo json_encode($data);
    }

    public function approve()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'date' => trim($_POST['date']),
                'voucher' => trim($_POST['voucherno'])
            ];
            if (!empty($data['id'])) {
                if ($this->expenseModel->approve($data)) {
                    flash('expense_msg',"Expense Approved Successfully!");
                    redirect('expenses');# code...
                }
            }
        }
    }

    public function edit($id)
    {
        $expense = $this->expenseModel->getExpense($id);
        $accounts = $this->expenseModel->GetAccounts(1);
        $paymethods = $this->reusemodel->PaymentMethods();
        $banks = $this->reusemodel->GetBanks();
        $groups = $this->expenseModel->getGroup();
        checkcenter($expense->congregationId);
        if($this->reusemodel->CheckYearClosed($expense->fiscalYearId)) :
         flash('contribution_msg','Cannot edit transactions for closed year','alert custom-danger alert-dismissible fade show');
         redirect('contributions');
         exit;
       endif; 
        $data = [
            'expense' => $expense,
            'voucher' => '',
            'date' => '',
            'expensetype' => '',
            'accounts' => $accounts,
            'account' => '',
            'groups' => $groups,
            'costcentre' => '',
            'paymethods' => $paymethods,
            'paymethod' => '',
            'deductfrom' => '',
            'banks' => $banks,
            'bank' => '',
            'amount' => '',
            'reference' => '',
            'description' => '',
            'date_err' => '',
            'amount_err' => '',
            'ref_err' => '',
            'desc_err' => '',
            'bank_err' => ''
        ];
        $this->view('expenses/edit',$data);
        exit;
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'voucher' => trim($_POST['voucher']),
                'date' => trim($_POST['date']),
                'expensetype' => trim($_POST['expensetype']),
                'account' => trim($_POST['account']),
                'costcentre' => trim($_POST['costcentre']),
                'deductfrom' => !empty($_POST['cashtype']) ? trim($_POST['cashtype']) : '',
                'paymethod' => trim($_POST['paymethod']),
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : NULL,
                'amount' => trim($_POST['amount']),
                'reference' => trim($_POST['reference']),
                'description' => trim($_POST['description']),
                'date_err' => '',
                'amount_err' => '',
                'ref_err' => '',
                'desc_err' => '',
                'bank_err' => ''
            ];
            if (empty($data['date'])) {
                $data['date_err'] = 'Select Date';
            }
            if (empty($data['amount'])) {
                $data['amount_err'] = 'Enter Amount';
            }
            if (empty($data['reference'])) {
                $data['ref_err'] = 'Enter Payment Reference';
            }
            if (empty($data['description'])) {
                $data['desc_err'] = 'Enter Brief Description On Expense';
            }
            if ($data['paymethod'] > 2 && (empty($data['bank']) || $data['bank'] == NULL)) {
                $data['bank'] = 'Select Bank';
            }
            if (empty($data['date_err']) && empty($data['amount_err']) && empty($data['ref_err']) 
                && empty ($data['desc_err']) && empty($data['bank_err'])) {
                if ($this->expenseModel->update($data)) {
                    flash('expense_msg',"Expense Updated Successfully!");
                    redirect('expenses');
                }
                else{
                    flash('expense_msg',"Something Went Wrong!",'alert custom-danger');
                    redirect('expenses');
                }
            }
            else{
                $this->view('expenses/edit',$data);
            }
        }
        else{
            redirect('expenses');
        }
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'date' => trim($_POST['date']),
                'voucher' => trim($_POST['voucherno'])
            ];

            if($this->expenseModel->YearIsClosed($data['id'])){
                flash('expense_msg','Cannot delete transactions for closed year','alert custom-danger alert-dismissible fade show');
                redirect('contributions');
                exit;
            }

            if (!empty($data['id'])) {
                if ($this->expenseModel->delete($data)) {
                    flash('expense_msg',"Expense Deleted Successfully!");
                    redirect('expenses');
                }
            }
        }
        else{
            redirect('expenses');
        }
    }

    public function print($id)
    {
        $expense = $this->expenseModel->getExpenseFull($id);
        $data = ['expense' => $expense];
        $this->view('expenses/print',$data);
    }

    public function getaccounts()
    {
        $type = isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : NULL;
        $accounts = $this->expenseModel->GetAccounts($type);
        $data = array();

        foreach($accounts as $account){
            array_push($data,[
                'id' => $account->ID,
                'label' => $account->accountType
            ]);
        }
        echo json_encode($data);
    }
}