<?php
class Groupfunds extends Controller
{
    private $authmodel;
    private $fundmodel;
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        $this->fundmodel = $this->model('Groupfund');
    }

    /** Same rule as checkrights(), for the JSON endpoints (they answer 403 as JSON instead of redirecting to an HTML page). */
    private function allowed($form)
    {
        return !(isset($_SESSION['userType']) && (int)$_SESSION['userType'] > 2 && (int)$_SESSION['userType'] !== 6
                 && !$this->authmodel->CheckRights($form));
    }

    private function denyJson()
    {
        http_response_code(403);
        echo json_encode(['success' => false,'message' => 'Access denied']);
        exit;
    }

    public function index()
    {
        checkrights($this->authmodel,'fund requisition');
        $data = [
            'requests' => $this->fundmodel->GetRequests(),
        ];
        $this->view('groupfunds/index',$data);
        exit;
    }

    public function add()
    {
        checkrights($this->authmodel,'fund requisition');
        $data = [
            'title' => 'Add requisition',
            'groups' => $this->fundmodel->GetGroups(),
            'reqno' => $this->fundmodel->GetReqNo(),
            'id' => '',
            'type' => '',
            'isedit' => false,
            'availableamount' => '',
            'errmsg' => '',
            'group' => '',
            'amount' => '',
            'reason' => '',
            'reqdate' => '',
            'dontdeduct' => false
        ];
        $this->view('groupfunds/add',$data);
        exit;
    }

    public function getamountavailable()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            if(!$this->allowed('fund requisition')) $this->denyJson();
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'group' => isset($_GET['group']) && !empty(trim($_GET['group'])) ? (int)trim($_GET['group']) : '',
                'date' => isset($_GET['date']) && !empty(trim($_GET['date'])) ? date('Y-m-d',strtotime(trim($_GET['date']))) : '',
                'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? strtolower(trim($_GET['type'])) : 'group',
            ];

            if(empty($data['group']) || empty($data['date'])) :
                http_response_code(400);
                echo json_encode(['message' => 'Provided all requied details']);
                exit;
            endif;

            echo json_encode($this->fundmodel->GetBalance($data['group'],$data['date'],$data['type']));
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    /**
     * The group / district / church category list of the requisition form (JSON [{id,label}]). It has its own endpoint and
     * its own right: the form must not depend on the 'group collections' right of Groupcollections::getgroupordistrict().
     */
    public function getgroupordistrict()
    {
        if(!$this->allowed('fund requisition')) $this->denyJson();
        $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
        if(!in_array($type,['group','district','church'],true)){
            http_response_code(400);
            echo json_encode(['success' => false,'message' => 'Select group, district or church']);
            exit;
        }
        $data = [];
        foreach($this->fundmodel->GetOwners($type) as $o){
            $data[] = ['id' => $o->ID,'label' => strtoupper($o->itemName)];
        }
        echo json_encode($data);
        exit;
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            checkrights($this->authmodel,'fund requisition');
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'title' => converttobool($_POST['isedit']) ? 'Edit requisition' : 'Add requisition',
                'reqno' => trim($_POST['reqno']),
                'groups' => $this->fundmodel->GetOwners(!empty(trim($_POST['type'])) ? strtolower(trim($_POST['type'])) : 'group'),
                'id' => trim($_POST['id']),
                'isedit' => converttobool($_POST['isedit']) ,
                'reqdate' => !empty(trim($_POST['date'])) ? date('Y-m-d',strtotime(trim($_POST['date']))) : '',
                'type' => !empty(trim($_POST['type'])) ? strtolower(trim($_POST['type'])) : 'group',
                'group' => !empty(trim($_POST['group'])) ? trim($_POST['group']) : '',
                'availableamount' => !empty(trim($_POST['availableamount'])) ? floatval(trim($_POST['availableamount'])) : '',
                'amount' => !empty(trim($_POST['amount'])) ? floatval(trim($_POST['amount'])) : '',
                'reason' => !empty(trim($_POST['reason'])) ? trim($_POST['reason']) : '',
                'dontdeduct' => isset($_POST['dontdeduct']) ? true : false,
                'errmsg' => '',
            ];

            // an edit must be of a pending requisition of this congregation
            $existing = null;
            if($data['isedit']){
                $existing = $this->fundmodel->GetRequest((int)$data['id']);
                if(!$existing){
                    redirect('users/deniedaccess');
                    exit;
                }
                checkcenter($existing->CongregationId);
                if((int)$existing->Status > 0 || (int)$existing->Deleted === 1){
                    redirect('users/deniedaccess');
                    exit;
                }
            }

            //validate
            if(empty($data['reqdate']) || empty($data['group']) || empty($data['amount'])
               || empty($data['reason'])){
               $data['errmsg'] = 'Fill all required fields';
            }elseif(!is_numeric($data['amount']) || $data['amount'] <= 0){
               $data['errmsg'] = 'Enter a valid amount';
            }elseif($problem = $this->fundmodel->OwnerProblem($data['type'],$data['group'],$existing)){
               $data['errmsg'] = $problem;
            }

            // AUDIT-FLAG [LEDGER AUDIT / tblledger]: Over-request validation DISABLED on 2026-10-20 at the user's request.
            // Requisitions where AmountRequested > available group/district balance can now be saved, so any
            // ledger audit (tblledger) must check for groups/districts whose funds were overdrawn by these requests.
            // Client-side counterpart also disabled: public/dist/js/pages/groupfunds/add-v4.js (otherValidation).
            // if(($data['amount'] > $data['availableamount']) && !$data['dontdeduct'] && $data['type'] === 'group'){
            //     $data['errmsg'] = 'Requesting more than is available';
            // }

            if($data['reqdate'] > date('Y-m-d')){
                $data['errmsg'] = 'Invalid request date';
            }

            if(!empty($data['errmsg'])){
                $this->view('groupfunds/add',$data);
                exit;
            }

            // if(!$data['isedit'] && (int)$this->fundmodel->PendingApprovalCount($data['group']) > 0){
            //     $data['errmsg'] = 'Group has pending requisition for approval';
            //     $this->view('groupfunds/add',$data);
            //     exit;
            // }

            if(!$this->fundmodel->CreateUpdate($data)){
                $data['errmsg'] = 'Unable to save this request. Contact admin for help';
                $this->view('groupfunds/add',$data);
                exit;
            }

            flash('request_msg','Saved successfully!');
            redirect('groupfunds');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function edit($id)
    {
        checkrights($this->authmodel,'fund requisition');
        $request = $this->fundmodel->GetRequest($id);
        if(!$request || (int)$request->Status > 0){
            redirect('users/deniedaccess');
            exit;
        }
        checkcenter($request->CongregationId);
        $data = [
            'title' => 'Edit requisition',
            'groups' => $this->fundmodel->GetOwners($request->RequestType),
            'reqno' => $request->ReqNo,
            'type' => $request->RequestType,
            'id' => $request->ID,
            'isedit' => true,
            'reqdate' => $request->RequisitionDate,
            'group' => $request->RequestType === 'group' ? $request->GroupId : ($request->RequestType === 'district' ? $request->DistrictId : $request->ChurchCategoryId),
            'amount' => $request->AmountRequested,
            'availableamount' => floatval($this->fundmodel->GetBalance($this->fundmodel->OwnerId($request),$request->RequisitionDate,$request->RequestType)) + floatval($request->AmountRequested),
            'reason' => strtoupper($request->Purpose),
            'dontdeduct' => converttobool($request->DontDeduct),
            'errmsg' => '',
        ];
        $this->view('groupfunds/add',$data);
        exit;
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            checkrights($this->authmodel,'fund requisition');
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : '';
            //no id
            if(empty($id)){
                flash('request_msg', 'Unable to get selected request!','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds');
                exit;
            }
            $request = $this->fundmodel->GetRequest((int)$id);
            if(!$request){
                redirect('users/deniedaccess');
                exit;
            }
            checkcenter($request->CongregationId);
            //if fund approved or rejected
            if((int)$request->Status !== 0):
                flash('request_msg', 'Denied! Cannot delete this request','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds');
                exit;
            endif;

            //unable to delete
            if(!$this->fundmodel->Delete($id)){
                flash('request_msg', 'Unable to delete selected request','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds');
                exit;
            }

            flash('request_msg', 'Deleted successfully!');
            redirect('groupfunds');
            exit;
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function approvals()
    {
        checkrights($this->authmodel,'fund approval');
        $data = [
            'approvals' => $this->fundmodel->GetApprovals(),
        ];
        $this->view('groupfunds/approvals',$data);
        exit;
    }

    public function approve($id)
    {
        checkrights($this->authmodel,'fund approval');
        $request = $this->fundmodel->GetRequest($id);
        if(!$request || (int)$request->Status > 0){
            redirect('users/deniedaccess');
            exit;
        }
        checkcenter($request->CongregationId);
        $ownerid = $this->fundmodel->OwnerId($request);
        $data = [
            'title' => 'Approve requisition',
            'paymethods' => $this->fundmodel->PayMethods(),
            'cashaccounts' => $this->fundmodel->GetCashAccounts(),
            'bankaccounts' => $this->fundmodel->GetBankAccounts(),
            'type' => $request->RequestType,
            'groupid' =>  $ownerid,
            'group' => '',
            'id' => $request->ID,
            'reqno' => $request->ReqNo,
            'reqdate' => date('d/m/Y',strtotime($request->RequisitionDate)),
            'amount' => number_format($request->AmountRequested,2),
            'availableamount' => number_format(floatval($this->fundmodel->GetBalance($ownerid,$request->RequisitionDate,$request->RequestType)),2),
            'reason' => strtoupper($request->Purpose),
            'dontdeduct' => $request->DontDeduct,
            'approved' => '',
            'paidfrom' => Groupfund::DEFAULT_PAID_FROM,
            'balance' => '',
            'paydate' => '',
            'paymethod' => Groupfund::CASH_METHOD,
            'reference' => '',
            'errmsg' => '',
        ];
        $data['group'] = strtoupper($this->fundmodel->GetGroupName($request->RequestType,$ownerid ?? $request->ChurchCategoryId));
        $this->view('groupfunds/approve',$data);
        exit;
    }

    public function approvefunds()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            checkrights($this->authmodel,'fund approval');
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $request = $this->fundmodel->GetRequest(isset($_POST['id']) ? (int)$_POST['id'] : 0);
            if(!$request){
                redirect('users/deniedaccess');
                exit;
            }
            checkcenter($request->CongregationId);
            // group / district, type, DontDeduct and the requested amount are the stored row's; the posted copies are only shown again on an error
            $data = [
                'title' => 'Approve requisition',
                'paymethods' => $this->fundmodel->PayMethods(),
                'cashaccounts' => $this->fundmodel->GetCashAccounts(),
                'bankaccounts' => $this->fundmodel->GetBankAccounts(),
                'reqno' => trim($_POST['reqno']),
                'groupid' => trim($_POST['groupid']),
                'type' => isset($_POST['type']) && !empty(trim($_POST['type'])) ? trim($_POST['type']) : 'group',
                'id' => isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : '',
                'group' => isset($_POST['group']) && !empty(trim($_POST['group'])) ? trim($_POST['group']) : '',
                'reqdate' => isset($_POST['date']) && !empty(trim($_POST['date'])) ? date('Y-m-d',strtotime(trim($_POST['date']))) : '',
                'paydate' => isset($_POST['paydate']) && !empty(trim($_POST['paydate'])) ? date('Y-m-d',strtotime(trim($_POST['paydate']))) : '',
                'availableamount' => isset($_POST['availableamount']) && !empty(trim($_POST['availableamount'])) ? floatval(numberFormat($_POST['availableamount'])) : '',
                'amount' => isset($_POST['amount']) && !empty(trim($_POST['amount'])) ? floatval(numberFormat(trim($_POST['amount']))) : '',
                'approved' => isset($_POST['approved']) && !empty(trim($_POST['approved'])) ? floatval(trim($_POST['approved'])) : '',
                'balance' => isset($_POST['balance']) && !empty(trim($_POST['balance'])) ? floatval(trim($_POST['balance'])) : '',
                'paymethod' => isset($_POST['paymethod']) && !empty(trim($_POST['paymethod'])) ? (int)trim($_POST['paymethod']) : null,
                'paidfrom' => isset($_POST['paidfrom']) && !empty(trim($_POST['paidfrom'])) ? (int)trim($_POST['paidfrom']) : null,
                'reference' => isset($_POST['reference']) && !empty(trim($_POST['reference'])) ? trim($_POST['reference']) : '',
                'dontdeduct' => isset($_POST['dontdeduct']) && !empty(trim($_POST['dontdeduct'])) ? converttobool($_POST['dontdeduct']) : false,
                'reason' => trim($_POST['reason']),
                'errmsg' => '',
            ];


            if(empty($data['paydate']) || empty($data['approved']) || empty($data['paymethod'])
               || empty($data['reference']) || empty($data['paidfrom'])){
                $data['errmsg'] = 'Fill all required field';
            }
            if($data['approved'] > $data['amount']){
                $data['errmsg'] = 'Payment amount greater than amount requested';
            }

            if(!empty($data['errmsg'])){
                $this->view('groupfunds/approve',$data);
                exit;
            }

            $approval = $this->fundmodel->Approve($data);
            if(is_array($approval)){
                if($approval['final']){
                    flash('approval_msg',$approval['error'],'alert custom-danger alert-dismissible fade show');
                    redirect('groupfunds/approvals');
                    exit;
                }
                $data['errmsg'] = $approval['error'];
                $this->view('groupfunds/approve',$data);
                exit;
            }
            if(!$approval){
                $data['errmsg'] = 'Unable to save this approval. Contact admin for help';
                $this->view('groupfunds/approve',$data);
                exit;
            }

            // sendgeneral($approval,'Your recent group fund requisition has been approved.');
            flash('approval_msg','Approved successfully!');
            redirect('groupfunds/approvals');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    /** Flash the refusal of reverse / reject / unapprove and go back to the approvals list. */
    private function actionFailed($result,$fallback)
    {
        flash('approval_msg',is_array($result) ? $result['error'] : $fallback,'alert custom-danger alert-dismissible fade show');
        redirect('groupfunds/approvals');
        exit;
    }

    public function reverse()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            checkrights($this->authmodel,'fund approval');
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? (int)trim($_POST['id']) : '';
            //no id
            if(empty($id)){
                flash('approval_msg', 'Unable to get selected approval!','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
            }

            $result = $this->fundmodel->Reverse($id);
            if(is_array($result) || !$result){
                $this->actionFailed($result,'Unable to reverse selected request');
            }

            flash('approval_msg', 'Reveresed successfully!');
            redirect('groupfunds/approvals');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function reject()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            checkrights($this->authmodel,'fund approval');
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? (int)trim($_POST['id']) : '';
            $reason = isset($_POST['reason']) && !empty(trim($_POST['reason'])) ? strtolower(trim($_POST['reason'])) : null;
            //no id
            if(empty($id)){
                flash('approval_msg', 'Unable to get selected approval!','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
            }

            $response = $this->fundmodel->Reject($id,$reason);
            if(is_array($response) || !$response){
                $this->actionFailed($response,'Unable to reject selected request');
            }

            $rejectionreason = is_null($reason) ? 'No reason for rejection specified' : $reason;
            if(is_string($response)){
                sendgeneral($response,'Your recent group fund requisition has been rejected. Reason for rejection: ' .$rejectionreason);
            }
            flash('approval_msg', 'Rejected successfully!');
            redirect('groupfunds/approvals');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function unapprove()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            checkrights($this->authmodel,'fund approval');
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $id = isset($_POST['uid']) && !empty(trim($_POST['uid'])) ? (int)trim($_POST['uid']) : '';

            //no id
            if(empty($id)){
                flash('approval_msg', 'Unable to get selected approval!','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
            }

            $response = $this->fundmodel->Unapprove($id);
            if(is_array($response) || !$response){
                $this->actionFailed($response,'Unable to unapprove selected request');
            }

            flash('approval_msg', 'Unapproved successfully!');
            redirect('groupfunds/approvals');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}
