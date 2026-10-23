<?php
class Expenses extends Controller{
    private $authmodel;
    private $expenseModel;
    private $reusemodel;

    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'expenses');
        $this->expenseModel = $this->model('Expense');
        $this->reusemodel = $this->model('Reusables');
    }

    public function index()
    {
        $expenses = $this->expenseModel->getExpenses();
        $data = ['expenses' => $expenses];
        $this->view('expenses/index',$data);
    }

    /** Positive integer from a request value, else 0. */
    private function intId($value)
    {
        $value = trim((string)$value);
        return ctype_digit($value) && (int)$value > 0 ? (int)$value : 0;
    }

    /** Flash text of a failed model result: its messages (escaped), else a generic one. */
    private function failText($result,$fallback)
    {
        return !empty($result['errors']) ? implode('<br>',array_map('htmlspecialchars',$result['errors'])) : $fallback;
    }

    /** The form values posted by the add / edit form. */
    private function posted()
    {
        $p = $_POST;
        $get = function ($key) use ($p) {
            return trim((string)($p[$key] ?? ''));
        };
        return [
            'date' => $get('date'),
            'expensetype' => $get('expensetype'),
            'account' => $get('account'),
            'costcentre' => $get('costcentre'),
            'paymethod' => $get('paymethod'),
            'paidfrom' => $get('paidfrom'),
            'reqid' => $get('reqid'),
            'amount' => $get('amount'),
            'reference' => $get('reference'),
            'description' => $get('description'),
        ];
    }

    /** Fills the dropdown lists of the add / edit form for the values in $data. */
    private function withLists($data,$expenseId = 0)
    {
        $type = (int)$data['expensetype'];
        $type = in_array($type,[1,2,3],true) ? $type : 1;
        $data['accounts'] = $this->expenseModel->GetExpenseAccounts($type,$expenseId);
        $data['costcentres'] = $this->expenseModel->GetCostCentres($type);
        $data['paymethods'] = $this->reusemodel->PaymentMethods();
        $data['paidfroms'] = $this->expenseModel->GetPaidFromAccounts((int)$data['paymethod'] ?: Expense::CASH_METHOD);
        $data['requisitions'] = $this->expenseModel->GetRequisitionOptions($type,$data['costcentre'],$expenseId);
        return $data;
    }

    public function voucherno()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $date = isset($_GET['txndate']) && !empty(trim($_GET['txndate'])) ? date('Y-m-d',strtotime($_GET['txndate'])) : date('Y-m-d', strtotime($_SESSION['processdate']));
            echo json_encode($this->expenseModel->peekVoucherNo($date));
        }
    }

    public function add()
    {
        $date = date('Y-m-d',strtotime($_SESSION['processdate']));
        $data = $this->withLists([
            'voucherno' => $this->expenseModel->peekVoucherNo($date),
            'date' => $date,
            'expensetype' => 1,
            'account' => '',
            'costcentre' => '',
            'paymethod' => Expense::CASH_METHOD,
            'paidfrom' => '',
            'reqid' => '',
            'amount' => '',
            'reference' => '',
            'description' => '',
            'errors' => []
        ]);
        $data['paidfrom'] = $this->expenseModel->DefaultPaidFrom($data['paidfroms']);
        $this->view('expenses/add',$data);
        exit;
    }

    public function checkoverspent()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : '',
                'aid' =>   isset($_GET['aid']) && !empty(trim($_GET['aid'])) ? (int)trim($_GET['aid']) : '',
                'type' =>   isset($_GET['type']) && !empty(trim($_GET['type'])) ? (int)trim($_GET['type']) : '',
                'gid' =>   isset($_GET['gid'])  ? (int)trim($_GET['gid']) : '',
            ];

            if(empty($data['edate']) || empty($data['aid']) || empty($data['type'])) exit;
            if($data['type'] === 2 && empty($data['gid'])) exit;

            echo json_encode($this->expenseModel->CheckOverSpent($data));
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function getcostcentre()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
           $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           $category = (int)trim($_POST['category']);
           if ($category === 1) {
               echo '<option value="0">CHURCH</option>';
           }
           else{
                $options = '<option value="" selected disabled>Select</option>';
                $groups = $this->expenseModel->GetGroupOrDistrict($category);
                foreach ($groups as $group ) {
                    $options .='<option value="'.$group->ID.'">'.$group->cost_center.'</option>';
                }
                echo $options;
           }
        }
    }

    /** Uploaded receipt: validates it and returns [stored name, tmp name, destination, error]. */
    private function receipt()
    {
        $out = ['name' => '','tmp' => '','dest' => '','error' => ''];
        if (!isset($_FILES['file']) || (int)$_FILES['file']['size'] <= 0) {
            return $out;
        }
        $ext = strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','pdf'],true)) {
            $out['error'] = 'Invalid file type';
        } elseif ($_FILES['file']['error'] !== 0) {
            $out['error'] = 'An error occurred during file upload';
        } elseif ($_FILES['file']['size'] >= 1000000) {
            $out['error'] = 'File size is too big';
        } else {
            $out['name'] = uniqid('',true) . '.' . $ext;
            $out['tmp'] = $_FILES['file']['tmp_name'];
            $out['dest'] = getcwd() . '/img/' . $out['name'];
        }
        return $out;
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW) ?: [];
            $in = $this->posted();
            $date = !empty($in['date']) ? $in['date'] : date('Y-m-d',strtotime($_SESSION['processdate']));
            $data = $in + ['voucherno' => $this->expenseModel->peekVoucherNo($date),'errors' => []];

            $file = $this->receipt();
            if ($file['error'] !== '') {
                $data['errors'][] = $file['error'];
            } else {
                $in['hasattachment'] = $file['name'] !== '' ? 1 : 0;
                $in['filename'] = $file['name'];
                $moved = false;
                if ($file['name'] !== '') {
                    $moved = move_uploaded_file($file['tmp'],$file['dest']);
                    if (!$moved) {
                        $data['errors'][] = 'Something went wrong with the file upload';
                    }
                }
                if (!$data['errors']) {
                    $result = $this->expenseModel->create($in);
                    if ($result['success']) {
                        flash('expense_msg','Expense Added Successfully!');
                        redirect('expenses');
                        exit;
                    }
                    if ($moved) {
                        @unlink($file['dest']);
                    }
                    $data['errors'] = $result['errors'] ?: ['Something went wrong! The expense was not saved. Contact admin'];
                }
            }
            $this->view('expenses/add',$this->withLists($data));
        }
        else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    /** Requisitions the form can offer (JSON: id, label, style = new / old, account = the account a new-style requisition is paid from). */
    public function requisitionoptions()
    {
        $type = $this->intId($_GET['type'] ?? '');
        $centre = $this->intId($_GET['centre'] ?? '');
        $expense = $this->intId($_GET['expense'] ?? '');
        $data = [];
        foreach ($this->expenseModel->GetRequisitionOptions($type,$centre,$expense) as $r) {
            $data[] = ['id' => $r->ID,'label' => $r->Label,'style' => $r->Style,'account' => $r->Account];
        }
        echo json_encode($data);
    }

    /** Paid-from accounts for a payment method (JSON: accounts, default). */
    public function paidfrom()
    {
        $method = $this->intId($_GET['method'] ?? '');
        $list = $this->expenseModel->GetPaidFromAccounts($method ?: Expense::CASH_METHOD);
        $data = [];
        foreach ($list as $a) {
            $data[] = ['id' => $a->ID,'label' => $a->Account];
        }
        echo json_encode(['accounts' => $data,'default' => $method === Expense::CASH_METHOD ? $this->expenseModel->DefaultPaidFrom($list) : '']);
    }

    /** Old requisition dropdown used by the reports (approved amount - spent, drafts included, > 0). */
    public function getrequisitions(){
        $groupid = isset($_GET['group']) && !empty(trim($_GET['group'])) ? (int)trim($_GET['group']) : null;
        $type = isset($_GET['type']) && !empty(trim($_GET['type'])) ? (int)trim($_GET['type']) : 2;
        $data = array();

        $balances = $this->expenseModel->GetGroupBalance($groupid,$type);
        foreach($balances as $balance){
            array_push($data,[
                'id' => $balance->ID,
                'label' => $balance->Formated
            ]);
        }
        echo json_encode($data);
    }

    public function approve()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $id = $this->intId($_POST['id'] ?? '');
            if ($id === 0) {
                flash('expense_msg','Unable to get the selected expense','alert custom-danger');
                redirect('expenses');
                exit;
            }
            $result = $this->expenseModel->approve($id);
            if ($result['success']) {
                flash('expense_msg','Expense Approved Successfully!' . (!empty($result['renumbered']) ? ' ' . htmlspecialchars($result['renumbered']) : ''));
            } else {
                flash('expense_msg',$this->failText($result,'Cannot approve expense due to an internal error.'),'alert custom-danger');
            }
            redirect('expenses');
            exit;
        }
        redirect('users/deniedaccess');
        exit;
    }

    public function unapprove()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $id = $this->intId($_POST['id'] ?? '');
            if ($id === 0) {
                flash('expense_msg','Unable to get the selected expense','alert custom-danger');
                redirect('expenses');
                exit;
            }
            $result = $this->expenseModel->unapprove($id);
            if ($result['success']) {
                flash('expense_msg','Expense unapproved: it is a draft again and its ledger entry has been reversed.');
            } else {
                flash('expense_msg',$this->failText($result,'Cannot unapprove expense due to an internal error.'),'alert custom-danger');
            }
            redirect('expenses');
            exit;
        }
        redirect('users/deniedaccess');
        exit;
    }

    public function edit($id = '')
    {
        $id = $this->intId($id);
        $expense = $this->expenseModel->getExpense($id);
        if (!$expense || (int)$expense->deleted === 1) {
            flash('expense_msg','Expense not found or already deleted','alert custom-danger');
            redirect('expenses');
            exit;
        }
        if ($problem = Expense::roleProblem('edit',(int)$expense->status)) {
            flash('expense_msg',htmlspecialchars($problem),'alert custom-danger');
            redirect('expenses');
            exit;
        }
        $type = (int)$expense->expenseType;
        $data = $this->withLists([
            'expense' => $expense,
            'date' => $expense->expenseDate,
            'expensetype' => $type,
            'account' => $expense->accountId,
            'costcentre' => $type === 2 ? $expense->groupId : ($type === 3 ? $expense->districtId : 0),
            'paymethod' => $expense->paymethodId,
            'paidfrom' => $expense->PaidFromAccountId,
            'reqid' => $expense->requisitionId,
            'amount' => $expense->amount,
            'reference' => strtoupper((string)$expense->paymentReference),
            'description' => strtoupper((string)$expense->narration),
            'errors' => []
        ],$id);
        $this->view('expenses/edit',$data);
        exit;
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW) ?: [];
            $id = $this->intId($_POST['id'] ?? '');
            $in = $this->posted();

            $result = $this->expenseModel->update($id,$in);
            if ($result['success']) {
                flash('expense_msg','Expense Updated Successfully!' . (!empty($result['renumbered']) ? ' ' . htmlspecialchars($result['renumbered']) : ''));
                redirect('expenses');
                exit;
            }

            $expense = $this->expenseModel->getExpense($id);
            if (!$expense || (int)$expense->deleted === 1) {
                flash('expense_msg',$this->failText($result,'Expense not found or already deleted'),'alert custom-danger');
                redirect('expenses');
                exit;
            }
            $data = $in + ['expense' => $expense,'errors' => $result['errors'] ?: ['Something went wrong! The expense was not updated. Contact admin']];
            $this->view('expenses/edit',$this->withLists($data,$id));
        }
        else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $id = $this->intId($_POST['id'] ?? '');
            if ($id === 0) {
                flash('expense_msg','Unable to get the selected expense','alert custom-danger');
                redirect('expenses');
                exit;
            }
            $result = $this->expenseModel->delete($id);
            if ($result['success']) {
                flash('expense_msg','Expense Deleted Successfully!');
            } else {
                flash('expense_msg',$this->failText($result,'Cannot delete expense due to an internal error.'),'alert custom-danger');
            }
            redirect('expenses');
            exit;
        }
        else{
            redirect('expenses');
        }
    }

    public function print($id = '')
    {
        $id = $this->intId($id);
        $expense = $this->expenseModel->getExpenseFull($id);
        $journalDetails = $expense ? $this->expenseModel->getJournalDetails($id) : false;
        if (!$expense || $journalDetails === false) {
            flash('expense_msg','Expense not found or already deleted','alert custom-danger');
            redirect('expenses');
            exit;
        }
        $data = ['expense' => $expense,'journalDetails' => $journalDetails,
                 'posted' => !$journalDetails || empty($journalDetails[0]->notPosted)];
        $this->view('expenses/print',$data);
    }

    public function getaccounts()
    {
        $type = $this->intId($_GET['type'] ?? '') ?: 1;
        $expense = $this->intId($_GET['expense'] ?? '');
        $data = array();

        foreach($this->expenseModel->GetExpenseAccounts($type,$expense) as $account){
            array_push($data,[
                'id' => $account->ID,
                'label' => $account->accountType
            ]);
        }
        echo json_encode($data);
    }
}
