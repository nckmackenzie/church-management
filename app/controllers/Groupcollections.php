<?php

class Groupcollections extends Controller
{
    private $authmodel;
    private $collectionmodel;
    

    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        $this->collectionmodel = $this->model('Groupcollection');
    }

    public function index()
    {
        checkrights($this->authmodel,'group collections');
        $data = ['transactions' => $this->collectionmodel->GetTransactions()];
        $this->view('groupcollections/index',$data);
        exit;
    }

    public function add()
    {
        $data = [
            'id' => '',
            'isedit' => false,
            'tdate' => date('Y-m-d',strtotime($_SESSION['processdate'])),
            'groups' => $this->collectionmodel->GetGroups(),
            'groupid' => '',
            'amount' => '',
            'narration' => '',
            'accountid' => '',
            'account' => '',
            'bankid' => '',
            'subaccount' => ''
        ];
        $this->view('groupcollections/add',$data);
        exit;
    }

    public function getsubaccounts()
    {
        $groupid = isset($_GET['groupid']) && !empty(trim($_GET['groupid'])) ? trim($_GET['groupid']) : null;
        $data = [];

        if(is_null($groupid)):
            http_response_code(400);
            echo json_encode(['message' => 'Select group']);
        endif;

        $subaccounts = $this->collectionmodel->GetSubAccounts($groupid);
        foreach($subaccounts as $account):
            array_push($data,[
                'id' => $account->ID,
                'label' => strtoupper($account->AccountName)
            ]);
        endforeach;

        echo json_encode($data);
    }

    public function getaccountdetails()
    {
        $subaccount = isset($_GET['subaccount']) && !empty(trim($_GET['subaccount'])) ? trim($_GET['subaccount']) : null;
     
        if(is_null($subaccount)):
            http_response_code(400);
            echo json_encode(['message' => 'Select group']);
        endif;

        $accountdetails = $this->collectionmodel->GetAccountDetails($subaccount);
        
        echo json_encode(['accountid' => $accountdetails[0],'accountname' => $accountdetails[1],'bankid' => $accountdetails[2]]);
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $fields = json_decode(file_get_contents('php://input'));
            $data = [
                'id' => isset($fields->id) && !empty(trim($fields->id)) ? (int)trim($fields->id) : null,
                'isedit' => converttobool($fields->isedit),
                'tdate' => isset($fields->tdate) && !empty(trim($fields->tdate)) ? date('Y-m-d',strtotime(trim($fields->tdate))) : null,
                'groups' => $this->collectionmodel->GetGroups(),
                'groupid' => isset($fields->groupid) && !empty(trim($fields->groupid)) ? (int)trim($fields->groupid) : null,
                'amount' => isset($fields->amount) && !empty(trim($fields->amount)) ? floatval(trim($fields->amount)) : null,
                'narration' => isset($fields->narration) && !empty(trim($fields->narration)) ? strtolower(trim($fields->narration)) : null,
                'accountid' => isset($fields->accountid) && !empty(trim($fields->accountid)) ? trim($fields->accountid) : null,
                'subaccount' => isset($fields->subaccount) && !empty(trim($fields->subaccount)) ? trim($fields->subaccount) : null,
                'account' => isset($fields->account) && !empty(trim($fields->account)) ? strtolower(trim($fields->account)) : null,
                'bankid' => isset($fields->bankid) && !empty(trim($fields->bankid)) ? trim($fields->bankid) : null,
            ];

            if(is_null($data['tdate']) || is_null($data['groupid']) || is_null($data['amount']) 
               || is_null($data['subaccount']) || is_null($data['narration'])){

               http_response_code(400);
               echo json_encode(['success' => false,'message' => 'Fill all required fields']);
               exit;
            }

            if(!$this->collectionmodel->CreateUpdate($data)){
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

    public function edit($id)
    {
        $transaction = $this->collectionmodel->GetCollection($id);
        $accountdetails = $this->collectionmodel->GetAccountDetails($transaction->SubAccountId);
        checkcenter($transaction->CongregationId);
        $data = [
            'id' => $transaction->ID,
            'isedit' => true,
            'tdate' => date('Y-m-d',strtotime($transaction->TransactionDate)),
            'groups' => $this->collectionmodel->GetGroups(),
            'groupid' => $transaction->GroupId,
            'amount' => $transaction->Debit,
            'narration' => strtoupper($transaction->Narration),
            'subaccounts' => $this->collectionmodel->GetSubAccounts($transaction->GroupId),
            'subaccount' => $transaction->SubAccountId,
            'accountid' => $accountdetails[0],
            'account' => $accountdetails[1],
            'bankid' => $accountdetails[2],
        ];
        $this->view('groupcollections/add',$data);
        exit;
    }
}