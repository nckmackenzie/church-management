<?php
class Deposits extends Controller
{
    public function __construct()
    {
       if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
       }
       $this->authmodel = $this->model('Auth');
       checkrights($this->authmodel,'cash deposits');
       $this->depositmodel = $this->model('Deposit');
    }

    public function index()
    {
        $data = [
            'deposits' => $this->depositmodel->GetDeposits()
        ];
        $this->view('deposits/index',$data);
        exit;
    }

    public function add()
    {
        $data= [
            'banks' => $this->depositmodel->GetBanks(),
            'title' => 'Add deposit',
            'id' => '',
            'touched' => false,
            'isedit' => false,
            'date' => '',
            'bank' => '',
            'reference' => '',
            'description' => '',
            'amount' => '',
            'date_err' => '',
            'bank_err' => '',
            'reference_err' => '',
            'amount_err' => '',
        ];
        $this->view('deposits/add',$data);
        exit;
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'banks' => $this->depositmodel->GetBanks(),
                'title' => converttobool($_POST['isedit']) ? 'Edit deposit' : 'Add deposit',
                'id' => trim($_POST['id']),
                'touched' => true,
                'isedit' => converttobool($_POST['isedit']),
                'date' => !empty(trim($_POST['date'])) ? date('Y-m-d',strtotime(trim($_POST['date']))) : '',
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'reference' => !empty(trim($_POST['reference'])) ? trim($_POST['reference']) : '',
                'description' => !empty(trim($_POST['description'])) ? trim($_POST['description']) : '',
                'amount' => !empty(trim($_POST['amount'])) ? floatval(trim($_POST['amount'])) : '',
                'date_err' => '',
                'bank_err' => '',
                'amount_err' => '',
            ];

            //validate
            if(empty($data['date'])){
                $data['date_err'] = 'Select deposit date';
            }else{
                if($data['date'] > date('Y-m-d')){
                    $data['date_err'] = 'Invalid deposit date';
                }
            }
            if(empty($data['bank'])){
                $data['bank_err'] ='Select bank';
            }
            if(empty($data['amount'])){
                $data['amount_err'] ='Enter amount desposited';
            }

            if(!empty($data['date_err'] || !empty($data['bank_err']) || !empty($data['amount_err']))){
                $this->view('deposits/add',$data);
                exit;
            }

            if(!$this->depositmodel->CreateUpdate($data)){
                flash('deposit_msg','There was an error creating the deposit. Please try again','alert custom-danger alert-dismissible fade show');
                redirect('deposits');
                exit;
            }

            flash('deposit_msg','Saved successfully');
            redirect('deposits');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function edit($id)
    {
        $deposit = $this->depositmodel->GetDeposit($id);
        $data= [
            'banks' => $this->depositmodel->GetBanks(),
            'title' => 'Edit deposit',
            'id' => $deposit->ID,
            'touched' => false,
            'isedit' => true,
            'date' => $deposit->DepositDate,
            'bank' => $deposit->BankId,
            'reference' => strtoupper($deposit->Reference),
            'description' => strtoupper($deposit->Description),
            'amount' => $deposit->Amount,
            'date_err' => '',
            'bank_err' => '',
            'reference_err' => '',
            'amount_err' => '',
        ];
        $this->view('deposits/add',$data);
        exit;
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $id = trim($_POST['id']);

            if(empty($id)){
                flash('deposit_msg','Unable to get selected deposit','alert custom-danger alert-dismissible fade show');
                redirect('deposits');
                exit;
            }

            if(!$this->depositmodel->Delete($id)){
                flash('deposit_msg','Unable to delete selected deposit! Try again or contact admin','alert custom-danger alert-dismissible fade show');
                redirect('deposits');
                exit;
            }

            flash('deposit_msg','Deleted successfully!');
            redirect('deposits');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}