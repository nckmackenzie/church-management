<?php
class Parishreports extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
        }
        $this->authmodel = $this->model('Auth');
        $this->parishReportModel = $this->model('Parishreport');
    }
    /** A posted bank must be in the report-safe bank list of the session congregation (task 9.1): 400 otherwise. */
    private function reportBank($value)
    {
        $id = positiveIntParam($value,'bank');
        if (!$this->parishReportModel->isReportBank($id)) { badRequest('Invalid bank.'); }
        return $id;
    }
    /** Posted account IDs must all be in the report-safe account list of the root type for the parish scope. */
    private function reportAccountsIn(array $ids,$root)
    {
        if ($ids && array_diff($ids,$this->parishReportModel->reportAccountIds($root))) { badRequest('Invalid account.'); }
        return $ids;
    }
    public function contributions()
    {
        checkrights($this->authmodel,'receipts reports');
        $congregations = $this->parishReportModel->GetCongregations();
        $accounts = $this->parishReportModel->GetAccounts(1);
        $data = [
            'congregations' => $congregations,
            'accounts' => $accounts
        ];
        $this->view('parishreports/contributions',$data);
    }
    /** Selected congregations must be congregations of the parish scope (task 9.7): 400 otherwise. */
    private function parishCongregations(array $ids)
    {
        if (array_diff($ids,FinancialReport::parishScope((new Database)->pdo()))) { badRequest('Invalid congregation.'); }
        return $ids;
    }
    /** The validated request of the three parish endpoints (hotfix validation, decision 118, plus the range check and the scope check). */
    private function parishRequest($root)
    {
        $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ?: [];
        $data = [
            'congregations' => $this->parishCongregations(positiveIntListParam($_GET['congregations'] ?? null,'congregations')),
            'accounts' => $this->reportAccountsIn(positiveIntListParam($_GET['accounts'] ?? null,'accounts'),$root),
            'start' => strictDateParam($_GET['start'] ?? null,'start date'),
            'end' => strictDateParam($_GET['end'] ?? null,'end date'),
        ];
        if ($data['start'] > $data['end']) { badRequest('The start date cannot be after the end date.'); }
        return $data;
    }
    private function parishPeriod($data)
    {
        return 'Period ' . date('d-m-Y',strtotime($data['start'])) . ' to ' . date('d-m-Y',strtotime($data['end']));
    }
    /** Parish contributions: JSON (one page, search) or Excel (every matching row). Approved, not deleted receipts only. */
    public function contributionsrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        $data = $this->parishRequest(1);
        $rows = $this->parishReportModel->GetContributions($data);
        $spec = ['title' => 'Contributions','lines' => [$this->parishPeriod($data)],
                 'columns' => [['key' => 'date','label' => 'Date','type' => 'date'],['key' => 'congregation','label' => 'Congregation','type' => 'text'],
                               ['key' => 'account','label' => 'Contribution Account','type' => 'text'],['key' => 'amount','label' => 'Amount','type' => 'money']],
                 'total' => 'amount','file' => 'parish-contributions-' . $data['start'] . '-to-' . $data['end'],'sheet' => 'Contributions','widths' => [12,34,32,16]];
        ReportList::respond($spec,$rows,$_GET);
    }

    public function bycontributor()
    {
        checkrights($this->authmodel,'receipts by contributor');
        $congregations = $this->parishReportModel->GetCongregations();
        $accounts = $this->parishReportModel->GetAccounts(1);
        $data = [
            'congregations' => $congregations,
            'accounts' => $accounts,
        ];
        $this->view('parishreports/bycontributor',$data);
    }
    /** Parish receipts by contributor: one total per contributor (decision 137). JSON page or Excel. */
    public function bycontributorrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        $data = $this->parishRequest(1);
        $rows = $this->parishReportModel->GetContributionsByContributor($data);
        $spec = ['title' => 'Receipts by contributor','lines' => [$this->parishPeriod($data)],
                 'columns' => [['key' => 'congregation','label' => 'Congregation','type' => 'text'],['key' => 'contributor','label' => 'Contributor','type' => 'text'],
                               ['key' => 'amount','label' => 'Amount','type' => 'money']],
                 'total' => 'amount','file' => 'parish-by-contributor-' . $data['start'] . '-to-' . $data['end'],'sheet' => 'By contributor','widths' => [34,40,16]];
        ReportList::respond($spec,$rows,$_GET);
    }

    public function expenses()
    {
        checkrights($this->authmodel,'expenses reports');
        $congregations = $this->parishReportModel->GetCongregations();
        $accounts = $this->parishReportModel->GetAccounts(2);
        $data = [
            'congregations' => $congregations,
            'accounts' => $accounts
        ];
        $this->view('parishreports/expenses',$data);
    }
    /** Parish expenses totalled per congregation and account: JSON page or Excel. Approved, not deleted only. */
    public function expensesrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        $data = $this->parishRequest(2);
        $rows = $this->parishReportModel->GetExpenses($data);
        $spec = ['title' => 'Expenses','lines' => [$this->parishPeriod($data)],
                 'columns' => [['key' => 'congregation','label' => 'Congregation','type' => 'text'],['key' => 'account','label' => 'Expense Account','type' => 'text'],
                               ['key' => 'amount','label' => 'Amount','type' => 'money']],
                 'total' => 'amount','file' => 'parish-expenses-' . $data['start'] . '-to-' . $data['end'],'sheet' => 'Expenses','widths' => [34,40,16]];
        ReportList::respond($spec,$rows,$_GET);
    }

    public function budgetvsexpense()
    {
        checkrights($this->authmodel,'budget vs expense reports');
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
        checkrights($this->authmodel,'invoice reports');
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
        checkrights($this->authmodel,'income statement');
        $congregations = $this->parishReportModel->GetCongregations();
        $data = [
            'congregations' => $congregations,
        ];
        $this->view('parishreports/incomestatement',$data);
    }
    /**
     * Income statement data (task 9.2): everything comes from FinancialReport; JSON, rendered by
     * dist/js/pages/reports/is/income-statement-v1.js with textContent.
     */
    public function incomestatementrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        [$start, $end] = IncomeStatementReport::dates($_GET);
        $db = (new Database)->pdo();
        [$scope,$entity] = IncomeStatementReport::parishScope($db,$_GET['cong'] ?? null);
        IncomeStatementReport::respondReport($db,$scope,$entity,$start,$end);
    }

    /** One drill level (account ID): the next level of accounts or the journal lines; the rows add up to the figure clicked. */
    public function plchildaccount()
    {
        $this->view('parishreports/plchildaccount',[]);
        exit;
    }

    public function plchildaccountrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed', 405); }
        [$start, $end] = IncomeStatementReport::dates($_GET);
        $account = IncomeStatementReport::accountId($_GET['account'] ?? null);
        $mode = ($_GET['view'] ?? '') === 'lines' ? 'lines' : 'children';
        $direct = ($_GET['direct'] ?? '') === '1';
        $db = (new Database)->pdo();
        [$scope,$entity] = IncomeStatementReport::parishScope($db,$_GET['cong'] ?? null);
        IncomeStatementReport::respondDrill($db,$scope,$entity,$account,$start,$end,$mode,$direct,$_GET);
    }

    /** Old name-based drill URLs: nothing is queried any more. */
    public function plchildaccountdetailed()
    {
        redirect('parishreports/incomestatement');
        exit;
    }

    public function plchildaccountdetailedrpt()
    {
        IncomeStatementReport::fail('This link is no longer supported; open the income statement again');
    }

    public function trialbalance()
    {
        checkrights($this->authmodel,'trial balance');
        $data = ['congregations' => $this->parishReportModel->GetCongregations()];
        $this->view('parishreports/trialbalance',$data);
    }

    /** JSON (or format=xlsx): asof, cong (0 / empty = the whole parish scope), detailed=1 */
    public function trialbalancerpt()
    {
        $db = (new Database)->pdo();
        [$scope,$entity] = IncomeStatementReport::parishScope($db,$_GET['cong'] ?? null);
        TrialBalanceReport::serveReport($db,$scope,$entity);
    }

    public function trialbalancedrill()
    {
        $this->view('parishreports/trialbalancedrill',[]);
        exit;
    }

    public function trialbalancedrillrpt()
    {
        $db = (new Database)->pdo();
        [$scope,$entity] = IncomeStatementReport::parishScope($db,$_GET['cong'] ?? null);
        TrialBalanceReport::serveDrill($db,$scope,$entity);
    }
    /** Balance sheet (task 9.4): FinancialReport on the journal; As Of Date only. */
    public function balancesheet()
    {
        checkrights($this->authmodel,'balance sheet');
        $data = ['congregations' => $this->parishReportModel->GetCongregations()];
        $this->view('parishreports/balancesheet',$data);
    }

    /** JSON (or format=xlsx): asof=Y-m-d , cong (0 / empty = the whole parish scope) */
    public function balancesheetrpt()
    {
        $db = (new Database)->pdo();
        [$scope,$entity] = IncomeStatementReport::parishScope($db,$_GET['cong'] ?? null);
        BalanceSheetReport::serveReport($db,$scope,$entity);
    }

    public function balancesheetdrill()
    {
        $this->view('parishreports/balancesheetdrill',[]);
        exit;
    }

    public function balancesheetdrillrpt()
    {
        $db = (new Database)->pdo();
        [$scope,$entity] = IncomeStatementReport::parishScope($db,$_GET['cong'] ?? null);
        BalanceSheetReport::serveDrill($db,$scope,$entity);
    }

    /** Old name-based drill URLs: nothing is queried any more */
    public function balancesheetdetailed()
    {
        redirect('parishreports/balancesheet');
        exit;
    }

    public function getbalancesheetdetailedrpt()
    {
        IncomeStatementReport::fail('This link is no longer supported; open the balance sheet again');
    }

    public function balancesheetchilddetails()
    {
        redirect('parishreports/balancesheet');
        exit;
    }

    public function balancesheetchilddetailsrpt()
    {
        IncomeStatementReport::fail('This link is no longer supported; open the balance sheet again');
    }

    public function banking()
    {
        checkrights($this->authmodel,'banking reports');
        $banks = $this->parishReportModel->getBanks();
        $data = ['banks' => $banks];
        $this->view('parishreports/banking',$data);
    }
    public function bankingrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'bank' => $this->reportBank($_GET['bank'] ?? null),
                'from' => strictDateParam($_GET['from'] ?? null,'from'),
                'to' => strictDateParam($_GET['to'] ?? null,'to'),
                'status' => in_array($_GET['status'] ?? null,['0','1'],true) ? $_GET['status'] : badRequest('Invalid status.'),
            ];
            if ($data['from'] > $data['to']) { badRequest('Invalid dates: from is after to.'); }

            $bankings = $this->parishReportModel->bankingrpt($data);
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
                                <td>'.htmlspecialchars((string)$banking->methodName,ENT_QUOTES,'UTF-8').'</td>
                                <td>'.number_format($banking->Amount,2).'</td>
                                <td>'.htmlspecialchars((string)$banking->reference,ENT_QUOTES,'UTF-8').'</td>
                            </tr>
                        ';
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:right">Total (cashbook rows listed):</th>
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
