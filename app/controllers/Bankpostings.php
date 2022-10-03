<?php
class Bankpostings extends Controller 
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('');
        }else {
            $this->postingsModel = $this->model('Bankposting');
        }
    }
    public function index()
    {
        $form = 'Post Bankings';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6 &&  !$this->postingsModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $postings = $this->postingsModel->getPostings();
        $data = ['postings' => $postings];
        $this->view('bankpostings/index',$data);
    }
    public function add()
    {
        $form = 'Post Bankings';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6 &&  !$this->postingsModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $banks = $this->postingsModel->getBanks();
        $methods = $this->postingsModel->getMethods();
        $accounts = $this->postingsModel->getAccounts();
        $data = [
            'banks' => $banks,
            'methods' => $methods,
            'accounts' => $accounts,
            'date' => '',
            'bank' => '',
            'type' => '',
            'amount' => '',
            'account' => '',
            'reference' => '',
            'narration' => '',
            'date_err' => '',
            'bank_err' => '',
            'type_err' => '',
            'amount_err' => '',
            'account_err' => '',
            'reference_err' => ''
        ];
        $this->view('bankpostings/add',$data);
    }
    public function create()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $banks = $this->postingsModel->getBanks();
            $methods = $this->postingsModel->getMethods();
            $accounts = $this->postingsModel->getAccounts();
            $data = [
                'banks' => $banks,
                'methods' => $methods,
                'accounts' => $accounts,
                'date' => trim($_POST['date']),
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'type' => !empty($_POST['type']) ? trim($_POST['type']) : '',
                'amount' => trim($_POST['amount']),
                'account' => !empty($_POST['account']) ? trim($_POST['account']) : '',
                'reference' => trim($_POST['reference']),
                'narration' => trim($_POST['narration']),
                'date_err' => '',
                'bank_err' => '',
                'type_err' => '',
                'amount_err' => '',
                'account_err' => '',
                'reference_err' => ''
            ];
            //validation
            if(empty($data['date'])){
                $data['date_err'] = 'Select date';
            }
            if(empty($data['bank'])){
                $data['bank_err'] = 'Select bank';
            }
            if(empty($data['type'])){
                $data['type_err'] = 'Select transaction type';
            }
            if(empty($data['amount'])){
                $data['amount_err'] = 'Enter amount';
            }
            if(empty($data['account'])){
                $data['account_err'] = 'Select G/L Account';
            }
            if(empty($data['reference'])){
                $data['reference_err'] = 'Enter reference';
            }

            if(empty($data['date_err']) && empty($data['bank_err']) && empty($data['type_err']) 
              && empty($data['amount_err']) && empty($data['account_err']) && empty($data['reference_err'])){

                if($this->postingsModel->create($data)){
                    flash('postings_msg','Posting Created Successfully!');
                    redirect('bankpostings');
                }   

            }else{
                $this->view('bankpostings/add',$data);
            }

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    public function edit($id)
    {
        $posting = $this->postingsModel->edit($id);
        $banks = $this->postingsModel->getBanks();
        $methods = $this->postingsModel->getMethods();
        $accounts = $this->postingsModel->getAccounts();
        $data = [
            'banks' => $banks,
            'methods' => $methods,
            'accounts' => $accounts,
            'id' => $posting->ID,
            'date' => $posting->transactionDate,
            'bank' => $posting->bankId,
            'type' => $posting->transactionMethod,
            'amount' => floatval($posting->debit) > 0 ? $posting->debit : $posting->credit,
            'account' => $posting->accountId,
            'reference' => strtoupper($posting->reference),
            'narration' => strtoupper($posting->narration),
            'date_err' => '',
            'bank_err' => '',
            'type_err' => '',
            'amount_err' => '',
            'account_err' => '',
            'reference_err' => ''
        ];
        $this->view('bankpostings/edit',$data);
    }
    public function update()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $banks = $this->postingsModel->getBanks();
            $methods = $this->postingsModel->getMethods();
            $accounts = $this->postingsModel->getAccounts();
            $data = [
                'banks' => $banks,
                'methods' => $methods,
                'accounts' => $accounts,
                'id'=> trim($_POST['id']),
                'date' => trim($_POST['date']),
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'type' => !empty($_POST['type']) ? trim($_POST['type']) : '',
                'amount' => trim($_POST['amount']),
                'account' => !empty($_POST['account']) ? trim($_POST['account']) : '',
                'reference' => trim($_POST['reference']),
                'narration' => trim($_POST['narration']),
                'date_err' => '',
                'bank_err' => '',
                'type_err' => '',
                'amount_err' => '',
                'account_err' => '',
                'reference_err' => ''
            ];
            //validation
            if(empty($data['date'])){
                $data['date_err'] = 'Select date';
            }
            if(empty($data['bank'])){
                $data['bank_err'] = 'Select bank';
            }
            if(empty($data['type'])){
                $data['type_err'] = 'Select transaction type';
            }
            if(empty($data['amount'])){
                $data['amount_err'] = 'Enter amount';
            }
            if(empty($data['account'])){
                $data['account_err'] = 'Select G/L Account';
            }
            if(empty($data['reference'])){
                $data['reference_err'] = 'Enter reference';
            }

            if(empty($data['date_err']) && empty($data['bank_err']) && empty($data['type_err']) 
              && empty($data['amount_err']) && empty($data['account_err']) && empty($data['reference_err'])){

                if($this->postingsModel->update($data)){
                    flash('postings_msg','Posting Updated Successfully!');
                    redirect('bankpostings');
                }   

            }else{
                $this->view('bankpostings/edit',$data);
            }

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $id = trim($_POST['id']);

            if($this->postingsModel->delete($id)){
                flash('postings_msg','Posting Deleted Successfully!');
                redirect('bankpostings');
            }   

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
}