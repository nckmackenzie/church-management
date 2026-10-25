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
    /** A posted bank must be in the report-safe bank list of the session congregation (task 9.1): 400 otherwise. */
    private function reportBank($value)
    {
        $id = positiveIntParam($value,'bank');
        if (!$this->bankreconModel->isReportBank($id)) { badRequest('Invalid bank.'); }
        return $id;
    }
    /** Strict Y-m-d dates with from <= to (task 9.5): 400 otherwise. Returns [from, to]. */
    private function reportDates($from,$to,$fromName = 'from',$toName = 'to')
    {
        $from = strictDateParam($from,$fromName);
        $to = strictDateParam($to,$toName);
        if ($from > $to) { badRequest('Invalid dates: ' . $fromName . ' is after ' . $toName . '.'); }
        return [$from,$to];
    }
    /** The statement balance typed by the user, as a decimal with at most two places: cents, 400 otherwise. */
    private function statementCents($value)
    {
        $v = is_string($value) ? trim($value) : '';
        if (!preg_match('/^-?\d{1,13}(\.\d{1,2})?$/D',$v)) { badRequest('Invalid balance: expected an amount with at most two decimal places.'); }
        return FinancialReport::cents($v);
    }
    private static function e($s)
    {
        return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
    }
    private static function m($cents)
    {
        return FinancialReport::money((int)$cents);
    }
    /** An amount cell: a link to the cashbook rows behind it when it is not zero. */
    private function amountCell($cents,$page,$type,$data)
    {
        if ((int)$cents === 0) { return '<td>' . self::m($cents) . '</td>'; }
        $route = URLROOT . '/bankreconcilliations/' . $page . '?type=' . $type . '&bank=' . (int)$data['bank']
               . '&sdate=' . self::e($data['from']) . '&edate=' . self::e($data['to']);
        return '<td><a href="' . $route . '" target="_blank">' . self::m($cents) . '</a></td>';
    }
    private function row($label,$cell)
    {
        return '<tr><td>' . self::e($label) . '</td>' . $cell . '</tr>';
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
           $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ?: [];
           $data = ['bank' => $this->reportBank($_GET['bank'] ?? null)];
           [$data['from'],$data['to']] = $this->reportDates($_GET['from'] ?? null,$_GET['to'] ?? null);
           $statement = $this->statementCents($_GET['balance'] ?? null);

           // one read per request: the bank's journal balance at `to` and the cashbook totals of the period
           $a = $this->bankreconModel->getAmountsrecon($data);
           $book = $a['book'];
           $expected = $book - $a['unclearedDeposits'] + $a['unclearedWithdrawals'];
           $variance = $statement - $expected;

           $output = '<table id="table" class="table table-striped table-bordered table-sm">'
                   . '<thead><th>Bank Reconcilliation ' . date('d-m-Y',strtotime($data['from'])) . ' - ' . date('d-m-Y',strtotime($data['to'])) . '</th><th></th></thead><tbody>'
                   . $this->row('Book balance at ' . date('d-m-Y',strtotime($data['to'])) . ' (per the ledger journal)','<td>' . self::m($book) . '</td>')
                   . $this->row('Less:uncleared deposits (per cashbook)',$this->amountCell($a['unclearedDeposits'],'uncleared','deposit',$data))
                   . $this->row('Add:uncleared withdrawals (per cashbook)',$this->amountCell($a['unclearedWithdrawals'],'uncleared','withdraw',$data))
                   . $this->row('Expected Balance','<td>' . self::m($expected) . '</td>')
                   . $this->row('Actual/Bank Balance','<td>' . self::m($statement) . '</td>')
                   . $this->row('Variance','<td>' . self::m($variance) . '</td>')
                   . '</tbody></table>';
           echo $output;

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
    public function cashflow_statement()
    {
        $banks = $this->bankreconModel->getBanks();
        $data = ['banks' => $banks];
        $this->view('bankreconcilliations/cashflow-statement',$data);
    }
    public function cashflow_report()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
           $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ?: [];
           $data = ['bank' => $this->reportBank($_GET['bank'] ?? null)];
           [$data['from'],$data['to']] = $this->reportDates($_GET['from'] ?? null,$_GET['to'] ?? null);
           $statement = $this->statementCents($_GET['balance'] ?? null);

           // one read per request: the bank's journal balance before `from` and the cashbook totals of the period
           $a = $this->bankreconModel->getAmounts($data);
           $expected = $a['opening'] + $a['clearedDeposits'] - $a['clearedWithdrawals'] - $a['unclearedDeposits'] + $a['unclearedWithdrawals'];
           $variance = $statement - $expected;

           $output = '<table id="table" class="table table-striped table-bordered table-sm">'
                   . '<thead><th>Cashflow Statement ' . date('d-m-Y',strtotime($data['from'])) . ' - ' . date('d-m-Y',strtotime($data['to'])) . '</th><th></th></thead><tbody>'
                   . $this->row('Opening Balance (book balance per the ledger journal)','<td>' . self::m($a['opening']) . '</td>')
                   . $this->row('Cleared Deposits (per cashbook)',$this->amountCell($a['clearedDeposits'],'cleared','deposit',$data))
                   . $this->row('Cleared Withdrawals (per cashbook)',$this->amountCell($a['clearedWithdrawals'],'cleared','withdraw',$data))
                   . $this->row('Uncleared Deposits (per cashbook)',$this->amountCell($a['unclearedDeposits'],'uncleared','deposit',$data))
                   . $this->row('Uncleared Withdrawals (per cashbook)',$this->amountCell($a['unclearedWithdrawals'],'uncleared','withdraw',$data))
                   . $this->row('Expected Balance (opening book balance and the cashbook movements above)','<td>' . self::m($expected) . '</td>')
                   . $this->row('Actual/Bank Balance','<td>' . self::m($statement) . '</td>')
                   . $this->row('Variance','<td>' . self::m($variance) . '</td>')
                   . '</tbody></table>';
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

    /** the cashbook rows behind a total: bank, strict dates and a deposit / withdraw type, else 400 */
    private function listData()
    {
        $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW) ?: [];
        $type = $_GET['type'] ?? null;
        if (!in_array($type,['deposit','withdraw'],true)) { badRequest('Invalid type.'); }
        $data = ['type' => $type,'bankid' => $this->reportBank($_GET['bank'] ?? null)];
        [$data['sdate'],$data['edate']] = $this->reportDates($_GET['sdate'] ?? null,$_GET['edate'] ?? null,'sdate','edate');
        return $data;
    }

    private function listResponse($items)
    {
        $results = [];
        foreach($items as $item){
            $results[] = [
                'transactionDate' => date('d-m-Y',strtotime($item->transactionDate)),
                'amount' => $item->amount,
                'reference' => $item->reference,
            ];
        }
        echo json_encode(['success' => true,'results' => $results]);
        exit;
    }

    public function unclearedreport()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $this->listResponse($this->bankreconModel->UnclearedReport($this->listData()));
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function clearedreport()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $this->listResponse($this->bankreconModel->ClearedReport($this->listData()));
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}
