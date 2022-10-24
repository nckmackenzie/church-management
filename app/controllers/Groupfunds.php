<?php
class Groupfunds extends Controller
{
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
        checkrights($this->authmodel,'group fund requisition');
        $data = [
            'requests' => $this->fundmodel->GetRequests(),
        ];
        $this->view('groupfunds/index',$data);
        exit;
    }

    public function add()
    {
        checkrights($this->authmodel,'group fund requisition');
        $data = [
            'title' => 'Add requisition',
            'groups' => $this->fundmodel->GetGroups(),
            'reqno' => $this->fundmodel->GetReqNo(),
            'id' => '',
            'isedit' => false,
            'availableamount' => '',
            'errmsg' => '',
            'group' => '',
            'amount' => '',
            'reason' => '',
            'reqdate' => '',
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
            ];

            if(empty($data['group']) || empty($data['date'])) : 
                http_response_code(400);
                echo json_encode(['message' => 'Provided all requied details']);
            endif;

            echo json_encode($this->fundmodel->GetBalance($data['group'],$data['date']));
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
                'group' => !empty(trim($_POST['group'])) ? trim($_POST['group']) : '',
                'availableamount' => !empty(trim($_POST['availableamount'])) ? floatval(trim($_POST['availableamount'])) : '',
                'amount' => !empty(trim($_POST['amount'])) ? floatval(trim($_POST['amount'])) : '',
                'reason' => !empty(trim($_POST['reason'])) ? trim($_POST['reason']) : '',
                'errmsg' => '',
            ];

            //validate
            if(empty($data['reqdate']) || empty($data['group']) || empty($data['amount']) 
               || empty($data['reason'])){
               $data['errmsg'] = 'Fill all required fields';
            }

            if($data['amount'] > $data['availableamount']){
                $data['errmsg'] = 'Requesting more than is available';
            }

            if($data['reqdate'] > date('Y-m-d')){
                $data['errmsg'] = 'Invalid request date';
            }

            if(!empty($data['errmsg'])){
                $this->view('groupfunds/add',$data);
                exit;
            }

            if(!$data['isedit'] && (int)$this->fundmodel->PendingApprovalCount($data['group']) > 0){
                $data['errmsg'] = 'Group has pending requisition for approval';
                $this->view('groupfunds/add',$data);
                exit;
            }

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
        checkrights($this->authmodel,'group fund requisition');
        $request = $this->fundmodel->GetRequest($id);
        if((int)$request->Status > 0){
            redirect('users/deniedaccess');
            exit;
        }
        checkcenter($request->CongregationId);
        $data = [
            'title' => 'Edit requisition',
            'groups' => $this->fundmodel->GetGroups(),
            'reqno' => $request->ReqNo,
            'id' => $request->ID,
            'isedit' => true,
            'reqdate' => $request->RequisitionDate,
            'group' => $request->GroupId,
            'amount' => $request->AmountRequested,
            'availableamount' => floatval($this->fundmodel->GetBalance($request->GroupId,$request->RequisitionDate)),
            'reason' => strtoupper($request->Purpose),
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
            if((int)$this->db->GetRequestStatus($id) !== 0):
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
        checkrights($this->authmodel,'group fund approval');
        $data = [
            'approvals' => $this->fundmodel->GetApprovals(),
        ];
        $this->view('groupfunds/approvals',$data);
        exit;
    }

    public function approve($id)
    {
        checkrights($this->authmodel,'group fund approval');
        $request = $this->fundmodel->GetRequest($id);if((int)$request->Status > 0){
            redirect('users/deniedaccess');
            exit;
        }
        checkcenter($request->CongregationId);
        $data = [
            'title' => 'Approve requisition',
            'paymethods' => $this->fundmodel->PayMethods(),
            'banks' => $this->fundmodel->GetBanks(),
            'groupid' => $request->GroupId,
            'group' => strtoupper($this->fundmodel->GetGroupName($request->GroupId)),
            'id' => $request->ID,
            'reqno' => $request->ReqNo,
            'reqdate' => date('d/m/Y',strtotime($request->RequisitionDate)),
            'amount' => number_format($request->AmountRequested,2),
            'availableamount' => number_format(floatval($this->fundmodel->GetBalance($request->GroupId,$request->RequisitionDate)),2),
            'reason' => strtoupper($request->Purpose),
            'approved' => '',
            'bank' => '',
            'balance' => '',
            'paydate' => '',
            'paymethod' => 3,
            'reference' => '',
            'errmsg' => '',
        ];
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
                'id' => isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : '',
                'group' => isset($_POST['group']) && !empty(trim($_POST['group'])) ? trim($_POST['group']) : '',
                'reqdate' => isset($_POST['date']) && !empty(trim($_POST['date'])) ? date('Y-m-d',strtotime(trim($_POST['date']))) : '',
                'paydate' => isset($_POST['paydate']) && !empty(trim($_POST['paydate'])) ? date('Y-m-d',strtotime(trim($_POST['paydate']))) : '',
                'availableamount' => isset($_POST['availableamount']) && !empty(trim($_POST['availableamount'])) ? floatval(numberFormat($_POST['availableamount'])) : '',
                'amount' => isset($_POST['amount']) && !empty(trim($_POST['amount'])) ? floatval(numberFormat(trim($_POST['amount']))) : '',
                'approved' => isset($_POST['approved']) && !empty(trim($_POST['approved'])) ? floatval(trim($_POST['approved'])) : '',
                'balance' => isset($_POST['balance']) && !empty(trim($_POST['balance'])) ? floatval(trim($_POST['balance'])) : '',
                'paymethod' => isset($_POST['paymethod']) && !empty(trim($_POST['paymethod'])) ? trim($_POST['paymethod']) : '',
                'bank' => isset($_POST['bank']) && !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'reference' => isset($_POST['reference']) && !empty(trim($_POST['reference'])) ? trim($_POST['reference']) : '',
                'reason' => trim($_POST['reason']),
                'errmsg' => '',
            ];

            
            if(empty($data['paydate']) || empty($data['approved']) || empty($data['paymethod']) 
               || empty($data['reference']) || empty($data['bank'])){
                $data['errmsg'] = 'Fill all required field';
            }
            //validate date
            if($data['reqdate'] > $data['paydate']){
                $data['errmsg'] = 'Payment date earlier than Requisition date';
            }
            if($data['approved'] > $data['amount']){
                $data['errmsg'] = 'Payment amount greater than amount requested';
            }

            if(!empty($data['errmsg'])){
                $this->view('groupfunds/approve',$data);
                exit;
            }

            if(!$this->fundmodel->Approve($data)){
                $data['errmsg'] = 'Unable to save this approval. Contact admin for help';
                $this->view('groupfunds/approve',$data);
                exit;
            }

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
}