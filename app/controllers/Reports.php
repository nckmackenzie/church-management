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
            if((int)$data['type'] !== 3) {
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
            }else{
                $output .= '
                    <table id="table" class="table table-striped table-bordered table-sm">
                        <thead class="bg-lightblue">
                            <tr>
                                <th>District</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>';
                        foreach ($contributions as $contribution) {
                            $output .='
                                <tr>
                                    <td>'.strtoupper($contribution->districtName).'</td>
                                    <td>'.number_format($contribution->total_amount,2).'</td>
                                </tr>';
                        }
                        $output .= '
                            </tbody>
                            <tfoot>
                                    <tr>
                                        <th>Total:</th>
                                        <th id="total"></th>
                                    </tr>
                            </tfoot>
                    </table>';
            }
            
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
            $revenue_total = 0;
            //expenses
            $expenses = $this->reportModel->GetExpensesPL($data);

            // $expenses_total = floatval($admincost) + floatval($hosptcost) + floatval($optcost) + floatval($staffcost);
            $expenses_total = 0;
            
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
                        foreach($revenues as $revenue){
                            $revenue_total += floatval($revenue->credit);
                            $output .='
                            <tr>
                                <td>'.ucwords($revenue->parentaccount).'</td>
                                <td><a target="_blank" href="'.URLROOT.'/reports/plchildaccount?account='.$revenue->parentaccount.'&sdate='.$data['start'].'&edate='.$data['end'].'">'.number_format($revenue->credit,2).'</a></td>
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
                    foreach($expenses as $expense){
                        $expenses_total += floatval($expense->debit);
                        $output .='
                        <tr>
                            <td>'.ucwords($expense->parentaccount).'</td>
                            <td><a target="_blank" href="'.URLROOT.'/reports/plchildaccount?account='.$expense->parentaccount.'&sdate='.$data['start'].'&edate='.$data['end'].'">'.number_format($expense->debit,2).'</a></td>
                        </tr>';
                    }
                    $profit_loss = ($revenue_total - $expenses_total);    
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

    public function plchildaccount()
    {
        $data = [];
        $this->view('reports/plchildaccount',$data);
        exit;
    }

    public function plchildaccountrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $data = [
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
                'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? strtolower(trim($_GET['account'])) : null,
                'totalamount' => 0,
                'results' => []
            ];
            //validate
            if(is_null($data['sdate']) || is_null($data['edate']) || is_null($data['account']))
            {
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Unable to get all fields']);
                exit;
            }

            $details = $this->reportModel->GetChildAccounts($data);

            if(empty($details))
            {
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'No details found for this account for specified period']);
                exit;
            }

            foreach($details as $detail)
            {
                $data['totalamount'] += floatval($detail->amount);
                array_push($data['results'],[
                    'account' => ucwords($detail->account),
                    'amount' => $detail->amount,
                ]);
            }

            echo json_encode(['success' => true,'results' => $data['results'],"total" => $data['totalamount']]);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit();
        }
    }

    public function plchildaccountdetailed()
    {
        $data = [];
        $this->view('reports/plchildaccountdetailed',$data);
        exit;
    }

    public function plchildaccountdetailedrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $data = [
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
                'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? strtolower(trim($_GET['account'])) : null,
                'accounttype' => '',
                'totalamount' => 0,
                'results' => []
            ];
            //validate
            if(is_null($data['sdate']) || is_null($data['edate']) || is_null($data['account']))
            {
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Unable to get all fields']);
                exit;
            }

            $data['accounttype'] = $this->reportModel->GetAccountType($data['account']);
            $details = $this->reportModel->GetPlChildAccountDetailed($data);

            if(empty($details))
            {
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'No details found for this account for specified period']);
                exit;
            }

            foreach($details as $detail)
            {
                $data['totalamount'] += floatval($detail->amount);
                array_push($data['results'],[
                    'transactionDate' => date('d-m-Y',strtotime($detail->transactionDate)),
                    'account' => ucwords($detail->account),
                    'amount' => $detail->amount,
                    'narration' => is_null($detail->narration) ? '' : ucfirst($detail->narration),
                    'transaction' => ucfirst($detail->TransactionType),
                    'parentAccount' => ucfirst($detail->parentaccount),
                ]);
            }

            echo json_encode(['success' => true,'results' => $data['results'],"total" => $data['totalamount']]);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit();
        }
    }


    // public function pldetailed()
    // {
    //     $data = [];
    //     $this->view('reports/pldetailed',$data);
    //     exit;
    // }

    // public function pldetailedrpt()
    // {
    //     if($_SERVER['REQUEST_METHOD'] === 'GET')
    //     {
    //         $data = [
    //             'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
    //             'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
    //             'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? strtolower(trim($_GET['account'])) : null,
    //             'accounttype' => '',
    //             'totalamount' => 0,
    //             'results' => []
    //         ];
    //         //validate
    //         if(is_null($data['sdate']) || is_null($data['edate']) || is_null($data['account']))
    //         {
    //             http_response_code(400);
    //             echo json_encode(['success' => false,'message' => 'Unable to get all fields']);
    //             exit;
    //         }

    //         $data['accounttype'] = $this->reportModel->GetAccountType($data['account']);
    //         $details = $this->reportModel->GetPlDetailed($data);

    //         if(empty($details))
    //         {
    //             http_response_code(400);
    //             echo json_encode(['success' => false,'message' => 'No details found for this account for specified period']);
    //             exit;
    //         }

    //         foreach($details as $detail)
    //         {
    //             $data['totalamount'] += floatval($detail->amount);
    //             array_push($data['results'],[
    //                 'transactionDate' => date('d-m-Y',strtotime($detail->transactionDate)),
    //                 'account' => ucwords($detail->account),
    //                 'amount' => $detail->amount,
    //                 'narration' => is_null($detail->narration) ? '' : ucfirst($detail->narration),
    //                 'transaction' => ucfirst($detail->TransactionType),
    //                 'parentAccount' => ucfirst($detail->parentaccount),
    //             ]);
    //         }

    //         echo json_encode(['success' => true,'results' => $data['results'],"total" => $data['totalamount']]);
    //         exit;
    //     }
    //     else
    //     {
    //         redirect('users/deniedaccess');
    //         exit();
    //     }
    // }


    public function groupsincomestatement()
    {
        checkrights($this->authmodel,'groups income statement');
        $data = ['groups' => $this->reportModel->GetGroups()];
        $this->view('reports/groupsincomestatement',$data);
    }

    public function groupincomestatementrpt()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'group' => isset($_GET['group']) && !empty(trim($_GET['group'])) ? (int)trim($_GET['group']) : null,
                'start' => isset($_GET['start']) && !empty(trim($_GET['start'])) ? date('Y-m-d',strtotime(trim($_GET['start']))) : null,
                'end' => isset($_GET['end']) && !empty(trim($_GET['end'])) ? date('Y-m-d',strtotime(trim($_GET['end']))) : null
            ];

            //validation
            if(is_null($data['group']) || is_null($data['start']) || is_null($data['end'])) :
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Please provide all required fields']);
                exit;
            endif;

            //values 
            $revenue = $this->reportModel->GetGroupRevenues($data);
            $collections = $this->reportModel->GetGroupCollections($data);
            $revenue_total = floatval($revenue) + floatval($collections);
            //expenses
            $expenses = $this->reportModel->GetGroupExpensesPL($data);
            $expenses_total = 0;
            
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
                    if($revenue > 0):
                        $output .='
                            <tr>
                                <td>Group Requisitions</td>
                                <td><a target="_blank" href="'.URLROOT.'/reports/groupplrevenuedetailed?type=requisitions&group='.$data['group'].'&sdate='.$data['start'].'&edate='.$data['end'].'">'.number_format($revenue,2).'</a></td>
                            </tr>
                        ';
                    endif;
                    if($collections > 0):
                        $output .='
                            <tr>
                                <td>Group Collections</td>
                                <td><a target="_blank" href="'.URLROOT.'/reports/groupplrevenuedetailed?type=collections&group='.$data['group'].'&sdate='.$data['start'].'&edate='.$data['end'].'">'.number_format($collections,2).'</a></td>
                            </tr>
                        ';
                    endif;
                    $output .='
                        <tr>
                            <th>Revenue Total</th>
                            <th>'.number_format($revenue_total,2).'</th>
                        </tr>
                        <tr style="background-color: #ed6b6b">
                            <td colspan="2">Expenses</td>
                        </tr>';
                    foreach($expenses as $expense){
                        $expenses_total += floatval($expense->Amount);
                        $output .='
                        <tr>
                            <td>'.ucwords($expense->accountType).'</td>
                            <td><a target="_blank" href="'.URLROOT.'/reports/groupplexpensedetailed?account='.$expense->ID.'&group='.$data['group'].'&sdate='.$data['start'].'&edate='.$data['end'].'">'.number_format($expense->Amount,2).'</a></td>
                        </tr>';
                    }
                    $profit_loss = ($revenue_total - $expenses_total);    
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

    public function groupplrevenuedetailedrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $data = [
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
                'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null,
                'group' => isset($_GET['group']) && !empty(trim($_GET['group'])) ? (int)trim($_GET['group']) : null,
                'totalamount' => 0,
                'results' => []
            ];
            //validate
            if(is_null($data['sdate']) || is_null($data['edate']) || is_null($data['type']) || is_null($data['group']))
            {
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Unable to get all fields']);
                exit;
            }

            $details = $this->reportModel->GetGroupPlRevenueDetailed($data);

            if(empty($details))
            {
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'No details found for this account for specified period']);
                exit;
            }

            foreach($details as $detail)
            {
                $data['totalamount'] += floatval($detail->Amount);
                array_push($data['results'],[
                    'transactionDate' => date('d-m-Y',strtotime($detail->TransactionDate)),
                    'amount' => $detail->Amount,
                    'narration' => is_null($detail->Narration) ? '' : ucfirst($detail->Narration),
                ]);
            }

            echo json_encode(['success' => true,'results' => $data['results'],"total" => $data['totalamount']]);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit();
        }
    }

    public function groupplexpensedetailedrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $data = [
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
                'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? trim($_GET['account']) : null,
                'group' => isset($_GET['group']) && !empty(trim($_GET['group'])) ? trim($_GET['group']) : null,
                'totalamount' => 0,
                'results' => []
            ];
            //validate
            if(is_null($data['sdate']) || is_null($data['edate']) || is_null($data['account']) || is_null($data['group']))
            {
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Unable to get all fields']);
                exit;
            }

            $details = $this->reportModel->GetGroupPlExpenseDetailed($data);

            if(empty($details))
            {
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'No details found for this account for specified period']);
                exit;
            }

            foreach($details as $detail)
            {
                $data['totalamount'] += floatval($detail->amount);
                array_push($data['results'],[
                    'transactionDate' => date('d-m-Y',strtotime($detail->expenseDate)),
                    'amount' => $detail->amount,
                    'reference' => is_null($detail->paymentReference) ? '' : ucfirst($detail->paymentReference),
                    'narration' => is_null($detail->narration) ? '' : ucfirst($detail->narration),
                ]);
            }

            echo json_encode(['success' => true,'results' => $data['results'],"total" => $data['totalamount']]);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit();
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

            $assetsTotal = 0;
            $liablityequitiesTotal = 0;
            foreach($assets as $asset){
                $assetsTotal += floatval($asset->balance);
            }
            foreach($liablityequities as $liabilityequity){
                $liablityequitiesTotal += floatval($liabilityequity->balance);
            }
            // $assetsTotal = $this->reportModel->GetAssetsTotal($todate);
            // $liablityequitiesTotal = $this->reportModel->GetLiabilityEquityTotal($todate);
            // $netIncome = $this->reportModel->GetNetIncome($todate);
            // $totalLiablityEquity = floatval($liablityequitiesTotal) + floatval($netIncome);
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Balance Sheet As Of '.date("d/m/Y", strtotime($todate)).'</th>
                            <th></th>
                        </tr>
                    </thead>   
                    <tbody>
                        <tr class="bg-olive">
                            <td>Assets</th>
                            <td></th>
                        </tr>';
                    foreach($assets as $asset){
                        $output .='
                        <tr>
                            <td>'.strtoupper($asset->parentaccount).'</td>
                            <td><a target="_blank" href="'.URLROOT.'/reports/balancesheetdetailed?account='.strtolower($asset->parentaccount).'&asdate='.$todate.'">'.number_format($asset->balance,2).'</a></td>
                        </tr>';
                    }
                    $output .='
                        <tr style="background-color: #abebbc;">
                            <td style="font-weight: 700;">Assets Total</td>
                            <td style="font-weight: 700;">'.number_format($assetsTotal,2).'</td>
                        </tr>
                        <tr style="background-color: #e85858; color: #fff;">
                            <td>Liability & Equity</th>
                            <td></th>
                        </tr>';
                    foreach ($liablityequities as $liabilityequity) {
                        $output .='
                        <tr>
                             <td>'.strtoupper($liabilityequity->parentaccount).'</td>
                             <td><a target="_blank" href="'.URLROOT.'/reports/balancesheetdetailed?account='.strtolower($liabilityequity->parentaccount).'&asdate='.$todate.'">'.number_format((floatval($liabilityequity->balance)),2).'</a></td>
                        </tr>';
                    } 
                    $output .='
 
                        <tr style="background-color: #f59595;">
                            <td style="font-weight: 700;">Liablity & Equity Total</td>
                            <td style="font-weight: 700;">'.number_format($liablityequitiesTotal,2).'</td>
                        </tr>
                    </tbody>
                </table>';   
            echo $output;
        }else {
            redirect('users');
        }
    }

    public function balancesheetdetailed()
    {
        $this->view('reports/balancesheetdetailed',[]);
    }

    public function getbalancesheetdetailedrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? trim($_GET['account']) : null,
                'asdate' => isset($_GET['asdate']) && !empty(trim($_GET['asdate'])) ? date('Y-m-d',strtotime(trim($_GET['asdate']))) : null,
            ];

            //validate data
            if(is_null($data['account']) || is_null($data['asdate'])){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required fields']);
                exit;
            }
           
            $results = $this->reportModel->GetDetailedBalanceSheetAccountReport($data);
            $totals=0;
            if(!$results){
                http_response_code(500);
                echo json_encode(['message' => 'Invalid report type']);
                exit;
            }

            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Account</th>
                            <th></th>
                        </tr>
                    </thead>   
                    <tbody>';
                    foreach($results as $result){
                        $totals += floatval($result->balance);
                        $output .='
                        <tr>
                            <td>'.strtoupper($result->account).'</td>
                            <td><a target="_blank" href="'.URLROOT.'/reports/balancesheetchilddetails?account='.strtolower($result->account).'&asdate='.$data['asdate'].'">'.number_format($result->balance,2).'</a></td>
                        </tr>';
                    }
                    $output .='
                        </tbody>
                        <tfoot>
                            <tr>
                                <th style="text-align:right">Total:</th>
                                <th>'.number_format($totals,2).'</th>
                            </tr>
                        </tfoot>
                    </table>';
            echo $output;
       }
        else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function balancesheetchilddetails()
    {
        $this->view('reports/balancesheetchilddetails',[]);
    }

    public function balancesheetchilddetailsrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? trim($_GET['account']) : null,
                'asdate' => isset($_GET['asdate']) && !empty(trim($_GET['asdate'])) ? date('Y-m-d',strtotime(trim($_GET['asdate']))) : null,
                'openingbal' => 0,
                'yearStartDate' => null,
                'accountTypeId' => null
            ];

            //validate data
            if(is_null($data['account']) || is_null($data['asdate'])){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required fields']);
                exit;
            }

            $openingBalanceDetails = $this->reportModel->GetBalanceSheetItemOpeningBalance($data);
            $data['openingbal'] = $openingBalanceDetails['openingBalance'];
            $data['yearStartDate'] = $openingBalanceDetails['yearStartDate'];
            $data['accountTypeId'] = $openingBalanceDetails['accountTypeId'];

            $results = $this->reportModel->GetChildDetailedBalanceSheetAccountReport($data);
            $totals=0;
            if(!$results){
                http_response_code(500);
                echo json_encode(['message' => 'Something went wrong while fetching report. Please try again']);
                exit;
            }

            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Transaction Date</th>
                            <th>Amount</th>
                            <th>Narration</th>
                            <th>Transaction Type</th>
                        </tr>
                    </thead>   
                    <tbody>
                        <tr>
                            <td>'.date('d/M/Y',strtotime($data['yearStartDate'])).'</td>
                            <td>'.number_format($data['openingbal'],2).'</td>
                            <td>Opening Balance</td>
                            <td></td>
                        </tr>';                    
                    foreach($results as $result){
                        // $totals += floatval($result->amount);
                        $output .='
                        <tr>
                            <td>'.date('d/m/Y',strtotime($result->transactionDate)).'</td>
                            <td>'.number_format($result->amount,2).'</td>
                            <td>'.ucwords($result->narration).'</td>
                            <td>'.ucwords($result->TransactionType).'</td>
                        </tr>';
                    }
                    $output .='
                        </tbody>
                        <tfoot>
                            <tr>
                                <th style="text-align:right">Total:</th>
                                <th id="totals"></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>';
            echo $output;
       }
        else{
            redirect('users/deniedaccess');
            exit;
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
                                <td>'.$util->Debit.'</td>
                                <td>'.$util->Credit.'</td>
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

    public function journalsrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
           $data = [
                'from' => !empty(trim($_GET['from'])) ? date('Y-m-d',strtotime($_GET['from'])) : NULL,
                'to' => !empty(trim($_GET['to'])) ? date('Y-m-d',strtotime($_GET['to'])) :NULL,
            ];

            $reports = $this->reportModel->GetJournalReport($data);
            $output = '';
            $output .= '
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Journal No</th>
                            <th>Transaction Date</th>
                            <th>Account</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($reports as $report) {
                        $output .= '
                            <tr>
                                <td>'.$report->journalNo.'</td>
                                <td>'.date('d-M-Y',strtotime($report->transactionDate)).'</td>
                                <td>'.ucwords($report->account).'</td>
                                <td>'.number_format($report->debit,2).'</td>
                                <td>'.number_format($report->credit,2).'</td>
                                <td>'.ucwords($report->narration).'</td>
                            </tr>
                        ';
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th colspan="3" style="text-align:center">Total:</th>
                                <th id="debits"></th>
                                <th id="credits"></th>
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
                            </tr>
                        </thead>
                        <tbody>';
                        foreach($reports as $report) {
                            $formattedoin = $report->AmountIn  > 0 ? number_format($report->AmountIn,2) : null;
                            $formattedout = ($report->AmountOut * -1) > 0 ? number_format($report->AmountOut * -1,2) : null;
                            $output .= '
                                <tr>
                                    <td>'.date('d-M-Y',strtotime($report->TransactionDate)).'</td>
                                    <td>'.ucwords($report->Reference).'</td>
                                    <td>'.$formattedoin.'</td>
                                    <td>'.$formattedout.'</td>
                                    <td>'.ucwords($report->Narration).'</td>
                                </tr>
                            ';
                        }
                        $output .= '
                        </tbody>
                        <tfoot>
                                <tr>
                                    <th colspan="2" style="text-align:center">Total:</th>
                                    <th id="debits"></th>
                                    <th id="credits"></th>
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
    public function ledger_statement()
    {
        $data = [
            'accounts' => $this->reusableModel->GetAccountsAll()
        ];
        $this->view('reports/ledger_statement',$data);
    } 

    public function ledgerstatementrpt()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ;

            $data = [
                 'account' => isset($_GET['account']) && !empty(trim($_GET['account'])) ? (int)$_GET['account'] : null,
                 'from' => isset($_GET['from']) && !empty(trim($_GET['from'])) ? date('Y-m-d',strtotime(trim($_GET['from']))) : null,
                 'to' => isset($_GET['from']) && !empty(trim($_GET['to'])) ? date('Y-m-d',strtotime(trim($_GET['to']))) : null,
                 'subledgers' => isset($_GET['subledgers']) && !empty(trim($_GET['subledgers'])) ? (int)$_GET['subledgers'] : 0,
            ];
 
            $rows = $this->reportModel->GetAccountStatement($data);
            $debitsTotal = 0;
            $creditsTotal = 0;

            $output = '';
            $output .='
                <table class="table table-bordered table-sm" id="table">
                    <thead class="bg-lightblue">
                        <tr>
                            <th>Date</th>
                            <th>Narraion</th>
                            <th>Reference</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>';
                    foreach($rows as $row) {
                        $debitsTotal += floatval($row->debit);
                        $creditsTotal += floatval($row->credit);
                        $debit = floatval($row->debit) == 0 ? '' : number_format($row->debit,2);
                        $credit = floatval($row->credit) == 0 ? '' : number_format($row->credit,2);
                       
                        $output .= '
                            <tr>
                                <td>'.date('d-m-Y',strtotime($row->transactionDate)).'</td>
                                <td>'.ucwords($row->narration).'</td>
                                <td>'.ucwords($row->reference).'</td>
                                <td>'.$debit.'</td>
                                <td>'.$credit.'</td>
                                <td>'.number_format($row->runningBalance,2).'</td>
                            </tr>
                        ';
                    }
                    $output .= '
                    </tbody>
                    <tfoot>
                            <tr>
                                <th style="text-align:center" colspan="3">Total:</th>
                                <th id="debitsTotals">'.number_format($debitsTotal,2).'</th>
                                <th id="creditsTotals">'.number_format($creditsTotal,2).'</th>
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