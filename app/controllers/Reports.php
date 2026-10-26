<?php
class Reports extends Controller {
    private $authmodel;
    private $reportModel;
    private $reusableModel;
    public function __construct()
    {
       if (!isset($_SESSION['userId'])) {
           redirect('users');
           exit;
       }
       $this->authmodel = $this->model('Auth');
       $this->reportModel = $this->model('Report');
       $this->reusableModel = $this->model('Reusables');
    }
    public function members()
    {
        checkrights($this->authmodel,'member reports');
        $districts = $this->reportModel->getDistricts();
        $data = ['districts' => $districts];
        $this->view('reports/members',$data);
    }
    public function memberreport()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $district = trim($_POST['district']);
            $status = trim($_POST['status']);
            $from = !empty($_POST['from']) ? trim($_POST['from']) : NULL;
            $to = !empty($_POST['to']) ? trim($_POST['to']) : NULL;
            
            $members = $this->reportModel->loadMembersRpt($district,$status,$from,$to);
            $output = '';
            if ($status < 3) {
                $output .='
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Member Name</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th>District</th>
                            <th>Status</th>
                            <th>Remark</th>
                            <th>Membership</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($members as $member) {
                    $output .= '
                        <tr>
                            <td>'.$member->memberName.'</td>
                            <td>'.$member->gender.'</td>
                            <td>'.$member->contact.'</td>
                            <td>'.$member->districtName.'</td>
                            <td>'.$member->memberstatus.'</td>
                            <td>'.$member->positionName.'</td>
                            <td>'.$member->mstatus.'</td>
                        </tr>
                    ';
                }
                $output .= '
                    </tbody>
                </table>    
                ';
            }else{
                $output .='
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Member Name</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Contact</th>
                            <th>District</th>
                            <th>Remark</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($members as $member) {
                    $output .= '
                        <tr>
                            <td>'.$member->memberName.'</td>
                            <td>'.$member->gender.'</td>
                            <td>'.$member->age.'</td>
                            <td>'.$member->contact.'</td>
                            <td>'.$member->district.'</td>
                            <td>'.$member->remark.'</td>
                        </tr>
                    ';
                }
                $output .= '
                    </tbody>
                </table>    
                ';
            }
            echo $output;    
        }
    }
    public function transfered()
    {
        checkrights($this->authmodel,'transfered report');
        $data = [];
        $this->view('reports/transfered',$data);
    }
    public function transferedreport()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'from' => trim($_POST['from']),
                'to' => trim($_POST['to']),
            ];
            
            $transfers = $this->reportModel->getTransfered($data);
            $output = '';
            $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Member Name</th>
                            <th>Gender</th>
                            <th>Position</th>
                            <th>Transfered To</th>
                            <th>Date Transfered</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($transfers as $transfer ) {
                    $output .='
                        <tr>
                            <td>'.$transfer->memberName.'</td>
                            <td>'.$transfer->gender.'</td>
                            <td>'.$transfer->positionName.'</td>
                            <td>'.$transfer->congregation.'</td>
                            <td>'.$transfer->transferDate.'</td>
                            <td>'.$transfer->reason.'</td>
                        </tr>
                    ';
                }
                $output .='
                    </body>
                </table>    
                '; 
            echo $output;       
        }
    }
    public function membershipstatus()
    {
        checkrights($this->authmodel,'by membership status');
        $districts = $this->reportModel->getDistricts();
        $data = ['districts' => $districts];
        $this->view('reports/membershipstatus',$data);
    }
    public function bystatusreport()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'status' => trim($_POST['status']),
                'district' => trim($_POST['district']),
            ];
            $members = $this->reportModel->byStatusRpt($data);
            $output = '';
            $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Member Name</th>
                            <th>Gender</th>
                            <th>District</th>
                            <th>Position</th>
                            <th>Membership Status</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($members as $member ) {
                    $output .= '
                        <tr>    
                            <td>'.$member->memberName.'</td>
                            <td>'.$member->gender.'</td>
                            <td>'.$member->district.'</td>
                            <td>'.$member->position.'</td>
                            <td>'.$member->membershipStatus.'</td>
                        </tr>
                    ';
                }
                $output .= '
                    </tbody>
                </table>
                ';
            echo $output;    
        }
    }
    public function residenceoccupation()
    {
        checkrights($this->authmodel,'residence/occupation reports');
        $districts = $this->reportModel->getDistricts();
        $data = ['districts' => $districts];
        $this->view('reports/residenceoccupation',$data);
    }
    public function residencereport()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $district = trim($_POST['district']);
            $members = $this->reportModel->getResidenceRpt($district);
            $output= '';
            $output .='
                <table class="table table-bordered table-striped table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Member Name</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th>District</th>
                            <th>Occupation</th>
                            <th>Residence</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($members as $member) {
                    $output .='
                        <tr>
                            <td>'.$member->memberName.'</td>
                            <td>'.$member->gender.'</td>
                            <td>'.$member->contact.'</td>
                            <td>'.$member->districtName.'</td>
                            <td>'.$member->occupation.'</td>
                            <td>'.$member->residence.'</td>
                        </tr>
                    ';
                }
                $output .='
                    </tbody>
                </table>
                ';
            echo $output;
        }
    }
    public function family()
    {
        checkrights($this->authmodel,'member family report');
        $familyCount = $this->reportModel->getFamilyCount();
        $districts = $this->reportModel->getDistricts();
        $data = [
            'districts' => $districts,
            'familycount' => $familyCount
        ];
        $this->view('reports/family',$data);
    }
    public function familyreport()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $district = trim($_POST['district']);
            $members  = $this->reportModel->getFamily($district);
        
            $output = '';
                $output .= '
                    <table class="table table-bordered table-striped table-sm" id="table">
                        <thead class="bg-lightblue"
                            <tr>
                                <th>Member Name</th>
                                <th>Family Member</th>
                                <th>Relationship</th>
                            </tr>
                        </thead>
                        <tbody>';
                    foreach ($members as $member ) {
                        $output .= '
                            <tr>
                                <td>'.$member->Main.'</td>
                                <td>'.$member->other.'</td>
                                <td>'.$member->relation.'</td>
                            </tr>
                        ';
                    }
            echo $output;
        }
    }
    public function contributions()
    {
        checkrights($this->authmodel,'receipts reports');
        $accounts = $this->reportModel->GetAccounts(1);
        $data = ['accounts' => $accounts];
        $this->view('reports/contributions',$data);
    }
    /**
     * Contributions as JSON (one page, server-side search) or Excel (every matching row), task 9.7. The hotfix validation
     * (decision 118) is unchanged; start must not be after end (400). Only approved, not deleted receipts are counted.
     */
    public function contributionsrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ?: [];
        $type = positiveIntParam($_GET['type'] ?? null,'type',[1,2,3]);
        $accounts = $this->reportAccountsIn(positiveIntListParam($_GET['account'] ?? null,'account',$type !== 2),1);
        $data = [
            'type' => $type,
            'start' => strictDateParam($_GET['start'] ?? null,'start date'),
            'end' => strictDateParam($_GET['end'] ?? null,'end date'),
            'account' => $accounts
        ];
        if ($data['start'] > $data['end']) { badRequest('The start date cannot be after the end date.'); }
        $rows = $this->reportModel->GetContributions($data);
        $period = 'Period ' . date('d-m-Y',strtotime($data['start'])) . ' to ' . date('d-m-Y',strtotime($data['end']));
        $file = 'contributions-' . $type . '-' . $data['start'] . '-to-' . $data['end'];
        if ($type === 3) {
            $spec = ['title' => 'Contributions by district','lines' => [$period],
                     'columns' => [['key' => 'district','label' => 'District','type' => 'text'],['key' => 'amount','label' => 'Amount','type' => 'money']],
                     'total' => 'amount','file' => $file,'sheet' => 'Contributions','widths' => [40,18]];
        } else {
            $spec = ['title' => 'Contributions','lines' => [$period],
                     'columns' => [['key' => 'date','label' => 'Date','type' => 'date'],['key' => 'receipt','label' => 'Receipt No','type' => 'link'],
                                   ['key' => 'account','label' => 'Contribution Account','type' => 'text'],['key' => 'cont','label' => 'Contributed By','type' => 'text'],
                                   ['key' => 'amount','label' => 'Amount','type' => 'money'],['key' => 'method','label' => 'Pay Method','type' => 'text'],
                                   ['key' => 'reference','label' => 'Reference','type' => 'text']],
                     'total' => 'amount','file' => $file,'sheet' => 'Contributions','widths' => [12,16,30,30,16,16,24]];
        }
        ReportList::respond($spec,$rows,$_GET);
    }

    public function expenses()
    {
        checkrights($this->authmodel,'expenses reports');
        $accounts = $this->reportModel->GetAccounts(2);
        $data = ['accounts' => $accounts];
        $this->view('reports/expenses',$data);
    }
    /** Expenses as JSON (one page, server-side search) or Excel (every matching row), task 9.7. Approved, not deleted only. */
    public function expensesrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ?: [];
        $type = positiveIntParam($_GET['type'] ?? null,'type',[1,2]);
        $accounts = $this->reportAccountsIn(positiveIntListParam($_GET['account'] ?? null,'account',$type !== 2),2);
        $data = [
            'type' => $type,
            'start' => strictDateParam($_GET['start'] ?? null,'start date'),
            'end' => strictDateParam($_GET['end'] ?? null,'end date'),
            'account' => $accounts
        ];
        if ($data['start'] > $data['end']) { badRequest('The start date cannot be after the end date.'); }
        $rows = $this->reportModel->GetExpenses($data);
        $spec = ['title' => 'Expenses','lines' => ['Period ' . date('d-m-Y',strtotime($data['start'])) . ' to ' . date('d-m-Y',strtotime($data['end']))],
                 'columns' => [['key' => 'date','label' => 'Date','type' => 'date'],['key' => 'voucher','label' => 'Voucher No','type' => 'link'],
                               ['key' => 'account','label' => 'Expense Account','type' => 'text'],['key' => 'costcenter','label' => 'Cost Center','type' => 'text'],
                               ['key' => 'amount','label' => 'Amount','type' => 'money'],['key' => 'method','label' => 'Pay Method','type' => 'text'],
                               ['key' => 'reference','label' => 'Reference','type' => 'text']],
                 'total' => 'amount','file' => 'expenses-' . $type . '-' . $data['start'] . '-to-' . $data['end'],'sheet' => 'Expenses','widths' => [12,14,30,26,16,16,24]];
        ReportList::respond($spec,$rows,$_GET);
    }

    public function pledges()
    {
        checkrights($this->authmodel,'pledge reports');
        $data = [];
        $this->view('reports/pledges',$data);
    }
    public function pledgesrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => trim($_GET['type']),
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
            ];

            $pledges = $this->reportModel->GetPledges($data);
            $output = '';
            if ($data['type'] != 4) {
                $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Date</th>
                            <th>Pledged By</th>
                            <th>Amount Pledged</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($pledges as $pledge) {
                    $output .='
                        <tr>
                            <td>'.$pledge->pledgeDate.'</td>
                            <td>'.$pledge->pledger.'</td>
                            <td>'.number_format($pledge->amountPledged,2).'</td>
                            <td>'.number_format($pledge->amountPaid,2).'</td>
                            <td>'.number_format($pledge->balance,2).'</td>
                        </tr>';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:right">Total:</th>
                                <th id="total"></th>
                                <th id="paidtotal"></th>
                                <th id="baltotal"></th>
                            </tr>
                    </tfoot>
                </table>';
            }else{
                $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Pay Date</th>
                            <th>Paid By</th>
                            <th>Amount Paid</th>
                            <th>Payment Method</th>
                            <th>Payment Reference</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($pledges as $pledge) {
                    $output .='
                        <tr>
                            <td>'.$pledge->paymentDate.'</td>
                            <td>'.$pledge->pledger.'</td>
                            <td>'.number_format($pledge->amountPaid,2).'</td>
                            <td>'.$pledge->paymentMethod.'</td>
                            <td>'.$pledge->payReference.'</td>
                        </tr>';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:right">Total:</th>
                                <th id="total"></th>
                                <th colspan="2"></th>
                            </tr>
                    </tfoot>
                </table>';
            }
            echo $output;
        }else{
            redirect('users');
        }
    }
    public function budgetvsexpense()
    {
        checkrights($this->authmodel,'budget vs expense reports');
        $groups = $this->reportModel->GetGroups();
        $years = $this->reportModel->GetYears();
        $current = $this->reportModel->GetCurrentyear();
        $data = [
            'groups' => $groups,
            'years' => $years,
            'current' => $current
        ];
        $this->view('reports/budgetvsexpense',$data);
    }
    public function budgetvsexpenserpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET =  filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => trim($_GET['type']),
                'year' => trim($_GET['year']),
                'group' => trim($_GET['group'])
            ];
            $budgetvexpenses = $this->reportModel->GetBudgetVsExpense($data);
            $output = '';
            $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Account</th>
                            <th>Budgeted Amount</th>
                            <th>Oct</th>
                            <th>Nov</th>
                            <th>Dec</th>
                            <th>Jan</th>
                            <th>Feb</th>
                            <th>Mar</th>
                            <th>Apr</th>
                            <th>May</th>
                            <th>Jun</th>
                            <th>Jul</th>
                            <th>Aug</th>
                            <th>Sep</th>
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
                            <td>'.number_format($budgetvexpense->Oct,2).'</td>
                            <td>'.number_format($budgetvexpense->Nov,2).'</td>
                            <td>'.number_format($budgetvexpense->Dece,2).'</td>
                            <td>'.number_format($budgetvexpense->Jan,2).'</td>
                            <td>'.number_format($budgetvexpense->Feb,2).'</td>
                            <td>'.number_format($budgetvexpense->Mar,2).'</td>
                            <td>'.number_format($budgetvexpense->Apr,2).'</td>
                            <td>'.number_format($budgetvexpense->May,2).'</td>
                            <td>'.number_format($budgetvexpense->Jun,2).'</td>
                            <td>'.number_format($budgetvexpense->Jul,2).'</td>
                            <td>'.number_format($budgetvexpense->Aug,2).'</td>
                            <td>'.number_format($budgetvexpense->Sep,2).'</td>
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
    public function incomestatement()
    {
        checkrights($this->authmodel,'income statement');
        $data = [];
        $this->view('reports/incomestatement',$data);
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
        $cong = (int)$_SESSION['congId'];
        IncomeStatementReport::respondReport($db,[$cong],'',$start,$end);   // no congregation heading (decision 147)
    }

    /** One drill level (account ID): the next level of accounts or the journal lines; the rows add up to the figure clicked. */
    public function plchildaccount()
    {
        $this->view('reports/plchildaccount',[]);
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
        $cong = (int)$_SESSION['congId'];
        IncomeStatementReport::respondDrill($db,[$cong],'',$account,$start,$end,$mode,$direct,$_GET);
    }

    /** Old name-based drill URLs: nothing is queried any more. */
    public function plchildaccountdetailed()
    {
        redirect('reports/incomestatement');
        exit;
    }

    public function plchildaccountdetailedrpt()
    {
        IncomeStatementReport::fail('This link is no longer supported; open the income statement again');
    }

    public function groupsincomestatement()
    {
        checkrights($this->authmodel,'groups income statement');
        $this->view('reports/groupsincomestatement',GroupIncomeStatement::choices((new Database)->pdo(),(int)$_SESSION['congId']));
    }

    /** Group / district income statement from the journal (task 9.7, decision 155): JSON, or Excel with format=xlsx. */
    public function groupincomestatementrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        GroupIncomeStatement::respondStatement((new Database)->pdo(),(int)$_SESSION['congId'],$_GET);
    }

    public function groupplrevenuedetailed()
    {
        $this->view('reports/groupplrevenuedetailed',[]);
        exit;
    }

    public function groupplexpensedetailed()
    {
        $this->view('reports/groupplexpensedetailed',[]);
        exit;
    }

    /** The requisitions / collections behind a revenue figure (type = requisitions | collections): JSON page or Excel. */
    public function groupplrevenuedetailedrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : '';
        GroupIncomeStatement::respondDetail((new Database)->pdo(),(int)$_SESSION['congId'],$_GET,$type);
    }

    /** The expenses behind an expense figure (account = account ID): JSON page or Excel. */
    public function groupplexpensedetailedrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        GroupIncomeStatement::respondDetail((new Database)->pdo(),(int)$_SESSION['congId'],$_GET,'expenses');
    }

    /** The old period trial balance is gone (task 9.3): the trial balance has one screen, with an As Of Date. */
    public function trialbalance()
    {
        redirect('trialbalance');
        exit;
    }

    /** Balance sheet (task 9.4): FinancialReport on the journal; As Of Date only. */
    public function balancesheet()
    {
        checkrights($this->authmodel,'balance sheet');
        $data = [];
        $this->view('reports/balancesheet',$data);
    }

    /** JSON (or format=xlsx): asof=Y-m-d  */
    public function balancesheetrpt()
    {
        $db = (new Database)->pdo();
        $cong = (int)$_SESSION['congId'];
        BalanceSheetReport::serveReport($db,[$cong],'');   // no congregation heading (decision 147)
    }

    public function balancesheetdrill()
    {
        $this->view('reports/balancesheetdrill',[]);
        exit;
    }

    public function balancesheetdrillrpt()
    {
        $db = (new Database)->pdo();
        $cong = (int)$_SESSION['congId'];
        BalanceSheetReport::serveDrill($db,[$cong],'');
    }

    /** Old name-based drill URLs: nothing is queried any more */
    public function balancesheetdetailed()
    {
        redirect('reports/balancesheet');
        exit;
    }

    public function getbalancesheetdetailedrpt()
    {
        IncomeStatementReport::fail('This link is no longer supported; open the balance sheet again');
    }

    public function balancesheetchilddetails()
    {
        redirect('reports/balancesheet');
        exit;
    }

    public function balancesheetchilddetailsrpt()
    {
        IncomeStatementReport::fail('This link is no longer supported; open the balance sheet again');
    }

    public function banking()
    {
        checkrights($this->authmodel,'banking reports');
        $banks = $this->reportModel->getBanks();
        $data = ['banks' => $banks];
        $this->view('reports/banking',$data);
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
    public function pettycash()
    {
        $db = (new Database)->pdo();
        $data = ['accounts' => PettyCashReport::accounts($db,(int)$_SESSION['congId'])];
        $this->view('reports/pettycash',$data);
    }

    public function pettycashrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') { IncomeStatementReport::fail('Method not allowed',405); }
        $a = $_GET['account'] ?? null;
        if (!is_string($a) || !ctype_digit($a) || strlen($a) > 9 || (int)$a < 1) { IncomeStatementReport::fail('Invalid petty cash account'); }
        $account = (int)$a;
        $db = (new Database)->pdo();
        $cong = (int)$_SESSION['congId'];
        if (!PettyCashReport::accountAllowed($db,$cong,$account)) { IncomeStatementReport::fail('Invalid petty cash account'); }
        PettyCashReport::serve($db,[$cong],$account);
    }
    public function invoicepayment()
    {
        $data = [];
        $this->view('reports/invoicepayment', $data);
    }
    public function getcustomersupplier()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $type = strtolower(trim($_GET['type']));
            $customer_suppliers = $this->reportModel->getcustomersupplier($type);
            foreach ($customer_suppliers as $customer_supplier) {
                echo '<option value="'.$customer_supplier->ID.'">'.$customer_supplier->criteria.'</option>';
            }
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    public function paymentsrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'start' => date('Y-m-d',strtotime($_GET['start'])),
                'end' => date('Y-m-d',strtotime($_GET['end'])),
                'type' => strtolower(trim($_GET['type'])),
                'customer' => trim($_GET['customer']),
            ];

            $reports = $this->reportModel->getpaymentreport($data);
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Pay Date</th>
                            <th>Amount</th>
                            <th>PaymentMethod</th>
                            <th>PayReference</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($reports as $report) {
                        $output .= '
                            <tr>
                                <td>'.date('d-m-Y',strtotime($report->PaymentDate)).'</td>
                                <td>'.$report->Amount.'</td>
                                <td>'.$report->PaymentMethod.'</td>
                                <td>'.$report->PayReference.'</td>
                            </tr>
                        ';
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th style="text-align:right">Total:</th>
                                <th id="total"></th>
                                <th colspan="2"></th>
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
            $reqid = !empty(trim($_GET['reqid'])) ? trim($_GET['reqid']) : NULL;
            // $data = [
            //     'start' => !empty(trim($_GET['start'])) ? date('Y-m-d',strtotime($_GET['start'])) : NULL,
            //     'end' => !empty(trim($_GET['end'])) ? date('Y-m-d',strtotime($_GET['end'])) :NULL,
            //     'gid' => trim($_GET['gid']),
            // ];

            $reports = $this->reportModel->getgroupstatement($reqid);
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Transaction Date</th>
                            <th>Description</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($reports as $report) {
                        $output .= '
                            <tr>
                                <td>'.date('d-M-Y',strtotime($report->TransactionDate)).'</td>
                                <td>'.$report->Description.'</td>
                                <td>'.number_format($report->AmountIn,2).'</td>
                                <td>'.number_format($report->AmountOut,2).'</td>
                                <td>'.number_format($report->Balance,2).'</td>
                            </tr>
                        ';
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:center">Total:</th>
                                <th id="deposits"></th>
                                <th id="withdrawals"></th>
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

    
    public function journals()
    {
        $data = [];
        $this->view('reports/journals',$data);
        exit;
    }

    /** Journal report from the journal (task 9.7): JSON (whole journals, search, paging) or Excel with format=xlsx. */
    public function journalsrpt()
    {
        JournalReport::serve((new Database)->pdo(),(int)$_SESSION['congId']);
    }
    public function subaccounts()
    {
        $data = [
            'subaccounts' => $this->reportModel->GetSubaccounts(),
        ];
        $this->view('reports/subaccounts',$data);
        exit;
    }

    public function subaccountsrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
           $data = [
                'account' => !empty(trim($_GET['account'])) ? trim($_GET['account']) : NULL,
                'from' => !empty(trim($_GET['from'])) ? date('Y-m-d',strtotime($_GET['from'])) : NULL,
                'to' => !empty(trim($_GET['to'])) ? date('Y-m-d',strtotime($_GET['to'])) :NULL,
            ];
 
            $reports = $this->reportModel->GetSubAccountReport($data);
            $output = '';
            $totals = 0;
            if($data['account'] !== 'all'){
                $output .= '
                    <table class="table table-bordered table-sm" id="table">
                        <thead class="bg-lightblue">
                            <tr>
                                <th>Transaction Date</th>
                                <th>Reference</th>
                                <th>Money In</th>
                                <th>Money Out</th>
                                <th>Description</th>
                                <th>Running Balance</th>
                            </tr>
                        </thead>
                        <tbody>';
                        $balance = 0;
                        foreach($reports as $report) {
                            $moneyIn = $report->AmountIn;
                            $moneyOut = $report->AmountOut * -1;

                            $formattedIn  = $moneyIn > 0 ? number_format($moneyIn, 2) : null;
                            $formattedOut = $moneyOut > 0 ? number_format($moneyOut, 2) : null;

                            $balance += ($moneyIn - $moneyOut);
                            $formattedBalance = number_format($balance, 2);

                            $output .= '
                                <tr>
                                    <td>'.date("d-M-Y", strtotime($report->TransactionDate)).'</td>
                                    <td>'.ucwords($report->Reference).'</td>
                                    <td>'.$formattedIn.'</td>
                                    <td>'.$formattedOut.'</td>
                                    <td>'.ucwords($report->Narration).'</td>
                                    <td>'.$formattedBalance.'</td>
                                </tr>
                            ';

                            // before running balance calculation
                            // $formattedoin = $report->AmountIn  > 0 ? number_format($report->AmountIn,2) : null;
                            // $formattedout = ($report->AmountOut * -1) > 0 ? number_format($report->AmountOut * -1,2) : null;
                            // $output .= '
                            //     <tr>
                            //         <td>'.date('d-M-Y',strtotime($report->TransactionDate)).'</td>
                            //         <td>'.ucwords($report->Reference).'</td>
                            //         <td>'.$formattedoin.'</td>
                            //         <td>'.$formattedout.'</td>
                            //         <td>'.ucwords($report->Narration).'</td>
                            //     </tr>
                            // ';
                        }
                        $output .= '
                        </tbody>
                        <tfoot>
                                <tr>
                                    <th colspan="2" style="text-align:center">Total:</th>
                                    <th id="debits"></th>
                                    <th id="credits"></th>
                                    <th></th>
                                    <th></th>
                                </tr>
                        </tfoot>
                    </table>';
            }else{
                $output .= '
                    <table class="table table-bordered table-sm" id="table">
                        <thead class="bg-lightblue">
                            <tr>
                                <th>Sub Account</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>';
                        foreach($reports as $report) {
                            $totals = $totals + floatval($report->Balance);
                            $formatted_amount = number_format($report->Balance,2);
                            $output .= '
                                <tr>
                                    <td>'.ucwords($report->AccountName).'</td>
                                    <td><a target="_blank" href="'.URLROOT.'/reports/subaccountdetailed?subaccount='.$report->ID.'&asdate='.$data['from'].'">'.$formatted_amount.'</a></td>
                                </tr>
                            ';
                        }
                        $output .= '
                        </tbody>
                        <tfoot>
                                <tr>
                                    <th style="text-align:center">Total:</th>
                                    <th id="totals">'.number_format($totals,2).'</th>
                                </tr>
                        </tfoot>
                    </table>';
            }
            
            echo $output; 
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    public function subaccountdetailed()
    {
        $this->view('reports/subaccountdetailed',[]);
    } 
    public function getsubaccountdetailedrpt(){
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? (int)trim($_GET['account']) : null,
                'asdate' => isset($_GET['asdate']) && !empty(trim($_GET['asdate'])) ? date('Y-m-d',strtotime(trim($_GET['asdate']))) : null,
                'results' => []
            ];

            //validate data
            if(is_null($data['account']) || is_null($data['asdate'])){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required fields']);
                exit;
            }
            
            $results = $this->reportModel->GetDetailedSubAccountReport($data);
            if(!$results){
                http_response_code(500);
                echo json_encode(['message' => 'Invalid report type']);
                exit;
            }

            foreach($results as $result){
                array_push($data['results'],[
                    'transactionDate' => date('d-m-Y',strtotime($result->TransactionDate)),
                    'amount' => floatval($result->Amount),
                    'narration' => ucwords($result->Narration),
                    'reference' => ucwords($result->Reference),
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
    /** A posted bank must be in the report-safe bank list of the session congregation (task 9.1): 400 otherwise. */
    private function reportBank($value)
    {
        $id = positiveIntParam($value,'bank');
        if (!$this->reportModel->isReportBank($id)) { badRequest('Invalid bank.'); }
        return $id;
    }
    /** Posted account IDs must all be in the report-safe account list of the root type (empty list allowed). */
    private function reportAccountsIn(array $ids,$root)
    {
        if ($ids && array_diff($ids,$this->reportModel->reportAccountIds($root))) { badRequest('Invalid account.'); }
        return $ids;
    }
    /** The Ledger Statement account: required, and in the report-safe account list of the session congregation. */
    private function reportLedgerAccount($value)
    {
        $id = positiveIntParam($value,'account');
        if (!$this->reusableModel->reportAccountAllowed($id)) { badRequest('Invalid account.'); }
        return $id;
    }
    public function ledger_statement()
    {
        $data = [
            'accounts' => $this->reusableModel->GetAccountsAll()
        ];
        $this->view('reports/ledger_statement',$data);
    } 

    /** Ledger Statement data (task 9.3): JSON, or format=xlsx; the account must be in the report-safe selector, every descendant when subledgers=1 */
    public function ledgerstatementrpt()
    {
        $account = $this->reportLedgerAccount($_GET['account'] ?? null);
        $db = (new Database)->pdo();
        $cong = (int)$_SESSION['congId'];
        LedgerStatementReport::serve($db,[$cong],'',$account);
    }

    public function unaccounted_requisitions()
    {
        // $data = [
        //     'subaccounts' => $this->reportModel->GetSubaccounts(),
        // ];
        $this->view('reports/unaccountedrequisitions',[]);
        exit;
    }

    public function unaccounted_requisition_rpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ;

            $data = [
                 'criteria' => isset($_GET['group']) && !empty(trim($_GET['group'])) ? $_GET['group'] : null,
            ];
 
            $rows = $this->reportModel->GetUnaccountedReport($data);


            $output = '';
            $output .='
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Date Requested</th>
                            <th>Group/District</th>
                            <th>Amount Requested</th>
                            <th>Amount Approved</th>
                            <th>Unaccounted Amount</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($rows as $row) {
                        if(floatval($row->UnaccountedAmount) > 0){                       
                            $output .= '
                                <tr>
                                    <td>'.date('d-m-Y',strtotime($row->RequisitionDate)).'</td>
                                    <td>'.$row->GroupDistrict.'</td>
                                    <td>'.number_format($row->AmountRequested,2).'</td>
                                    <td>'.number_format($row->AmountApproved,2).'</td>
                                    <td>'.number_format($row->UnaccountedAmount,2).'</td>
                                </tr>
                            ';
                        }
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th style="text-align:center" colspan="2">Total:</th>
                                <th></th>
                                <th></th>
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
}
