<?php
class Reports extends Controller {
    public function __construct()
    {
       if (!isset($_SESSION['userId'])) {
           redirect('users');
           exit;
       }
       $this->authmodel = $this->model('Auth');
       $this->reportModel = $this->model('Report');
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
                            <th>ID No</th>
                            <th>Contact</th>
                            <th>District</th>
                            <th>Remark</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($members as $member) {
                    $output .= '
                        <tr>
                            <td>'.$member->memberName.'</td>
                            <td>'.$member->gender.'</td>
                            <td>'.$member->idNo.'</td>
                            <td>'.$member->contact.'</td>
                            <td>'.$member->districtName.'</td>
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
    public function contributionsrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => trim($_GET['type']),
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
                // 'account' => !empty($_GET['account']) ? $_GET['account'] : ''
                'account' => !empty($_GET['account']) ? join(",",$_GET['account']) : ''
            ];
        //    echo join(",",$data['account']);
            $contributions = $this->reportModel->GetContributions($data);
            // print_r($contributions);
            $output = '';
            $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Date</th>
                            <th>Contribution Account</th>
                            <th>Contributed By</th>
                            <th>Amount</th>
                            <th>Pay Method</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($contributions as $contribution) {
                    $output .='
                        <tr>
                            <td>'.$contribution->contdate.'</td>
                            <td>'.$contribution->conttype.'</td>
                            <td>'.$contribution->cont.'</td>
                            <td>'.number_format($contribution->amount,2).'</td>
                            <td>'.$contribution->paymethod.'</td>
                            <td>'.$contribution->paymentReference.'</td>
                        </tr>';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="3" style="text-align:right">Total:</th>
                                <th id="total"></th>
                                <th colspan="2"></th>
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
        checkrights($this->authmodel,'expenses reports');
        $accounts = $this->reportModel->GetAccounts(2);
        $data = ['accounts' => $accounts];
        $this->view('reports/expenses',$data);
    }
    public function expensesrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => trim($_GET['type']),
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
                'account' => !empty($_GET['account']) ? join(",",$_GET['account']) : ''
            ];
        
            $expenses = $this->reportModel->GetExpenses($data);
           
