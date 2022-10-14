<?php

class Trialbalance extends Controller
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'trial balance');
        $this->reportmodel = $this->model('Tb');
    }

    public function index()
    {
        $this->view('reports/trialbalance',[]);
        exit;
    }

    public function getreport()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null,
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
            ];

            //validate
            if(is_null($data['type']) || is_null($data['sdate']) || is_null($data['edate'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Fill all required fields']);
                exit;
            }

            echo json_encode(['results' => $this->reportmodel->GetReport($data),'success' => true]);
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}