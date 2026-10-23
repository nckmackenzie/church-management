<?php
class Mmfreceipts extends Controller
{
    private $authmodel;
    private $mmfmodel;

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
        $data = [
            'id' => '',
            'touched' => '',
            'groups' => $this->mmfmodel->GetGroups(),
            'banks' => $this->mmfmodel->GetBanks(),
            'fundsaccounts' => $this->mmfmodel->GetFundsHeldAccounts(),
            'isedit' => false,
            'tdate' => date('Y-m-d',strtotime($_SESSION['processdate'])),
            'balance' => '',
            'groupid' => '',
            'amount' => '',
            'bank' => '',
            'fundsheld' => '',
            'reference' => '',
            'errmsg' => '',
            'tdate_err' => '',
            'groupid_err' => '',
            'amount_err' => '',
            'reference_err' => '',
            'bank_err' => '',
            'fundsheld_err' => ''
        ];
        $this->view('mmfreceipts/add',$data);
    }

    public function getbalance()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $date = isset($_GET['date']) && !empty(trim($_GET['date'])) ? date('Y-m-d',strtotime(trim($_GET['date']))) : null;
            $groupid = isset($_GET['groupid']) && !empty(trim($_GET['groupid'])) ? (int)trim($_GET['groupid']) : null;
            $id = isset($_GET['id']) && !empty(trim($_GET['id'])) ? (int)trim($_GET['id']) : 0;

            if(is_null($date) || is_null($groupid)){
                http_response_code(400);
                echo json_encode(['balance' => 0,'message' => 'Select date and group']);
                exit;
            }

            echo json_encode(['balance' => $this->mmfmodel->GetBalance($groupid,$date,$id)]);
            exit;

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
                'id' => trim($_POST['id']),
                'groups' => $this->mmfmodel->GetGroups(),
                'banks' => $this->mmfmodel->GetBanks(),
                'fundsaccounts' => $this->mmfmodel->GetFundsHeldAccounts(),
                'isedit' => converttobool(trim($_POST['isedit'])),
                'tdate' => !empty(trim($_POST['tdate'])) ? date('Y-m-d',strtotime($_POST['tdate'])) : '',
                'groupid' => !empty(trim($_POST['groupid'])) ? trim($_POST['groupid']) : '',
                'amount' => trim($_POST['amount']),
                'bank' => !empty(trim($_POST['bank'])) ? trim($_POST['bank']) : '',
                'fundsheld' => !empty(trim($_POST['fundsheld'])) ? trim($_POST['fundsheld']) : '',
                'reference' => trim($_POST['reference']),
                'balance' => '',
                'errmsg' => '',
                'tdate_err' => '',
                'groupid_err' => '',
                'amount_err' => '',
                'reference_err' => '',
                'bank_err' => '',
                'fundsheld_err' => ''
            ];

            // quick field checks for the form; the model repeats every rule (and the balance, which is never taken from the form)
            if(empty($data['tdate'])){
                $data['tdate_err'] = 'Select date';
            }elseif($data['tdate'] > date('Y-m-d')){
                $data['tdate_err'] = 'Invalid date selected';
            }
            if(empty($data['groupid'])){
                $data['groupid_err'] = 'Select group';
            }
            if($data['amount'] === ''){
                $data['amount_err'] = 'Enter amount';
            }elseif($message = Ledger::amountError($data['amount'])){
                $data['amount_err'] = $message;
            }
            if(empty($data['bank'])){
                $data['bank_err'] = 'Select bank';
            }
            if(empty($data['fundsheld'])){
                $data['fundsheld_err'] = 'Select group funds held account';
            }
            if($message = $this->mmfmodel->ReferenceProblem($data['reference'],(int)$data['id'])){
                $data['reference_err'] = $message;
            }

            if(!empty($data['tdate_err']) || !empty($data['groupid_err']) || !empty($data['amount_err'])
                || !empty($data['reference_err']) || !empty($data['bank_err']) || !empty($data['fundsheld_err'])){

                $this->view('mmfreceipts/add',$data);
                exit();
            }

            $result = $this->mmfmodel->CreateUpdate($data);
            if(!$result['success']){
                $data['errmsg'] = !empty($result['errors']) ? implode('. ',$result['errors']) : 'Unable to save transaction. Please try again';
                $this->view('mmfreceipts/add',$data);
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
        $mmf = $this->mmfmodel->GetMmf($id);
        if(!$mmf){
            flash('mmf_msg','MMF receipt not found','alert custom-danger alert-dismissible fade show');
            redirect('mmfreceipts');
            exit();
        }
        $data = [
            'id' => $mmf->ID,
            'touched' => '',
            'groups' => $this->mmfmodel->GetGroups(),
            'banks' => $this->mmfmodel->GetBanks(),
            'fundsaccounts' => $this->mmfmodel->GetFundsHeldAccounts(),
            'isedit' => true,
            'tdate' => $mmf->TransactionDate,
            'groupid' => $mmf->GroupId,
            'amount' => $mmf->Credit,
            'bank' => $mmf->BankId,
            'fundsheld' => $this->mmfmodel->GetFundsHeldOf($mmf->ID),
            'reference' => strtoupper($mmf->Reference),
            'balance' => $this->mmfmodel->GetBalance($mmf->GroupId,$mmf->TransactionDate,$mmf->ID),
            'errmsg' => '',
            'tdate_err' => '',
            'groupid_err' => '',
            'amount_err' => '',
            'reference_err' => '',
            'bank_err' => '',
            'fundsheld_err' => ''
        ];
        $this->view('mmfreceipts/add',$data);
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $id = isset($_POST['id']) ? (int)trim($_POST['id']) : 0;

            if(empty($id)){
                flash('mmf_msg','unable to get selected MMF','alert custom-danger alert-dismissible fade show');
                redirect('mmfreceipts');
                exit();
            }

            $result = $this->mmfmodel->Delete($id);
            if(!$result['success']){
                flash('mmf_msg',!empty($result['errors']) ? implode('. ',$result['errors']) : 'unable to delete transaction. Please try again','alert custom-danger alert-dismissible fade show');
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
