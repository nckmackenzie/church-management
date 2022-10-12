<?php
class Bankreconcilliations extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('');
        }else {
            $this->bankreconModel = $this->model('Bankreconcilliation');
        }
    }
    public function index()
    {
        $form = 'Bank Reconcilliation';
        if ($_SESSION['userType'] > 2 &&  !$this->bankreconModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
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
                'from' => trim($_GET['from']),
                'to' => trim($_GET['to']),
                'balance' => trim($_GET['balance']),
           ];

           $clearedDeposits = floatval($this->bankreconModel->getAmounts($data)[0]);
           $clearedWithdrawals = floatval($this->bankreconModel->getAmounts($data)[1]);
           $unclearedDeposits = floatval($this->bankreconModel->getAmounts($data)[2]);
           $unclearedWithdrawals = floatval($this->bankreconModel->getAmounts($data)[3]);
           $variance =  (floatval($data['balance']) - ($clearedDeposits - $clearedWithdrawals));
           $expectedBalance = ($clearedDeposits + $unclearedDeposits) - ($clearedWithdrawals + $unclearedWithdrawals);

           $output = '';
           $output .='
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead>
                        <th>Bank Reconcilliation</th>
                        <th></th>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Balance</td>
                            <td>'.number_format($data['balance'],2).'</td>
                        </tr>
                        <tr>
                            <td>Cleared Deposits</td>
                            <td>'.number_format($clearedDeposits,2).'</td>
                        </tr>
                        <tr>
                            <td>Cleared Withdrawals</td>
                            <td>'.number_format($clearedWithdrawals,2).'</td>
                        </tr>
                        <tr>
                            <td>Variance</td>
                            <td>'.number_format($variance,2).'</td>
                        </tr>
                        <tr>
                            <td>Uncleared Deposits</td>
                            <td>'.number_format($unclearedDeposits,2).'</td>
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
}