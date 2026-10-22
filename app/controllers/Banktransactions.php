<?php

class Banktransactions extends Controller
{
    private $authmodel;
    private $bankmodel;
    private $reusemodel;
    public function __construct()
    {
       if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
       }
       $this->authmodel = $this->model('Auth');
       checkrights($this->authmodel,'bank transactions');
       $this->reusemodel = $this->model('Reusables');
       $this->bankmodel = $this->model('Banktransaction');
    }

    public function index()
    {
        $data = ['transactions' => $this->bankmodel->GetTransactions()];
        $this->view('banktransactions/index',$data);
        exit;
    }

    public function add()
    {
        $data= [
            'banks' => $this->bankmodel->GetBanks(),
            'accounts' => $this->bankmodel->GetOtherBanks(),
            'cashaccounts' => $this->bankmodel->GetCashAccounts(),
            'cashaccount' => '',
            'subaccounts' => $this->bankmodel->GetAllSubaccounts(),
            'title' => 'Add transaction',
            'id' => '',
            'touched' => false,
            'isedit' => false,
            'type' => '',
            'date' => '',
            'bank' => '',
            'reference' => '',
            'description' => '',
            'amount' => '',
            'transfer' => '',
            'deposittosubs' => false,
            // 'subaccounts' => ''
        ];
        $this->view('banktransactions/add',$data);
        exit;
    }

    public function getsubaccounts()
    {
        $bankid = isset($_GET['bankid']) && !empty(trim($_GET['bankid'])) ? (int)trim($_GET['bankid']) : null;

        if(is_null($bankid)){
            http_response_code(400);
            echo json_encode(['success' => false,'message' => 'Select bank']);
            exit;
        }

        $accounts = $this->bankmodel->GetSubaccounts($bankid);
        echo json_encode(['success' => true,'data' => $accounts]);
        exit;
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $fields = json_decode(file_get_contents('php://input'));
            $data = [
                'id' => isset($fields->id) && !empty(trim($fields->id)) ? (int)trim($fields->id) : null,
                'accounts' => $this->bankmodel->GetOtherBanks(),
                'isedit' => converttobool($fields->isedit),
                'date' => isset($fields->date) && !empty(trim($fields->date)) ? date('Y-m-d',strtotime($fields->date)) : null,
                'bank' => isset($fields->bank) && !empty(trim($fields->bank)) ? trim($fields->bank) : null,
                'type' => isset($fields->type) && !empty(trim($fields->type)) ? (int)trim($fields->type) : null,
                'transfer' => isset($fields->transferto) && !empty(trim($fields->transferto)) ? trim($fields->transferto) : null,
                'cashaccount' => isset($fields->cashaccount) && !empty(trim($fields->cashaccount)) ? (int)trim($fields->cashaccount) : null,
                'amount' => isset($fields->amount) && !empty(trim($fields->amount)) ? trim($fields->amount) : null,
                'reference' => isset($fields->reference) && !empty(trim($fields->reference)) ? trim($fields->reference) : null,
                'description' => isset($fields->description) && !empty(trim($fields->description)) ? trim($fields->description) : null,
                'deposittosubs' => isset($fields->deposittosubs) && trim($fields->deposittosubs) === 'on' ? true : false,
                'subaccounts' => isset($fields->deposittosubs) && trim($fields->deposittosubs) === 'on' ? $fields->subaccounts : null,
                'issubmain' => false,
                'issubtransfer' => false,
                'issubtxn' => false
            ];

            if(is_null($data['date']) || is_null($data['bank']) || is_null($data['type'])
               || is_null($data['amount']) || is_null($data['reference'])){

               http_response_code(400);
               echo json_encode(['success' => false,'message' => 'Fill all required fields']);
               exit;
            }

            if($amounterr = Ledger::amountError($data['amount'])){
               http_response_code(400);
               echo json_encode(['success' => false,'message' => $amounterr]);
               exit;
            }

            // the crediting account only applies to deposits and withdrawals
            if(!in_array($data['type'],[1,2],true)){
                $data['cashaccount'] = null;
            }elseif(is_null($data['cashaccount'])){
               http_response_code(400);
               echo json_encode(['success' => false,'message' => 'Select the crediting account']);
               exit;
            }

            if($data['type'] === 5 && is_null($data['transfer'])){
               http_response_code(400);
               echo json_encode(['success' => false,'message' => 'Select account to transfer to']);
               exit;
            }

            if($data['type'] === 5 && (int)$data['transfer'] === (int)$data['bank']){
               http_response_code(400);
               echo json_encode(['success' => false,'message' => 'Transfering accounts cannot be same']);
               exit;
            }

            if(count(explode('-',$data['bank'])) > 1){
                $data['issubmain'] = true;
            }

            if(!is_null($data['transfer']) && count(explode('-',$data['transfer'])) > 1){
                $data['issubtransfer'] = true;
            }

            if($data['issubmain'] && $data['type'] !== 5){
               http_response_code(400);
               echo json_encode(['success' => false,'message' => 'Sub accounts can only transfer to other sub accounts.']);
               exit;
            }

            if($data['issubmain'] && $data['type'] === 5 && !$data['issubtransfer']){
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Sub accounts can only transfer to other sub accounts.']);
                exit;
            }

            if(!$data['issubmain'] && $data['type'] === 5 && $data['issubtransfer']){
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Main account cannot transfer to sub accounts.']);
                exit;
            }

            if($data['issubmain'] && $data['issubtransfer']){
                $data['issubtxn'] = true;
                $data['type'] = 6;
                $data['transfer'] = (int)explode('-',$data['transfer'])[0];
                $data['bank'] = (int)explode('-',$data['bank'])[0];
            }

            // echo json_encode($data);

            $result = $this->bankmodel->CreateUpdate($data);
            if(!$result['success']){
                if($result['errors']){
                    http_response_code(422);
                    echo json_encode(['success' => false,'message' => implode('<br>',array_map('htmlspecialchars',$result['errors']))]);
                }else{
                    http_response_code(500);
                    echo json_encode(['success' => false,'message' => 'Unable to save this transaction. Contact admin']);
                }
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

    public function edit($id)
    {
        $transaction = $this->bankmodel->GetTransaction($id);
        if(!$transaction){
            flash('banktransaction_msg','Transaction not found or already deleted','alert custom-danger');
            redirect('banktransactions');
            exit;
        }
        checkcenter($transaction->CongregationId);
        $data= [
            'banks' => $this->bankmodel->GetBanks(),
            // 'accounts' => $this->reusemodel->GetAccountsAll(),
            'accounts' => $this->bankmodel->GetOtherBanks(),
            'cashaccounts' => $this->bankmodel->GetCashAccounts(),
            'cashaccount' => $transaction->CashAccountId,
            'title' => 'Edit transaction',
            'id' => $transaction->ID,
            'touched' => false,
            'isedit' => true,
            'date' => date('Y-m-d',strtotime($transaction->TransactionDate)),
            'bank' => $transaction->BankId,
            'reference' => strtoupper($transaction->Reference),
            'description' => strtoupper($transaction->Description),
            'amount' => $transaction->Amount,
            'type' => $transaction->TransactionTypeId,
            'transfer' => $transaction->TransferToId,
            'deposittosubs' => $transaction->DepositToSubAccounts,
            'subaccounts' => (int)$transaction->DepositToSubAccounts === 1 ? $this->bankmodel->GetSavedSubsAccounts($transaction->ID) : null
        ];
        $this->view('banktransactions/add',$data);
        exit;
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? (int)trim($_POST['id']) : null;

            $result = $this->bankmodel->Delete($id);
            if(!$result['success']){
                $msg = $result['errors'] ? implode('<br>',array_map('htmlspecialchars',$result['errors'])) : 'Unable to delete transaction!';
                flash('banktransaction_msg',$msg,'alert custom-danger');
                redirect('banktransactions');
                exit;
            }

            flash('banktransaction_msg',"Deleted successfully!");
            redirect('banktransactions');
            exit;

        }
        else
        {
            redirect('users/deniedaccess');
            exit();
        }
    }
}