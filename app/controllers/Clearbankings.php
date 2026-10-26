<?php

class Clearbankings extends Controller
{
    private $clearmodel;
    private $authmodel;
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        
        $this->clearmodel = $this->model('Clearbanking');
    }

    /** Strict report-safe bank and Y-m-d range shared by the Clear Banking summaries. */
    private function summaryData($input)
    {
        $input = is_array($input) ? $input : [];
        $bank = positiveIntParam($input['bank'] ?? null, 'bank');
        if (!$this->clearmodel->isReportBank($bank)) { badRequest('Invalid bank.'); }
        $from = strictDateParam($input['from'] ?? null, 'from');
        $to = strictDateParam($input['to'] ?? null, 'to');
        if ($from > $to) { badRequest('Invalid dates: from is after to.'); }
        return ['bank' => $bank, 'from' => $from, 'to' => $to];
    }

    public function index()
    {
        checkrights($this->authmodel,'clear bankings');
        $banks = $this->clearmodel->getBanks();
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
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ?: [];
            $data = $this->summaryData($_GET) + ['status' => $_GET['status'] ?? null, 'bankings' => []];
            if (!is_null($data['status']) && $data['status'] !== 'cleared') { badRequest('Invalid status.'); }

            if(is_null($data['status'])){
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
                foreach($this->clearmodel->getClearedBankings($data) as $banking){
                    array_push($data['bankings'],[
                        'id' => $banking->ID,
                        'transactionDate' => date('d-m-Y',strtotime($banking->transactionDate)),
                        'clearedDate' => is_null($banking->clearedDare) ? null : $banking->clearedDare,
                        'amount' => $banking->Amount,
                        'reference' => $banking->Reference
                    ]);
                }
                    
                echo json_encode(['bankings' => $data['bankings']]);
            }

            
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }

    public function getValues()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ?: [];
            $data = $this->summaryData($_GET);
            [$data['debits'], $data['credits']] = $this->clearmodel->getAmounts($data);
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
    
    public function modify()
    {
        checkrights($this->authmodel,'edit cleared bankings');
        $banks = $this->clearmodel->getBanks();
        $data = [
            'banks' => $banks,
        ];
        $this->view('clearbankings/modify',$data);
        exit;
    }

    public function update()
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
            if(!$this->clearmodel->Modify($data)){
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
}
