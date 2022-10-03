<?php
class Parishreports extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else {
            $this->parishReportModel = $this->model('Parishreport');
        }
    }
    public function contributions()
    {
        $form = 'Contributions Reports';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->parishReportModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $congregations = $this->parishReportModel->GetCongregations();
        $accounts = $this->parishReportModel->GetAccounts(1);
        $data = [
            'congregations' => $congregations,
            'accounts' => $accounts
        ];
        $this->view('parishreports/contributions',$data);
    }
    public function contributionsrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'congregations' => !empty($_GET['congregations']) ? join(",",$_GET['congregations']) : '',
                'accounts' => !empty($_GET['accounts']) ? join(",",$_GET['accounts']) : '',
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
            ];
            // print_r($data);
            $contributions = $this->parishReportModel->GetContributions($data);
            $output = '';
            $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Congregation</th>
                            <th>Contribution Account</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($contributions as $contribution) {
                    $output .='
                        <tr>
                            <td>'.$contribution->congregation.'</td>
                            <td>'.$contribution->account.'</td>
                            <td>'.number_format($contribution->sumofamount,2).'</td>
                        </tr>';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:right">Total:</th>
                                <th id="total"></th>
                            </tr>
                    </tfoot>
                </table>';
            echo $output;

        }else{
            redirect('users');
        }
    }
    public function expenses()
    {
        $form = 'Expenses Reports';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->parishReportModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $congregations = $this->parishReportModel->GetCongregations();
        $accounts = $this->parishReportModel->GetAccounts(2);
        $data = [
            'congregations' => $congregations,
            'accounts' => $accounts
        ];
        $this->view('parishreports/expenses',$data);
    }
    public function expensesrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'congregations' => !empty($_GET['congregations']) ? join(",",$_GET['congregations']) : '',
                'accounts' => !empty($_GET['accounts']) ? join(",",$_GET['accounts']) : '',
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
            ];
            // print_r($data);
            $expenses = $this->parishReportModel->GetExpenses($data);
            $output = '';
            $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Congregation</th>
                            <th>Expense Account</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($expenses as $expense) {
                    $output .='
                        <tr>
                            <td>'.$expense->congregation.'</td>
                            <td>'.$expense->account.'</td>
                            <td>'.number_format($expense->sumofamount,2).'</td>
                        </tr>';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:right">Total:</th>
                                <th id="total"></th>
                            </tr>
                    </tfoot>
                </table>';
            echo $output;
        }else {
            redirect('users');
        }
    }
    public function budgetvsexpense()
    {
        $form = 'Budget Vs Expense Reports';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->parishReportModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $congregations = $this->parishReportModel->GetCongregations();
        $years = $this->parishReportModel->GetYears();
        $current = $this->parishReportModel->GetCurrentyear();
        $data = [
            'congregations' => $congregations,
            'years' => $years,
            'current' => $current
        ];
        $this->view('parishreports/budgetvsexpense',$data);
    }
    public function getgroups()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $congregation = trim($_GET['cong']);
            $groups = $this->parishReportModel->GetGroups($congregation);
            // print_r($groups);
            $output = '';
            $output .='<option value="0" selected disabled>Select Group</option>';
            foreach ($groups as $group ) {
                $output .= '<option value="'.$group->ID.'">'.$group->groupName.'</option>';
            }
           
            echo $output;
        }else {
            redirect('users');
        }
    }
    public function budgetvsexpenserpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET =  filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => trim($_GET['type']),
                'cong' => trim($_GET['cong']),
                'year' => trim($_GET['year']),
                'group' => trim($_GET['group'])
            ];
            // print_r($data);
            $budgetvexpenses = $this->parishReportModel->GetBudgetVsExpense($data);
            $output = '';
            $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Account</th>
                            <th>Budgeted Amount</th>
                            <th>Jan</th>
                            <th>Feb</th>
                            <th>Mar</th>
                            <th>Apr</th>
                            <th>May</th>
                            <th>Jun</th>
                            <th>Jul</th>
                            <th>Aug</th>
                            <th>Sep</th>
                            <th>Oct</th>
                            <th>Nov</th>
                            <th>Dec</th>
                            <th>Total</th>
                            <th>Variance</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($budgetvexpenses as $budgetvexpense) {
                    $output .='
                        <tr>
                            <td>'.$budgetvexpense->accountType.'</td>
                            <td>'.number_format($budgetvexpense->budgetedAmount,2).'</td>
                            <td>'.number_format($budgetvexpense->Jan,2).'</td>
                            <td>'.number_format($budgetvexpense->Feb,2).'</td>
                            <td>'.number_format($budgetvexpense->Mar,2).'</td>
                            <td>'.number_format($budgetvexpense->Apr,2).'</td>
                            <td>'.number_format($budgetvexpense->May,2).'</td>
                            <td>'.number_format($budgetvexpense->Jun,2).'</td>
                            <td>'.number_format($budgetvexpense->Jul,2).'</td>
                            <td>'.number_format($budgetvexpense->Aug,2).'</td>
                            <td>'.number_format($budgetvexpense->Sep,2).'</td>
                            <td>'.number_format($budgetvexpense->Oct,2).'</td>
                            <td>'.number_format($budgetvexpense->Nov,2).'</td>
                            <td>'.number_format($budgetvexpense->Dece,2).'</td>
                            <td>'.number_format($budgetvexpense->ExpenseTotal,2).'</td>
                            <td>'.number_format($budgetvexpense->variance,2).'</td>
                        </tr>';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th style="text-align:right">Total:</th>
                                <th id="budtotal"></th>
                                <th id="jantotal"></th>
                                <th id="febtotal"></th>
                                <th id="martotal"></th>
                                <th id="aprtotal"></th>
                                <th id="maytotal"></th>
                                <th id="juntotal"></th>
                                <th id="jultotal"></th>
                                <th id="augtotal"></th>
                                <th id="septotal"></th>
                                <th id="octtotal"></th>
                                <th id="novtotal"></th>
                                <th id="dectotal"></th>
                                <th id="exptotal"></th>
                                <th id="vartotal"></th>
                            </tr>
                    </tfoot>
                </table>';
            echo $output;
        }else {
            redirect('users');
        }
    }
    public function invoices()
    {
        $form = 'Invoice Reports';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->parishReportModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $data = [];
        $this->view('parishreports/invoices',$data);
    }
    public function invoicesrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'status' => trim($_GET['status']),
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
            ];
            $invoices = $this->parishReportModel->GetInvoices($data);
            $output = '';
            if ($data['status'] == 1) {
                $output .='
                    <table id="table" class="table table-striped table-bordered table-sm">
                        <thead class="bg-lightblue">
                            <tr>
                                <th>Invoice Date</th>
                                <th>Invoice No</th>
                                <th>Customer</th>
                                <th>Invoice Amount</th>
                                <th>Amount Paid</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                <tbody>';
                foreach ($invoices as $invoice ) {
                    $output .='
                        <tr>
                            <td>'.$invoice->invoiceDate.'</td>
                            <td>'.$invoice->invoiceNo.'</td>
                            <td>'.$invoice->customer.'</td>
                            <td>'.number_format($invoice->inclusiveVat,2).'</td>
                            <td>'.number_format($invoice->amountpaid,2).'</td>
                            <td>'.number_format($invoice->balance,2).'</td>
                        </tr>
                    ';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="3" style="text-align:right">Total:</th>
                                <th id="amounttotal"></th>
                                <th id="paidtotal"></th>
                                <th id="baltotal"></th>
                            </tr>
                    </tfoot>
                </table>';
            }elseif ($data['status'] == 2) {
                $output .='
                    <table id="table" class="table table-striped table-bordered table-sm">
                        <thead class="bg-lightblue">
                            <tr>
                                <th>Invoice Date</th>
                                <th>Invoice No</th>
                                <th>Customer</th>
                                <th>Invoice Amount</th>
                                <th>Amount Paid</th>
                            </tr>
                        </thead>
                <tbody>';
                foreach ($invoices as $invoice ) {
                    $output .='
                        <tr>
                            <td>'.$invoice->invoiceDate.'</td>
                            <td>'.$invoice->invoiceNo.'</td>
                            <td>'.$invoice->customer.'</td>
                            <td>'.number_format($invoice->inclusiveVat,2).'</td>
                            <td>'.number_format($invoice->amountpaid,2).'</td>
                        </tr>
                    ';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="3" style="text-align:right">Total:</th>
                                <th id="amounttotal"></th>
                                <th id="paidtotal"></th>
                            </tr>
                    </tfoot>
                </table>';
            }elseif ($data['status'] == 3) {
                $output .='
                    <table id="table" class="table table-striped table-bordered table-sm">
                        <thead class="bg-lightblue">
                            <tr>
                                <th>Invoice No</th>
                                <th>Invoice Date</th>
                                <th>Due Date</th>
                                <th>Due Days</th>
                                <th>Customer</th>
                                <th>Invoice Amount</th>
                                <th>Amount Paid</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                <tbody>';
                foreach ($invoices as $invoice ) {
                    $output .='
                        <tr>
                            <td>'.$invoice->invoiceNo.'</td>
                            <td>'.$invoice->invoiceDate.'</td>
                            <td>'.$invoice->duedate.'</td>
                            <td>'.$invoice->duedays.'</td>
                            <td>'.$invoice->customer.'</td>
                            <td>'.number_format($invoice->inclusiveVat,2).'</td>
                            <td>'.number_format($invoice->amountpaid,2).'</td>
                            <td>'.number_format($invoice->balance,2).'</td>
                        </tr>
                    ';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="5" style="text-align:right">Total:</th>
                                <th id="amounttotal"></th>
                                <th id="paidtotal"></th>
                                <th id="baltotal"></th>
                            </tr>
                    </tfoot>
                </table>';
            }
            echo $output;
        }else{
            redirect('users');
        }
    }
    public function incomestatement()
    {
        $form = 'Income Statement';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->parishReportModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $congregations = $this->parishReportModel->GetCongregations();
        $data = [
            'congregations' => $congregations,
        ];
        $this->view('parishreports/incomestatement',$data);
    }
    public function incomestatementrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'cong' => trim($_GET['cong']),
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
            ];
            // print_r($data);
            $revenues = $this->parishReportModel->GetRevenues($data);
            $revenue_total = $this->parishReportModel->GetRevenuesTotal($data);
            $expenses = $this->parishReportModel->GetExpensesPL($data);
            $expenses_total = $this->parishReportModel->GetExpensesTotal($data);
            $profit_loss = ($revenue_total - $expenses_total);
            $output = '';
            $output .='
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>Income Statement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="bg-olive">
                            <td colspan="2">Income</td>
                        </tr>';
                    foreach ($revenues as $revenue ) {
                        $output .='
                        <tr>
                            <td>'.$revenue->account.'</td>
                            <td>'.number_format($revenue->SumOfTotal,2).'</td>
                        </tr>';
                    }
                    $output .='
                        <tr>
                            <th>Revenue Total</th>
                            <th>'.number_format($revenue_total,2).'</th>
                        </tr>
                        <tr style="background-color: #ed6b6b">
                            <td colspan="2">Expenses</td>
                        </tr>';
                    foreach ($expenses as $expense ) {
                        $output .='
                        <tr>
                            <td>'.$expense->account.'</td>
                            <td>'.number_format($expense->SumOfTotal,2).'</td>
                        </tr>';
                    }
                    $output .='
                        <tr>
                            <th>Expense Total</th>
                            <th>'.number_format($expenses_total,2).'</th>
                        </tr>
                        <tr style="background-color: #7a998b">
                            <th>Profit/Loss</th>
                            <th>'.number_format($profit_loss,2).'</th>
                        </tr>
                    </tbody>
                </table>';
                   
                
            echo $output;
        }else {
            redirect('users');
        }
    }
    public function trialbalance()
    {
        $form = 'Trial Balance';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->parishReportModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $congregations = $this->parishReportModel->GetCongregations();
        $data = [
            'congregations' => $congregations,
        ];
        $this->view('parishreports/trialbalance',$data);
    }
    public function trialbalancerpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'cong' => trim($_GET['cong']),
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
            ];
            $accounts_balances = $this->parishReportModel->GetTrialBalance($data);
            $output = '';
            $output .='
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Account</th>
                            <th>Debit</th>
                            <th>Credit</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach ($accounts_balances as $balance ) {
                        $output .='
                            <tr>
                                <td>'.strtoupper($balance->account).'</td>
                                <td>'.number_format(floatval($balance->Debit),2).'</td>
                                <td>'.number_format(floatval($balance->credit),2).'</td>
                            </tr>
                        ';
                    }
                    $output .='
                    </tbody>
                    <tfoot>
                        <tr>
                            <th style="text-align:right">Total</th>
                            <th id="debittotal"></th>
                            <th id="credittotal"></th>
                        </tr>
                    </tfoot>
                </table>';
            
            echo $output;
        }else {
            redirect('users');
        }
    }
    public function balancesheet()
    {
        $form = 'Balance Sheet';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->parishReportModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $congregations = $this->parishReportModel->GetCongregations();
        $data = [
            'congregations' => $congregations,
        ];
        $this->view('parishreports/balancesheet',$data);
    }
    public function balancesheetrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'cong' => trim($_GET['cong']),
                'todate' => trim($_GET['todate']),
            ];
            $assets = $this->parishReportModel->GetAssets($data);
            $liablityequities = $this->parishReportModel->GetLiablityEquity($data);
            $assetsTotal = $this->parishReportModel->GetAssetsTotal($data);
            $liablityequitiesTotal = $this->parishReportModel->GetLiabilityEquityTotal($data);
            $netIncome = $this->parishReportModel->GetNetIncome($data);
            $totalLiablityEquity = floatval($liablityequitiesTotal) + floatval($netIncome);
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Balance Sheet As Of '.date("d/m/Y", strtotime($data['todate'])).'</th>
                        </tr>
                    </thead>   
                    <tbody>
                        <tr class="bg-olive">
                            <td colspan="2">Assets</th>
                        </tr>';
                    foreach($assets as $asset){
                        $output .='
                        <tr>
                            <td>'.strtoupper($asset->account).'</td>
                            <td>'.number_format($asset->bal,2).'</td>
                        </tr>';
                    }
                    $output .='
                        <tr style="background-color: #abebbc;">
                            <td style="font-weight: 700;">Assets Total</td>
                            <td style="font-weight: 700;">'.number_format($assetsTotal,2).'</td>
                        </tr>
                        <tr style="background-color: #e85858; color: #fff;">
                            <td colspan="2">Liability & Equity</th>
                        </tr>';
                    foreach ($liablityequities as $liabilityequity) {
                        $output .='
                        <tr>
                             <td>'.strtoupper($liabilityequity->account).'</td>
                             <td>'.number_format((floatval($liabilityequity->bal) * -1),2).'</td>
                        </tr>';
                    } 
                    $output .='
                        <tr>
                            <td>NET INCOME</td>
                            <td>'.number_format(floatval($netIncome),2).'</td>
                        </tr>
                        <tr style="background-color: #f59595;">
                            <td style="font-weight: 700;">Liablity & Equity Total</td>
                            <td style="font-weight: 700;">'.number_format($totalLiablityEquity,2).'</td>
                        </tr>
                    </tbody>
                </table>';   
            echo $output;
        }else {
            redirect('users');
        }
    }
    public function banking()
    {
        $form = 'Banking Reports';
        if ($_SESSION['userType'] > 2 &&  !$this->parishReportModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $banks = $this->parishReportModel->getBanks();
        $data = ['banks' => $banks];
        $this->view('parishreports/banking',$data);
    }
    public function bankingrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'bank' => trim($_GET['bank']),
                'from' => trim($_GET['from']),
                'to' => trim($_GET['to']),
                'status' => trim($_GET['status']),
            ];

            $bankings = $this->reportModel->bankingrpt($data);
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Date</th>
                            <th>Transaction Type</th>
                            <th>Amount</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($bankings as $banking) {
                        $output .= '
                            <tr>
                                <td>'.date('d-m-Y',strtotime($banking->transactionDate)).'</td>
                                <td>'.$banking->methodName.'</td>
                                <td>'.number_format($banking->Amount,2).'</td>
                                <td>'.$banking->reference.'</td>
                            </tr>
                        ';
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:right">Total:</th>
                                <th id="total"></th>
                                <th></th>
                            </tr>
                    </tfoot>
                </table>';
            echo $output;        
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    public function groupstatement()
    {
        $data = [
            'groups' => $this->reportModel->GetGroups()
        ];
        $this->view('reports/groupstatement',$data);
    }

    public function groupstatementrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'start' => !empty(trim($_GET['start'])) ? date('Y-m-d',strtotime($_GET['start'])) : NULL,
                'end' => !empty(trim($_GET['end'])) ? date('Y-m-d',strtotime($_GET['end'])) :NULL,
                'gid' => trim($_GET['gid']),
            ];

            $reports = $this->reportModel->getgroupstatement($data);
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Transaction Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Deposits</th>
                            <th>Withdrawals</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($reports as $report) {
                        $output .= '
                            <tr>
                                <td>'.date('d-m-Y',strtotime($report->TransactionDate)).'</td>
                                <td>'.$report->Narration.'</td>
                                <td>'.$report->Reference.'</td>
                                <td>'.$report->Debit.'</td>
                                <td>'.$report->Credit.'</td>
                            </tr>
                        ';
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="3" style="text-align:center">Total:</th>
                                <th id="deposits"></th>
                                <th id="withdrawals"></th>
                            </tr>
                    </tfoot>
                </table>';
            echo $output; 
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
}