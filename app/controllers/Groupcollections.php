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

    /** Same rule as checkrights(), for the JSON endpoints (they answer 403 instead of redirecting). */
    private function allowed()
    {
        return !(isset($_SESSION['userType']) && (int)$_SESSION['userType'] > 2 && (int)$_SESSION['userType'] !== 6
                 && !$this->authmodel->CheckRights('group collections'));
    }

    private function denyJson()
    {
        http_response_code(403);
        echo json_encode(['success' => false,'message' => 'Access denied']);
        exit;
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
        checkrights($this->authmodel,'group collections');
        $data = [
            'id' => '',
            'isedit' => false,
            'tdate' => date('Y-m-d',strtotime($_SESSION['processdate'])),
            'type' => '',
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

    public function getgroupordistrict()
    {
        if(!$this->allowed()) $this->denyJson();
        $type = isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null;
        $data = [];

        if(is_null($type)):
            http_response_code(400);
            echo json_encode(['message' => 'Select group']);
            exit;
        endif;

        $groupsordistricts = $this->collectionmodel->GetDistrictOrGroup($type);
        foreach($groupsordistricts as $grpdist):
            array_push($data,[
                'id' => $grpdist->ID,
                'label' => strtoupper($grpdist->ColumnName)
            ]);
        endforeach;

        echo json_encode($data);
    }

    public function getsubaccounts()
    {
        if(!$this->allowed()) $this->denyJson();
        $type = isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null;
        $groupid = isset($_GET['groupid']) && !empty(trim($_GET['groupid'])) ? trim($_GET['groupid']) : null;
        $data = [];

        if(is_null($groupid) || is_null($type)):
            http_response_code(400);
            echo json_encode(['message' => 'Select group']);
            exit;
        endif;

        $subaccounts = $this->collectionmodel->GetSubAccounts($type,$groupid);
        foreach($subaccounts as $account):
            array_push($data,[
                'id' => $account->ID,
                'label' => strtoupper($account->AccountName)
            ]);
        endforeach;

        echo json_encode($data);
    }

    /** Display only: the account and bank shown after choosing a sub account. The save never reads them. */
    public function getaccountdetails()
    {
        if(!$this->allowed()) $this->denyJson();
        $subaccount = isset($_GET['subaccount']) && !empty(trim($_GET['subaccount'])) ? trim($_GET['subaccount']) : null;

        if(is_null($subaccount)):
            http_response_code(400);
            echo json_encode(['message' => 'Select group']);
            exit;
        endif;

        $accountdetails = $this->collectionmodel->GetAccountDetails($subaccount);

        echo json_encode(['accountid' => $accountdetails[0],'accountname' => $accountdetails[1],'bankid' => $accountdetails[2]]);
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            if(!$this->allowed()) $this->denyJson();
            $fields = json_decode(file_get_contents('php://input'));
            // only these fields are read; account, accountid and bankid are ignored (the server reads them from the sub account)
            $data = [
                'id' => isset($fields->id) && !empty(trim($fields->id)) ? (int)trim($fields->id) : null,
                'isedit' => converttobool($fields->isedit ?? false),
                'tdate' => isset($fields->tdate) && !empty(trim($fields->tdate)) ? date('Y-m-d',strtotime(trim($fields->tdate))) : null,
                'type' => isset($fields->type) && !empty(trim($fields->type)) ? strtolower(trim($fields->type)) : null,
                'groupid' => isset($fields->groupid) && !empty(trim($fields->groupid)) ? (int)trim($fields->groupid) : null,
                'amount' => isset($fields->amount) && !empty(trim($fields->amount)) ? trim($fields->amount) : null,
                'narration' => isset($fields->narration) && !empty(trim($fields->narration)) ? strtolower(trim($fields->narration)) : null,
                'subaccount' => isset($fields->subaccount) && !empty(trim($fields->subaccount)) ? (int)trim($fields->subaccount) : null,
            ];

            if(is_null($data['tdate']) || is_null($data['groupid']) || is_null($data['amount'])
               || is_null($data['subaccount']) || is_null($data['narration']) || is_null($data['type'])){

               http_response_code(400);
               echo json_encode(['success' => false,'message' => 'Fill all required fields']);
               exit;
            }

            $result = $this->collectionmodel->CreateUpdate($data);
            if(!$result['success']){
                http_response_code(!empty($result['errors']) ? 422 : 500);
                echo json_encode(['success' => false,'message' => !empty($result['errors']) ? implode('. ',$result['errors']) : 'Unable to save this transaction. Contact admin']);
                exit;
            }

            if($data['isedit']){
                flash('collection_msg','Saved successfully!');     // shown by the list the browser is sent back to after an edit
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
        checkrights($this->authmodel,'group collections');
        $transaction = $this->collectionmodel->GetCollection($id);
        if(!$transaction){
            flash('collection_msg','Group collection not found','alert custom-danger');
            redirect('groupcollections');
            exit;
        }
        checkcenter($transaction->CongregationId);
        $accountdetails = $this->collectionmodel->GetAccountDetails($transaction->SubAccountId);
        $type = $transaction->Type;
        $groupid = $type == 'group' ? $transaction->GroupId : $transaction->DistrictId;
        $data = [
            'id' => $transaction->ID,
            'isedit' => true,
            'tdate' => date('Y-m-d',strtotime($transaction->TransactionDate)),
            'type' => $type,
            'groups' => $this->collectionmodel->GetDistrictOrGroup($type),
            'groupid' => $groupid,
            'amount' => $transaction->Debit,
            'narration' => strtoupper($transaction->Narration),
            'subaccounts' => $this->collectionmodel->GetSubAccounts($type, $groupid),
            'subaccount' => $transaction->SubAccountId,
            'accountid' => $accountdetails[0],
            'account' => $accountdetails[1],
            'bankid' => $accountdetails[2],
        ];
        $this->view('groupcollections/add',$data);
        exit;
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            checkrights($this->authmodel,'group collections');
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? (int)trim($_POST['id']) : null;

            if(is_null($id)){
                flash('collection_msg','Unable to get selected transaction!','alert custom-danger');
                redirect('groupcollections');
                exit;
            }

            $result = $this->collectionmodel->Delete($id);
            if(!$result['success']){
                flash('collection_msg',!empty($result['errors']) ? implode('. ',$result['errors']) : 'Unable to delete selected transaction!','alert custom-danger');
                redirect('groupcollections');
                exit;
            }

            flash('collection_msg','Group collection deleted successfully!');
            redirect('groupcollections');
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit();
        }
    }
}
