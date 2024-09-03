<?php

class Trialbalance extends Controller
{
    private $authmodel;
    private $reportmodel;
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
                'asofdate' => isset($_GET['asofdate']) && !empty(trim($_GET['asofdate'])) ? date('Y-m-d',strtotime(trim($_GET['asofdate']))) : null,
                // 'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                // 'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
            ];

            //validate
            // if(is_null($data['type']) || is_null($data['sdate']) || is_null($data['edate'])) {
            //     http_response_code(400);
            //     echo json_encode(['message' => 'Fill all required fields']);
            //     exit;
            // }
            if(is_null($data['type']) || is_null($data['asofdate'])) {
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

    public function report()
    {
        $data = [];
        $this->view('reports/trialbalancereport',$data);
        exit;
    }

    public function detailedreport()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null,
                'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? trim($_GET['account']) : null,
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
                'results' => []
            ];

            //validate data
            if(is_null($data['type']) || is_null($data['account']) || is_null($data['sdate']) || is_null($data['edate'])){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required fields']);
                exit;
            }
            if($data['sdate'] > $data['edate']){
                http_response_code(400);
                echo json_encode(['message' => 'Start date cannot be greater than end date']);
                exit;
            }

            $results = $this->reportmodel->GetDetailedTbReport($data);
            if(!$results){
                http_response_code(500);
                echo json_encode(['message' => 'Invalid report type']);
                exit;
            }
            foreach($results as $result){
                array_push($data['results'],[
                    'transactionDate' => date('d-m-Y',strtotime($result->transactionDate)),
                    'account' => ucwords($result->account),
                    'debit' => floatval($result->debit) !== 0 ? floatval($result->debit) : '',
                    'credit' => floatval($result->credit) !== 0 ? floatval($result->credit) : '',
                    'narration' => ucwords($result->narration),
                    'transactionType' => ucwords($result->TransactionType),
                ]);
            }

            echo json_encode(['success' => true,'results' => $data['results']]);
            exit;
        }
        else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}