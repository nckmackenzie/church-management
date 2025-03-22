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
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'group' => isset($_GET['group']) && !empty(trim($_GET['group'])) ? (int)trim($_GET['group']) : '',
                'date' => isset($_GET['date']) && !empty(trim($_GET['date'])) ? date('Y-m-d',strtotime(trim($_GET['date']))) : '',
                'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? strtolower(trim($_GET['type'])) : 'group',
            ];

            if(empty($data['group']) || empty($data['date'])) : 
                http_response_code(400);
                echo json_encode(['message' => 'Provided all requied details']);
            endif;

            echo json_encode($this->fundmodel->GetBalance($data['group'],$data['date'],$data['type']));
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
                'title' => converttobool($_POST['isedit']) ? 'Edit requisition' : 'Add requisition',
                'reqno' => trim($_POST['reqno']),
                'groups' => $this->fundmodel->GetGroups(),
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

            //validate
            if(empty($data['reqdate']) || empty($data['group']) || empty($data['amount']) 
               || empty($data['reason'])){
               $data['errmsg'] = 'Fill all required fields';
            }

            if(($data['amount'] > $data['availableamount']) && !$data['dontdeduct']){
                $data['errmsg'] = 'Requesting more than is available';
            }

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
        if((int)$request->Status > 0){
            redirect('users/deniedaccess');
            exit;
        }
        checkcenter($request->CongregationId);
        $data = [
            'title' => 'Edit requisition',
            'groups' => $request->RequestType === 'group' ? $this->fundmodel->GetGroups() : $this->fundmodel->GetDistricts(),
            'reqno' => $request->ReqNo,
            'type' => $request->RequestType,
            'id' => $request->ID,
            'isedit' => true,
            'reqdate' => $request->RequisitionDate,
            'group' => $request->GroupId,
            'amount' => $request->AmountRequested,
            'availableamount' => floatval($this->fundmodel->GetBalance($request->GroupId,$request->RequisitionDate,$request->RequestType)) + floatval($request->AmountRequested),
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
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : '';
            //no id
            if(empty($id)){
                flash('request_msg', 'Unable to get selected request!','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds');
                exit;
            }
            //if fund approved or rejected
            if((int)$this->fundmodel->GetRequestStatus($id) !== 0):
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
        if((int)$request->Status > 0){
            redirect('users/deniedaccess');
            exit;
        }
        checkcenter($request->CongregationId);
        $data = [
            'title' => 'Approve requisition',
            'paymethods' => $this->fundmodel->PayMethods(),
            'banks' => $this->fundmodel->GetBanks(),
            'type' => $request->RequestType,
            'groupid' =>  $request->RequestType === 'group' ? $request->GroupId : $request->DistrictId, 
            'group' => '',
            'id' => $request->ID,
            'reqno' => $request->ReqNo,
            'reqdate' => date('d/m/Y',strtotime($request->RequisitionDate)),
            'amount' => number_format($request->AmountRequested,2),
            // 'availableamount' => number_format(floatval($this->fundmodel->GetBalance($request->GroupId,$request->RequisitionDate,$request->RequestType) + floatval($request->AmountRequested)),2),
            'availableamount' => number_format(floatval($this->fundmodel->GetBalance($request->GroupId,$request->RequisitionDate,$request->RequestType)),2),
            'reason' => strtoupper($request->Purpose),
            'dontdeduct' => $request->DontDeduct,
            'approved' => '',
            'bank' => '',
            'balance' => '',
            'paydate' => '',
            'paymethod' => 3,
            'reference' => '',
            'errmsg' => '',
        ];
        $data['group'] = strtoupper($this->fundmodel->GetGroupName($request->RequestType,$data['groupid']));
        $this->view('groupfunds/approve',$data);
        exit;
    }

    public function approvefunds()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'title' => 'Approve requisition',
                'paymethods' => $this->fundmodel->PayMethods(),
                'banks' => $this->fundmodel->GetBanks(),
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
                'bank' => isset($_POST['bank']) && !empty($_POST['bank']) ? trim($_POST['bank']) : null,
                'reference' => isset($_POST['reference']) && !empty(trim($_POST['reference'])) ? trim($_POST['reference']) : '',
                'dontdeduct' => isset($_POST['dontdeduct']) && !empty(trim($_POST['dontdeduct'])) ? converttobool($_POST['dontdeduct']) : false,
                'reason' => trim($_POST['reason']),
                'errmsg' => '',
            ];

            
            if(empty($data['paydate']) || empty($data['approved']) || empty($data['paymethod']) 
               || empty($data['reference'])){
                $data['errmsg'] = 'Fill all required field';
            }
            if($data['paymethod'] > 2 && is_null($data['bank'])){
                $data['errmsg'] = 'Select bank';
            }
            //validate date
            // if($data['reqdate'] > $data['paydate']){
            //     $data['errmsg'] = 'Payment date earlier than Requisition date';
            // }
            if($data['approved'] > $data['amount']){
                $data['errmsg'] = 'Payment amount greater than amount requested';
            }

            if(!empty($data['errmsg'])){
                $this->view('groupfunds/approve',$data);
                exit;
            }

            $approval = $this->fundmodel->Approve($data);
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

    public function reverse()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : '';
            //no id
            if(empty($id)){
                flash('approval_msg', 'Unable to get selected approval!','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
            }
            
            //unable to delete
            if(!$this->fundmodel->Reverse($id)){
                flash('approval_msg', 'Unable to delete selected request','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
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
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : '';
            $reason = isset($_POST['reason']) && !empty(trim($_POST['reason'])) ? strtolower(trim($_POST['reason'])) : null;
            //no id
            if(empty($id)){
                flash('approval_msg', 'Unable to get selected approval!','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
            }
            
            //unable to delete
            $response = $this->fundmodel->Reject($id,$reason);
            if(!$response){
                flash('approval_msg', 'Unable to delete selected request','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
            }

            $rejectionreason = is_null($reason) ? 'No reason for rejection specified' : $reason;
            sendgeneral($response,'Your recent group fund requisition has been rejected. Reason for rejection: ' .$rejectionreason);
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
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $id = isset($_POST['uid']) && !empty(trim($_POST['uid'])) ? trim($_POST['uid']) : '';
            
            //no id
            if(empty($id)){
                flash('approval_msg', 'Unable to get selected approval!','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
            }
            
            //unable to delete
            $response = $this->fundmodel->Unapprove($id);
            if(!$response){
                flash('approval_msg', 'Unable to delete selected request','alert custom-danger alert-dismissible fade show');
                redirect('groupfunds/approvals');
                exit;
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