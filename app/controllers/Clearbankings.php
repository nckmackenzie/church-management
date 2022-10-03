<?php

class Clearbankings extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('');
        }else {
            $this->clearbankingsModel = $this->model('Clearbanking');
        }
    }
    public function index()
    {
        $form = 'Clear Bankings';
        if ($_SESSION['userType'] > 2 &&  !$this->clearbankingsModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $banks = $this->clearbankingsModel->getBanks();
        // $bankings = $this->clearbankingsModel->getBankings();
        $data = [
            'banks' => $banks,
            // 'bankings' => $bankings
        ];
        $this->view('clearbankings/index',$data);
    }
    public function clear()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'details' => $_POST['table_data'],
            ];
          
            $this->clearbankingsModel->clear($data);
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
            $this->clearbankingsModel->delete($id);
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    //to add
    public function fetch()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'bank' => (int)trim($_GET['bank']),
                'from' => trim($_GET['from']),
                'to' => trim($_GET['to']),
            ];
            $bankings = $this->clearbankingsModel->getBankings($data);
            $output = '';
            $output .='
                <table class="table table-bordered table-striped table-sm mt-1" id="bankingsTable">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Select</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Reference</th>';
                            if($_SESSION['userType'] <=2 || $_SESSION['userType'] == 6){
                                $output .='<th>Actions</th>';
                            }
                        $output .='
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($bankings as $banking){
                        $output .= '
                            <tr>
                                <td>'.$banking->ID.'</td>
                            <td>
                                <input type="checkbox" name="cleared" id="cleared">
                            </td>
                            <td>'.date("d-m-Y", strtotime($banking->transactionDate)).'</td>
                            <td>'.number_format($banking->Amount,2).'</td>
                            <td>'.$banking->Reference.'</td>';
                            if($_SESSION['userType'] <=2){
                                $output .='
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
                                </td>';
                            }
                            $output .='
                            </tr>';
                    }
                    $output .='
                    </tbody>
                    </table>';
            echo $output;
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
            $debits = $this->clearbankingsModel->getAmounts($data)[0];
            $credits = $this->clearbankingsModel->getAmounts($data)[1];
            $balance = $this->clearbankingsModel->getAmounts($data)[2];
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
}