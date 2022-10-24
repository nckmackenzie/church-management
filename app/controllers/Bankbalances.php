<?php

class Bankbalances extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('users');
        }else {
            $this->bankbalanceModel = $this->model('Bankbalance');
        }
    }
    public function index()
    {
        $form = 'Bank Balances';
        if ($_SESSION['userType'] > 2 &&  !$this->bankbalanceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $balances = $this->bankbalanceModel->index();
        $data = ['balances' => $balances];
        $this->view('bankbalances/index',$data);
    }
    public function add()
    {
        $form = 'Bank Balances';
        if ($_SESSION['userType'] > 2 &&  !$this->bankbalanceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $banks = $this->bankbalanceModel->getBanks();
        $data = [
            'banks' => $banks,
            'date' => '',
            'bank' => '',
            'amount' => '',
            'date_err' => '',
            'bank_err' => '',
            'amount_err' => '',
        ];
        $this->view('bankbalances/add',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $banks = $this->bankbalanceModel->getBanks();
            $data = [
                'id' => '',
                'banks' => $banks,
                'date' => trim($_POST['date']),
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'amount' => trim($_POST['amount']),
                'date_err' => '',
                'bank_err' => '',
                'amount_err' => '',
            ];

            //validation
            if(empty($data['date'])){
                $data['date_err'] = 'Select balance date';
            }

            if(empty($data['amount'])){
                $data['amount_err'] = 'Enter balance';
            }
            if(empty($data['bank'])){
                $data['bank_err'] = 'Select bank';
            }

            if(!empty($data['date'] && !empty($data['bank']))){
                if($this->bankbalanceModel->checkExists($data['date'],$data['id'],$data['bank'])){
                    $data['date_err'] = 'Balance for selected date exists';
                }
            }

            if(empty($data['date_err']) && empty($data['amount_err']) && empty($data['bank_err'])){
                if($this->bankbalanceModel->create($data)){
                    flash('balances_msg','Balance Added Successfully!');
                    redirect('bankbalances');
                }
            }else{
                $this->view('bankbalances/add',$data);
            }

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    public function edit($id)
    {
        $form = 'Bank Balances';
        if ($_SESSION['userType'] > 2 &&  !$this->bankbalanceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $balance = $this->bankbalanceModel->edit($id);
        $banks = $this->bankbalanceModel->getBanks();
        $data = [
            'banks' => $banks,
            'balance' => $balance,
            'date' => '',
            'bank' => '',
            'amount' => '',
            'date_err' => '',
            'bank_err' => '',
            'amount_err' => ''
        ];
        $this->view('bankbalances/edit',$data);
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $banks = $this->bankbalanceModel->getBanks();
            $data = [
                'banks' => $banks,
                'id' => trim($_POST['id']),
                'date' => trim($_POST['date']),
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'amount' => trim($_POST['amount']),
                'date_err' => '',
                'bank_err' => '',
                'amount_err' => '',
            ];

            //validation
            if(empty($data['date'])){
                $data['date_err'] = 'Select balance date';
            }
            if(empty($data['amount'])){
                $data['amount_err'] = 'Enter balance';
            }
            if(empty($data['bank'])){
                $data['bank_err'] = 'Select bank';
            }

            if(!empty($data['date'] && !empty($data['bank']))){
                if($this->bankbalanceModel->checkExists($data['date'],$data['id'],$data['bank'])){
                    $data['date_err'] = 'Balance for selected date exists';
                }
            }

            if(empty($data['date_err']) && empty($data['amount_err']) && empty($data['bank_err'])){
                if($this->bankbalanceModel->update($data)){
                    flash('balances_msg','Balance Updated Successfully!');
                    redirect('bankbalances');
                }
            }else{
                $this->view('bankbalances/add',$data);
            }

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $id = trim($_POST['id']);
            
            if($this->bankbalanceModel->delete($id)){
                flash('balances_msg','Balance Deleted Successfully!');
                redirect('bankbalances');
            }
            
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
}