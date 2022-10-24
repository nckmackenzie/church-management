<?php
class Transfers extends Controller
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        if((int)$_SESSION['isParish'] === 0){
            redirect('users/deniedaccess');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'transfer member');
        $this->transfermodel = $this->model('Transfer');
    }

    public function index()
    {
        $congregations = $this->transfermodel->GetCongregations();
        $data = [
            'congregations' => $congregations,
            'congregationfrom' => '',
            'members' => '',
            'member' => '',
            'district' => '',
            'newcongregation' => '',
            'districts' => '',
            'newdistrict' => '',
            'date' => '',
            'reason' => '',
            'newcong_err' => '',
            'newdist_err' => '',
            'date_err' => '',
            'reason_err' => ''
        ];
        $this->view('members/transfer',$data);
    }

    public function getvalues()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $data = [
                'type' => trim($_GET['type']),
                'cong' => isset($_GET['cong']) ? (int)trim($_GET['cong']) : null,
                'district' => isset($_GET['district']) ? (int)trim($_GET['district']) : null,
                'results' => [],
            ];
            $results='';
            if($data['type'] === 'districts'){
                $results = $this->transfermodel->GetDistricts($data['cong']);
            }elseif($data['type'] === 'members'){
                $results = $this->transfermodel->GetMembers($data['district']);
            }
            foreach($results as $district){
                array_push($data['results'],[
                    'id' => $district->ID,
                    'fieldName' => $district->fieldName,
                ]);
            }
            echo json_encode($data['results']);
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function transfermember()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'currentcong' => !empty(trim($_POST['congregationfrom'])) ? trim($_POST['congregationfrom']) : '',
                'currentdist' => !empty(trim($_POST['district'])) ? trim($_POST['district']) : '',
                'newcong' => !empty(trim($_POST['newcongregation'])) ? trim($_POST['newcongregation']) : '',
                'newdist' => !empty(trim($_POST['newdistrict'])) ? trim($_POST['newdistrict']) : '',
                'tdate' => !empty(trim($_POST['date'])) ? date('Y-m-d',strtotime(trim($_POST['date']))) : '',
                'reason' => !empty(trim($_POST['date'])) ? trim($_POST['reason']) : '',
                'members' => $_POST['member'],
            ];
     
            if(empty($data['currentcong']) || empty($data['currentdist']) || empty($data['newcong']) 
              || empty($data['newdist']) || empty($data['tdate']) || empty($data['reason'])){
                exit;
            }

            if(!$this->transfermodel->Transfer($data)){
                flash('transfer_msg','Something went wrong! Retry or contact admin','alert custom-danger alert-dismissible fade show');
                redirect('transfers');
            }

            flash('transfer_msg','Member Transfered Successfully');
            redirect('transfers');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}