            $output = '';
            $output .= '
                <table id="table" class="table table-striped table-bordered table-sm">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Date</th>
                            <th>Voucher No</th>
                            <th>Expense Account</th>
                            <th>Cost Center</th>
                            <th>Amount</th>
                            <th>Pay Method</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>';
                foreach ($expenses as $expense) {
                    $output .='
                        <tr>
                            <td>'.$expense->expenseDate.'</td>
                            <td>'.$expense->voucherNo.'</td>
                            <td>'.$expense->accountType.'</td>
                            <td>'.$expense->costcenter.'</td>
                            <td>'.number_format($expense->amount,2).'</td>
                            <td>'.$expense->paymethod.'</td>
                            <td>'.$expense->payref.'</td>
                        </tr>';
                }
                $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="4" style="text-align:right">Total:</th>
                                <th id="total"></th>
                                <th colspan="2"></th>
                            </tr>
                    </tfoot>
                </table>';
            echo $output;
        }else{
            redirect('users');
        }
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
    public function incomestatement()
    {
        checkrights($this->authmodel,'income statement');
        $data = [];
        $this->view('reports/incomestatement',$data);
    }
    public function incomestatementrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'start' => isset($_GET['start']) && !empty(trim($_GET['start'])) ? date('Y-m-d',strtotime(trim($_GET['start']))) : null,
                'end' => isset($_GET['end']) && !empty(trim($_GET['end'])) ? date('Y-m-d',strtotime(trim($_GET['end']))) : null
            ];

            //validation
            if(is_null($data['start']) || is_null($data['end'])) :
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please provide all required fields']);
                exit;
            endif;

            //values 
            $revenues = $this->reportModel->GetRevenues($data);
            $tithesofferings = $revenues[0];
            $mmfcollections = $revenues[1];
            $othercollections = $revenues[2];
            $revenue_total = floatval($tithesofferings) + floatval($mmfcollections) + floatval($othercollections);
            //expenses
            $expenses = $this->reportModel->GetExpensesPL($data);
            $admincost = $expenses[0];
            $hosptcost = $expenses[1];
            $optcost = $expenses[2];
            $staffcost = $expenses[3];

            $expenses_total = floatval($admincost) + floatval($hosptcost) + floatval($optcost) + floatval($staffcost);
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
                        </tr>
                        <tr>
                            <td>Tithes &amp; Offerings</td>
                            <td>'.number_format($tithesofferings,2).'</td>
                        </tr>
                        <tr>
                            <td>MMF Collections</td>
                            <td>'.number_format($mmfcollections,2).'</td>
                        </tr>
                        <tr>
                            <td>Other Collections</td>
                            <td>'.number_format($othercollections,2).'</td>
                        </tr>
                        <tr>
                            <th>Revenue Total</th>
                            <th>'.number_format($revenue_total,2).'</th>
                        </tr>
                        <tr style="background-color: #ed6b6b">
                            <td colspan="2">Expenses</td>
                        </tr>
                        <tr>
                            <td>Administrative Costs</td>
                            <td>'.number_format($admincost,2).'</td>
                        </tr>
                        <tr>
                            <td>Hospitality Costs</td>
                            <td>'.number_format($hosptcost,2).'</td>
                        </tr>
                        <tr>
                            <td>Operation Costs</td>
                            <td>'.number_format($optcost,2).'</td>
                        </tr>
                        <tr>
                            <td>Staff Expenses</td>
                            <td>'.number_format($staffcost,2).'</td>
                        </tr>
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
        checkrights($this->authmodel,'trial balance');
        $data = [];
        $this->view('reports/trialbalance',$data);
    }
    public function trialbalancerpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'start' => trim($_GET['start']),
                'end' => trim($_GET['end']),
            ];
            $accounts_balances = $this->reportModel->GetTrialBalance($data);
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
        checkrights($this->authmodel,'balance sheet');
        $data = [];
        $this->view('reports/balancesheet',$data);
    }
    public function balancesheetrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $todate = trim($_GET['todate']);
            $assets = $this->reportModel->GetAssets($todate);
            $liablityequities = $this->reportModel->GetLiablityEquity($todate);
            $assetsTotal = $this->reportModel->GetAssetsTotal($todate);
            $liablityequitiesTotal = $this->reportModel->GetLiabilityEquityTotal($todate);
            $netIncome = $this->reportModel->GetNetIncome($todate);
            $totalLiablityEquity = floatval($liablityequitiesTotal) + floatval($netIncome);
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Balance Sheet As Of '.date("d/m/Y", strtotime($todate)).'</th>
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
    public function pettycash()
    {
        $data = [];
        $this->view('reports/pettycash',$data);
    }

    public function pettycashrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'start' => date('Y-m-d',strtotime($_GET['start'])),
                'end' => date('Y-m-d',strtotime($_GET['end'])),
            ];

            $pettycashutils = $this->reportModel->pettycashutil($data);
            $debitstotal = floatval($this->reportModel->debitcredittotal($data)[0]);
            $creditstotal = floatval($this->reportModel->debitcredittotal($data)[1]);
            $openingbal = floatval($this->reportModel->debitcredittotal($data)[2]);
            $balance = ($debitstotal + $openingbal) - $creditstotal;
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Date</th>
                            <th>Narration</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($pettycashutils as $util) {
                        $output .= '
                            <tr>
                                <td>'.date('d-m-Y',strtotime($util->TransactionDate)).'</td>
                                <td>'.$util->Narration.'</td>
                                <td>'.number_format($util->Debit,2).'</td>
                                <td>'.number_format($util->Credit,2).'</td>
                                <td>'.$util->Reference.'</td>
                            </tr>
                        ';
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="2" style="text-align:right">Total:</th>
                                <th id="debittotal"></th>
                                <th id="credittotal"></th>
                                <th id="balance">'.number_format($balance,2).'</th>
                            </tr>
                    </tfoot>
                </table>';
            echo $output;        
        }else{
            redirect('users/deniedaccess');
            exit();
        }
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