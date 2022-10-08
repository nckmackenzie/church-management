<?php
class Mmfreceipts extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit();
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'mmf Receipts');
        $this->mmfmodel = $this->model('Mmfreceipt');
    }

    public function index()
    {
        $mmfs = $this->mmfmodel->GetMMFs();
        $data = [
            'mmfs' => $mmfs,
        ];
        $this->view('mmfreceipts/index',$data);
    }

    public function add()
    {
        $groups = $this->mmfmodel->GetGroups();
        $banks = $this->mmfmodel->GetBanks();
        $data = [
            'id' => '',
            'touched' => '',
            'groups' => $groups,
            'banks' => $banks,
            'isedit' => false,
            'tdate' => date('Y-m-d'),
            'balance' => '',
            'groupid' => '',
            'amount' => '',
            'bank' => '',
            'reference' => '',
            'tdate_err' => '',
            'groupid_err' => '',
            'amount_err' => '',
            'reference_err' => '',
            'bank_err' => ''
        ];
        $this->view('mmfreceipts/add',$data);
    }
    
    public function getbalance()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'date' => !empty(trim($_GET['date'])) ? date('Y-m-d',strtotime(trim($_GET['date']))) : NULL,
                'groupid' => !empty(trim($_GET['groupid'])) ? trim($_GET['groupid']) : NULL,
            ];

            $balance = $this->mmfmodel->GetBalance($data);
            
            echo json_encode(['balance' => $balance]);

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $groups = $this->mmfmodel->GetGroups();
            $banks = $this->mmfmodel->GetBanks();
            $data = [
                'id' => trim($_POST['id']),
                'groups' => $groups,
                'banks' => $banks,
                'isedit' => converttobool(trim($_POST['isedit'])),
                'tdate' => !empty(trim($_POST['tdate'])) ? date('Y-m-d',strtotime($_POST['tdate'])) : '',
                'groupid' => !empty(trim($_POST['groupid'])) ? trim($_POST['groupid']) : '',
                'amount' => trim($_POST['amount']),
                'bank' => !empty(trim($_POST['bank'])) ? trim($_POST['bank']) : '',
                'reference' => trim($_POST['reference']),
                'balance' => !empty(trim($_POST['balance'])) ? trim($_POST['balance']) : 0,
                'tdate_err' => '',
                'groupid_err' => '',
                'amount_err' => '',
                'reference_err' => '',
                'bank_err' => ''
            ];

            if(empty($data['tdate'])){
                $data['tdate_err'] = 'Select date';
            }else{
                if($data['tdate'] > date('Y-m-d')){
                    $data['tdate_err'] = 'Invalid date selected';
                }
            }

            if(empty($data['groupid'])){
                $data['groupid_err'] = 'Select group';
            }

            if(empty($data['amount'])){
                $data['amount_err'] = 'Enter amount';
            }else{
                if(floatval($data['amount']) > floatval($data['balance'])){
                    $data['amount_err'] = 'Cannot withdraw more than available amount';
                }
            }
            
            if(empty($data['bank'])){
                $data['bank_err'] = 'Select bank';
            }

            if(empty($data['reference'])){
                $data['reference_err'] = 'Enter reference';
            }else{
                if(!$this->mmfmodel->CheckRefDuplication($data['reference'],$data['id'])){
                    $data['reference_err'] = 'Reference already exists';
                }
            }

            if(!empty($data['tdate_err']) || !empty($data['groupid_err']) || !empty($data['amount_err'])
                || !empty($data['reference_err']) || !empty($data['bank_err'])){
                
                $this->view('mmfreceipts/add',$data);
                exit();
            }
           
            if(!$this->mmfmodel->CreateUpdate($data)){
                flash('mmf_msg','unable to save transaction. Please try again','alert custom-danger alert-dismissible fade show');
                redirect('mmfreceipts');
                exit();
            }

            flash('mmf_msg','Saved successfully!');
            redirect('mmfreceipts');
            exit();

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }

    public function edit($id)
    {
        $groups = $this->mmfmodel->GetGroups();
        $mmf = $this->mmfmodel->GetMmf($id);
        $banks = $this->mmfmodel->GetBanks();
        $data = [
            'id' => $mmf->ID,
            'touched' => '',
            'groups' => $groups,
            'banks' => $banks,
            'isedit' => true,
            'tdate' => $mmf->TransactionDate,
            'groupid' => $mmf->GroupId,
            'amount' => $mmf->Credit,
            'bank' => $mmf->BankId,
            'reference' => strtoupper($mmf->Reference),
            'tdate_err' => '',
            'groupid_err' => '',
            'amount_err' => '',
            'reference_err' => '',
            'bank_err' => ''
        ];
        $this->view('mmfreceipts/add',$data);
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $id = trim($_POST['id']);

            if(empty($id)){
                flash('mmf_msg','unable to get selected MMF','alert custom-danger alert-dismissible fade show');
                redirect('mmfreceipts');
                exit();
            }

            if(!$this->mmfmodel->Delete($id)){
                flash('mmf_msg','unable to delete transaction. Please try again','alert custom-danger alert-dismissible fade show');
                redirect('mmfreceipts');
                exit();
            }

            flash('mmf_msg','Deleted successfully!');
            redirect('mmfreceipts');
            exit();

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
}