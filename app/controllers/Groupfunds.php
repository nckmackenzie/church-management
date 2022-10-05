<?php
class Groupfunds extends Controller
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->fundmodel = $this->model('Groupfund');
    }

    public function index()
    {
        checkrights($this->fundmodel,'group fund requisition');
        $data = [
            'requests' => $this->fundmodel->GetRequests(),
        ];
        $this->view('groupfunds/index',$data);
        exit;
    }

    public function add()
    {
        checkrights($this->fundmodel,'group fund requisition');
        $data = [
            'title' => 'Add requisition',
            'groups' => $this->fundmodel->GetGroups(),
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
        $request = $this->fundmodel->GetRequest($id);
        $cid = $this->fundmodel->GetGroupCongregation($request->GroupId);
        if((int)$request->Status > 0){
            redirect('users/deniedaccess');
            exit;
        }
        checkcenter($cid);
        $data = [
            'title' => 'Edit requisition',
            'groups' => $this->fundmodel->GetGroups(),
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
}