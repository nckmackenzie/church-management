<?php
class Expenses extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else{
            $this->expenseModel = $this->model('Expense');
        }
    }
    public function index()
    {
        $form = 'Expenses';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->expenseModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $expenses = $this->expenseModel->getExpenses();
        $data = ['expenses' => $expenses];
        $this->view('expenses/index',$data);
    }
    public function add()
    {
        $form = 'Expenses';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->expenseModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $accounts = $this->expenseModel->getAccounts();
        $paymethods = $this->expenseModel->paymethods();
        $banks = $this->expenseModel->banks();
        $voucherno = $this->expenseModel->receiptNo();
        $data = [
            'voucherno' => $voucherno,
            'date' => '',
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
            $accounts = $this->expenseModel->getAccounts();
            $paymethods = $this->expenseModel->paymethods();
            $banks = $this->expenseModel->banks();
            $voucherno = $this->expenseModel->receiptNo();
            $data = [
            'voucherno' => $voucherno,
            'voucher' => trim($_POST['voucher']),
            'date' => trim($_POST['date']),
            'expensetype' => trim($_POST['expensetype']),
            'accounts' => $accounts,
            'account' => trim($_POST['account']),
            'costcentre' => trim($_POST['costcentre']),
            'paymethods' => $paymethods,
            'paymethod' => trim($_POST['paymethod']),
            'deductfrom' => !empty($_POST['cashtype']) ? trim($_POST['cashtype']) : '',
            'banks' => $banks,
            'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : NULL,
            'amount' => trim($_POST['amount']),
            'reference' => trim($_POST['reference']),
            'description' => trim($_POST['description']),
            'file' => $_FILES['file'],
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
            
            if ($data['file']['size'] > 0) {
                $fileName = $data['file']['name'];
                $fileTmpName = $data['file']['tmp_name'];
                $fileSize = $data['file']['size'];
                $fileError = $data['file']['error'];

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
            $accounts = $this->expenseModel->getAccounts();
            $paymethods = $this->expenseModel->paymethods();
            $banks = $this->expenseModel->banks();
            $voucherno = $this->expenseModel->receiptNo();
            $data = [
                'voucherno' => $voucherno,
                'date' => '',
                'accounts' => $accounts,
                'expensetype' => '',
                'account' => '',
                'paymethods' => $paymethods,
                'paymethod' => '',
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
            $this->view('expenses/add',$data);
        }
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
        // $form = 'Expenses';
        // if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->expenseModel->CheckRights($form)) {
        //     redirect('users/deniedaccess');
        //     exit();
        // }
        $expense = $this->expenseModel->getExpense($id);
        $accounts = $this->expenseModel->getAccounts();
        $paymethods = $this->expenseModel->paymethods();
        $banks = $this->expenseModel->banks();
        $groups = $this->expenseModel->getGroup();
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
        if ($data['expense']->congregationId != $_SESSION['congId'] || $_SESSION['userType'] > 2) {
           redirect('expenses');
        }
        else{
            $this->view('expenses/edit',$data);
        }
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
            if (!empty($data['id'])) {
                if ($this->expenseModel->delete($data)) {
                    flash('expense_msg',"Expense Updated Successfully!");
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
}