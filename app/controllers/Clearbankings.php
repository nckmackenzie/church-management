<?php

class Clearbankings extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'clear bankings');
        $this->clearmodel = $this->model('Clearbanking');
        $this->reusemodel = $this->model('Reusables');
    }

    public function index()
    {
        $banks = $this->reusemodel->GetBanks();
        $data = [
            'banks' => $banks,
        ];
        $this->view('clearbankings/index',$data);
        exit;
    }

    //to add
    public function getbankings()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [ 
                'bank' => isset($_GET['bank']) && !empty(trim($_GET['bank'])) ? (int)trim($_GET['bank']) : NULL,
                'from' => isset($_GET['from']) && !empty(trim($_GET['from'])) ? date('Y-m-d',strtotime(trim($_GET['from']))) : NULL,
                'to' => isset($_GET['to']) && !empty(trim($_GET['to'])) ? date('Y-m-d',strtotime(trim($_GET['to']))) : NULL,
                'bankings' => []
            ];

            if(is_null($data['bank']) || is_null($data['from']) || is_null($data['to'])){
                http_response_code(400);
                echo json_encode(['message' => 'Please provide all required fields']);
                exit;
            }

            foreach($this->clearmodel->getBankings($data) as $banking){
                array_push($data['bankings'],[
                    'id' => $banking->ID,
                    'transactionDate' => $banking->transactionDate,
                    'amount' => $banking->Amount,
                    'reference' => $banking->Reference
                ]);
            }

            $values = $this->clearmodel->getAmounts($data);
            $debits = $values[0];
            $credits = $values[1];
            $value = [
                'debits' => $debits,
                'credits' => $credits
            ];
            echo json_encode(['values' => $value, 'bankings' => $data['bankings']]);
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }

    public function getValues()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'bank' => (int)trim($_GET['bank']),
                'from' => trim($_GET['from']),
                'to' => trim($_GET['to']),
                'debits' => '',
                'credits' => '',
                'balance' => '',
                'variance' => ''
            ];
            // $values = [];
            $debits = $this->clearmodel->getAmounts($data)[0];
            $credits = $this->clearmodel->getAmounts($data)[1];
            $balance = $this->clearmodel->getAmounts($data)[2];
            $data['debits'] = $debits;
            $data['credits'] = $credits;
            $data['balance'] = $balance;
            $data['variance'] = floatval($balance) - (floatval($debits) - floatval($credits));
            // array_push($values,$debits);
            // array_push($values,$credits);

            echo json_encode($data);
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    

    public function clear()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $fields = json_decode(file_get_contents('php://input'));
            $data = [
                'table' => $fields->table
            ];
            $errorCount = 0;
            $dateError = 0;
            //validate
            if(!is_countable($data['table'])) :
                http_response_code(400);
                echo json_encode(['message' => 'No bankings selected for clearing']);
                exit;
            endif;

            for($i = 0; $i < count($data['table']); $i++) {
                if(empty(trim($data['table'][$i]->clearDate)) || $data['table'][$i]->clearDate == ''){
                    $errorCount ++;
                }
                if(date('Y-m-d',strtotime($data['table'][$i]->clearDate)) < date('Y-m-d',strtotime($data['table'][$i]->txnDate))){
                    $dateError ++;
                }
            }

            if($errorCount > 0){
                http_response_code(400);
                echo json_encode(['message' => 'Clear date not selected for '.$errorCount.' banking(s)']);
                exit;
            }

            if($dateError > 0){
                http_response_code(400);
                echo json_encode(['message' => 'Clear date not earlier than transaction date in '.$dateError.' banking(s)']);
                exit;
            }

            //not saved
            if(!$this->clearmodel->Clear($data)){
                http_response_code(500);
                echo json_encode(['message' => 'Unable to clear selected bankings. Retry or contact admin']);
                exit;
            }

            echo json_encode(['message' => 'Saved successfully','success' => true]);
            exit;
  
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    
    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $id = trim($_POST['id']);
            $this->clearmodel->delete($id);
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
}