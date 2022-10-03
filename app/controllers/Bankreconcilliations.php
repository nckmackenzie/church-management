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
                            <td>Uncleared Withdrawals</td>
                            <td>'.number_format($unclearedWithdrawals,2).'</td>
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
}