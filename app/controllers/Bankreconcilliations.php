<?php
class Bankreconcilliations extends Controller
{
    private $authmodel;
    private $bankreconModel;
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('users');
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'bank reconcilliations');
        $this->bankreconModel = $this->model('Bankreconcilliation');
    }
    public function index()
    {
        $banks = $this->bankreconModel->getBanks();
        $data = ['banks' => $banks];
        $this->view('bankreconcilliations/index',$data);
    }
    public function bankrecon()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
           $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ;
           $data = [
                'bank' => trim($_GET['bank']),
                'from' => isset($_GET['from']) && !empty(trim($_GET['from'])) ? date('Y-m-d',strtotime(trim($_GET['from']))) : null,
                'to' => isset($_GET['from']) && !empty(trim($_GET['to'])) ? date('Y-m-d',strtotime(trim($_GET['to']))) : null,
                // 'to' => trim($_GET['to']),
                'balance' => trim($_GET['balance']),
           ];

           $openingBalance = floatval($this->bankreconModel->getAmounts($data)[4]);
           $clearedDeposits = floatval($this->bankreconModel->getAmounts($data)[0]);
           $clearedWithdrawals = floatval($this->bankreconModel->getAmounts($data)[1]);
           $unclearedDeposits = floatval($this->bankreconModel->getAmounts($data)[2]);
           $unclearedWithdrawals = floatval($this->bankreconModel->getAmounts($data)[3]);
        //    $variance =  (floatval($data['balance']) - ($clearedDeposits - $clearedWithdrawals));
           $expectedBalance = (($openingBalance + $clearedDeposits) - $clearedWithdrawals) - $unclearedDeposits + $unclearedWithdrawals;
           $variance =  floatval($data['balance']) - $expectedBalance;

           $output = '';
           $output .='
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead>
                        <th>Bank Reconcilliation</th>
                        <th></th>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Opening Balance</td>
                            <td>'.number_format($openingBalance,2).'</td>
                        </tr>
                        <tr>
                            <td>Cleared Deposits</td>';
                            if(floatval($clearedDeposits) != 0){
                                $route = URLROOT .'/bankreconcilliations/cleared?type=deposit&bank='.$data['bank'].'&sdate='.$data['from'].'&edate='.$data['to'].'';
                                $output .= '<td><a href="'.$route.'" class="" target="_blank">'.number_format($clearedDeposits,2).'</a></td>';
                            }else{
                                $output .= '<td>'.number_format($clearedDeposits,2).'</td>';
                            }
                        $output .='                            
                        </tr>
                        <tr>
                            <td>Cleared Withdrawals</td>';
                            if(floatval($clearedWithdrawals) != 0){
                                $route = URLROOT .'/bankreconcilliations/cleared?type=withdraw&bank='.$data['bank'].'&sdate='.$data['from'].'&edate='.$data['to'].'';
                                $output .= '<td><a href="'.$route.'" class="" target="_blank">'.number_format($clearedWithdrawals,2).'</a></td>';
                            }else{
                                $output .= '<td>'.number_format($clearedWithdrawals,2).'</td>';
                            }
                        $output .='
                        </tr>
                        <tr>
                            <td>Uncleared Deposits</td>';
                            if(floatval($unclearedDeposits) != 0){
                                $route = URLROOT .'/bankreconcilliations/uncleared?type=deposit&bank='.$data['bank'].'&sdate='.$data['from'].'&edate='.$data['to'].'';
                                $output .= '<td><a href="'.$route.'" class="" target="_blank">'.number_format($unclearedDeposits,2).'</a></td>';
                            }else{
                                $output .= '<td>'.number_format($unclearedDeposits,2).'</td>';
                            }
                        $output .='    
                        </tr>
                        <tr>
                            <td>Uncleared Withdrawals</td>';
                            if(floatval($unclearedWithdrawals) != 0){
                                $route = URLROOT .'/bankreconcilliations/uncleared?type=withdraw&bank='.$data['bank'].'&sdate='.$data['from'].'&edate='.$data['to'].'';
                                $output .= '<td><a href="'.$route.'" class="" target="_blank">'.number_format($unclearedWithdrawals,2).'</a></td>';
                            }else{
                                $output .= '<td>'.number_format($unclearedWithdrawals,2).'</td>';
                            }
                        $output .='    
                        </tr>
                        <tr>
                            <td>Expected Balance</td>
                            <td>'.number_format($expectedBalance,2).'</td>
                        </tr>
                        <tr>
                            <td>Actual/Bank Balance</td>
                            <td>'.number_format($data['balance'],2).'</td>
                        </tr>
                        <tr>
                            <td>Variance</td>
                            <td>'.number_format($variance,2).'</td>
                        </tr>
                    </tbody>
                </table>           
           ';

           echo $output;

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }

    public function uncleared()
    {
        $data = [];
        $this->view('bankreconcilliations/uncleared', $data);
        exit;
    }

    public function cleared()
    {
        $data = [];
        $this->view('bankreconcilliations/cleared', $data);
        exit;
    }

    public function unclearedreport()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null,
                'bankid' => isset($_GET['bank']) && !empty(trim($_GET['bank'])) ? trim($_GET['bank']) : null,
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
                'results' => []
            ];

            if(is_null($data['bankid']) || is_null($data['sdate']) || is_null($data['edate']) || is_null($data['type'])){
                http_response_code(400);
                echo json_encode(['message' => 'Invalid parameters provided for this report']);
                exit;
            }

            foreach($this->bankreconModel->UnclearedReport($data) as $item){
                array_push($data['results'],[
                    'transactionDate' => date('d-m-Y',strtotime($item->transactionDate)),
                    'amount' => $item->amount,
                    'reference' => $item->reference,
                ]);
            }

            echo json_encode(['success' => true,'results' => $data['results']]);
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function clearedreport()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null,
                'bankid' => isset($_GET['bank']) && !empty(trim($_GET['bank'])) ? trim($_GET['bank']) : null,
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
                'results' => []
            ];

            if(is_null($data['bankid']) || is_null($data['sdate']) || is_null($data['edate']) || is_null($data['type'])){
                http_response_code(400);
                echo json_encode(['message' => 'Invalid parameters provided for this report']);
                exit;
            }

            foreach($this->bankreconModel->ClearedReport($data) as $item){
                array_push($data['results'],[
                    'transactionDate' => date('d-m-Y',strtotime($item->transactionDate)),
                    'amount' => $item->amount,
                    'reference' => $item->reference,
                ]);
            }

            echo json_encode(['success' => true,'results' => $data['results']]);
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}