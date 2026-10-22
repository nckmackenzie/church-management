<?php
/**
 * Expenses (transaction type 2) on the Ledger library (task 8.5d).
 *
 * Every account on a posting comes from the expense row, which the user chose on the form:
 *   Dr tblexpenses.accountId  /  Cr tblexpenses.PaidFromAccountId, both for the expense amount.
 * The backend never infers an account. tblexpenses.deductfrom and bankId are legacy: nothing reads them, create
 * leaves them NULL and update leaves them as they are.
 *
 * Lifecycle: draft (status 0) -> approve (posts) -> unapprove (voids, back to draft) ; delete from either state.
 * Every public action loads the expense by ID + session congregation (FOR UPDATE) inside its transaction and
 * returns ['success' => bool, 'errors' => string[]] (no errors on failure = unexpected error, logged).
 */
class Expense
{
    /** Method name of the bank row (tbltransactionmethods 2): money leaves the bank, whatever the payment method. */
    const BANK_METHOD = 'Withdrawal';
    /** 'petty cash', preselected on a new cash expense (confirmed by the user, decision 8.5d-1). */
    const DEFAULT_PAID_FROM = 98;
    /** 'cash holding account': the only account a new-style requisition may be paid from (and only requisitions may use it). */
    const CASH_HOLDING = Ledger::CASH_HOLDING;
    const TYPE_EXPENSE = 2;
    const TYPE_GROUP_APPROVAL = 12;
    const CASH_METHOD = 1;
    /** tblexpenses.narration / paymentReference lengths (the petty cash book column is 50 as well) */
    const NARRATION_LEN = 50;
    const REFERENCE_LEN = 50;

    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    // -----------------------------------------------------------------
    // Lists for the screens
    // -----------------------------------------------------------------

    public function getExpenses()
    {
        $this->db->query('SELECT * FROM vw_getexpenses_2 WHERE (congregationId=:cid)');
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    /** Own expense (deleted ones included, callers decide), or false. */
    public function getExpense($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }
        $this->db->query('SELECT * FROM tblexpenses WHERE ID = :id AND congregationId = :cid');
        $this->db->bind(':id',$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        return $this->db->single();
    }

    /** Voucher print data of an own expense, or false. */
    public function getExpenseFull($id)
    {
        if (!$this->getExpense($id)) {
            return false;
        }
        $this->db->query('SELECT * FROM vw_expensevoucher_2 WHERE (ID=:id)');
        $this->db->bind(':id',(int)$id);
        return $this->db->single();
    }

    /** Fiscal year (not deleted) covering the date, or 0. */
    private function yearIdFor($pdo,$date)
    {
        if (!date_is_valid((string)$date)) {
            return 0;
        }
        $y = Ledger::yearForDate($pdo,$date);
        return $y && (int)$y['deleted'] === 0 ? (int)$y['ID'] : 0;
    }

    /** Voucher number to SHOW on the form (the real number is allocated when the expense is saved); '' when the date has no fiscal year. */
    public function peekVoucherNo($date)
    {
        $pdo = $this->db->pdo();
        $yearId = $this->yearIdFor($pdo,$date);
        return $yearId > 0 ? Ledger::peekNextNumber($pdo,(int)$_SESSION['congId'],Ledger::SEQ_VOUCHER,$yearId) : '';
    }

    /**
     * Accounts to offer as the expense (debit) account: posting accounts of types 2, 3, 4 (expense, asset, liability),
     * no banks, valid for the congregation, active; group expenses (type 2) only forGroup = 1. $keepFor = an own
     * expense ID: its current account is also offered, even when inactive (legacy), as long as it still satisfies the
     * type rules. This one function feeds the add form, the edit form and the AJAX getaccounts; accountProblem() is
     * the server-side check with the same rules.
     */
    public function GetExpenseAccounts($type,$keepFor = 0)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $type = (int)$type;
        $sql = 'SELECT ID FROM tblaccounttypes
                WHERE deleted = 0 AND isBank = 0 AND accountTypeId IN (2,3,4) AND congregationId IN (0,?)';
        if ($type === 2) {
            $sql .= ' AND forGroup = 1';
        }
        $st = $pdo->prepare($sql);
        $st->execute([$cong]);
        $ids = Ledger::filterPostingAccounts($pdo,$cong,array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)));

        $keepFor = (int)$keepFor;
        if ($keepFor > 0) {
            $row = $this->getExpense($keepFor);
            if ($row && (int)$row->expenseType === $type && !in_array((int)$row->accountId,$ids,true)
                && $this->accountProblem($pdo,$cong,(int)$row->accountId,$type,true) === null) {
                $ids[] = (int)$row->accountId;
            }
        }
        if (!$ids) {
            return [];
        }
        $st = $pdo->prepare('SELECT ID, UCASE(accountType) AS accountType FROM tblaccounttypes WHERE ID IN ('
                            . implode(',',array_fill(0,count($ids),'?')) . ') ORDER BY accountType');
        $st->execute($ids);
        return $st->fetchAll(PDO::FETCH_OBJ);
    }

    /** Why $accountId cannot be the expense account of a $type expense, or null. */
    private function accountProblem($pdo,$cong,$accountId,$type,$allowInactive)
    {
        if ($accountId <= 0) {
            return 'Select the expense account';
        }
        if ($why = Ledger::postingProblem($pdo,$accountId,$cong,$allowInactive)) {
            return "Expense account: $why";
        }
        $st = $pdo->prepare('SELECT accountType, accountTypeId, isBank, forGroup FROM tblaccounttypes WHERE ID = ?');
        $st->execute([$accountId]);
        $a = $st->fetch(PDO::FETCH_OBJ);
        if (!in_array((int)$a->accountTypeId,[2,3,4],true)) {
            return "Expense account: '{$a->accountType}' is not an expense, asset or liability account";
        }
        if ((int)$a->isBank === 1) {
            return "Expense account: '{$a->accountType}' is a bank account";
        }
        if ($type === 2 && (int)$a->forGroup !== 1) {
            return "Expense account: '{$a->accountType}' is not available for group expenses";
        }
        return null;
    }

    /** Ledger::PAY_CASH for payment method 1 (cash), Ledger::PAY_BANK for the rest (M-Pesa, cheque, bank). */
    private function payKind($method)
    {
        return (int)$method === self::CASH_METHOD ? Ledger::PAY_CASH : Ledger::PAY_BANK;
    }

    /** "Paid from" accounts for a payment method (the Ledger lists): [ (object)['ID','Account'], ... ]. */
    public function GetPaidFromAccounts($method)
    {
        $pdo = $this->db->pdo();
        $kind = $this->payKind($method);
        $list = Ledger::listPaymentAccounts($pdo,(int)$_SESSION['congId'],$kind);
        $accountNo = [];
        if ($kind === Ledger::PAY_BANK && $list) {
            $ids = array_column($list,'ID');
            $st = $pdo->prepare('SELECT ID, accountNo FROM tblaccounttypes WHERE ID IN (' . implode(',',array_fill(0,count($ids),'?')) . ')');
            $st->execute($ids);
            foreach ($st->fetchAll(PDO::FETCH_OBJ) as $r) {
                $accountNo[(int)$r->ID] = $r->accountNo;
            }
        }
        $out = [];
        foreach ($list as $a) {
            $label = strtoupper($a['accountType']);
            if ($kind === Ledger::PAY_BANK && trim((string)($accountNo[(int)$a['ID']] ?? '')) !== '') {
                $label .= '-' . $accountNo[(int)$a['ID']];
            }
            $out[] = (object)['ID' => (int)$a['ID'],'Account' => $label];
        }
        return $out;
    }

    /** The paid-from account preselected on a new expense: DEFAULT_PAID_FROM when the list has it, else none. */
    public function DefaultPaidFrom($list)
    {
        foreach ($list as $a) {
            if ((int)$a->ID === self::DEFAULT_PAID_FROM) {
                return self::DEFAULT_PAID_FROM;
            }
        }
        return '';
    }

    /** Groups (type 2) or districts (type 3) of this congregation; none for a church expense. */
    public function GetCostCentres($type)
    {
        return (int)$type === 1 ? [] : $this->GetGroupOrDistrict((int)$type);
    }

    public function GetGroupOrDistrict($category)
    {
        if ($category === 2) {
            $this->db->query('SELECT ID,ucase(groupName) as cost_center
                              FROM tblgroups WHERE (active=1) AND (deleted=0)
                                   AND (congregationId=:cid)
                              ORDER BY cost_center');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }else{
            $this->db->query('SELECT ID,ucase(districtName) as cost_center
                              FROM tbldistricts WHERE (deleted=0)
                               AND (congregationId=:cid)
                          ORDER BY cost_center');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
    }

    // -----------------------------------------------------------------
    // Requisitions
    // -----------------------------------------------------------------

    /**
     * 'new' when the requisition's single active group-fund approval entry (type 12) has a debit line on the cash
     * holding account (the money was moved there), 'old' when it has one such entry without that line, null when
     * there is no entry or more than one (the expense cannot be classified, so it is refused).
     */
    public function requisitionStyle($pdo,$cong,$reqId)
    {
        $st = $pdo->prepare('SELECT e.ID, SUM(l.accountId = ? AND l.dc = ?) AS toCashHolding
                             FROM journal_entries e
                             LEFT JOIN journal_lines l ON l.journalEntryId = e.ID
                             WHERE e.congregationId = ? AND e.transactionTypeId = ? AND e.transactionId = ? AND e.deleted = 0
                             GROUP BY e.ID');
        $st->execute([self::CASH_HOLDING,Ledger::DEBIT,$cong,self::TYPE_GROUP_APPROVAL,(int)$reqId]);
        $rows = $st->fetchAll(PDO::FETCH_OBJ);
        if (count($rows) !== 1) {
            return null;
        }
        return (int)$rows[0]->toCashHolding > 0 ? 'new' : 'old';
    }

    /**
     * Requisitions an expense of $type / cost centre can account for: approved, own congregation, matching centre,
     * balance left (approved amount - approved non-deleted expenses, excluding $expenseId) > 0. The expense's own
     * requisition is always listed. [ (object)['ID','Label','Style'], ... ]
     */
    public function GetRequisitionOptions($type,$centre,$expenseId = 0)
    {
        $type = (int)$type;
        $centre = (int)$centre;
        if (($type !== 2 && $type !== 3) || $centre <= 0) {
            return [];
        }
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $own = 0;
        if ($expenseId > 0 && ($e = $this->getExpense($expenseId))) {
            $own = (int)$e->requisitionId;
        }
        $st = $pdo->prepare('SELECT r.ID, r.ReqNo, r.AmountApproved,
                                    r.AmountApproved - COALESCE((SELECT SUM(x.amount) FROM tblexpenses x
                                          WHERE x.requisitionId = r.ID AND x.deleted = 0 AND x.status = 1 AND x.ID <> ?),0) AS Remaining
                             FROM tblfundrequisition r
                             WHERE r.CongregationId = ? AND r.Deleted = 0 AND r.Status = 1 AND '
                            . ($type === 2 ? 'r.GroupId' : 'r.DistrictId') . ' = ?
                             ORDER BY r.ID');
        $st->execute([(int)$expenseId,$cong,$centre]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_OBJ) as $r) {
            if ((float)$r->Remaining <= 0 && (int)$r->ID !== $own) {
                continue;
            }
            $out[] = (object)[
                'ID' => (int)$r->ID,
                'Label' => 'Req No ' . $r->ReqNo . ' - ' . number_format((float)$r->AmountApproved,2) . ' (balance ' . number_format((float)$r->Remaining,2) . ')',
                'Style' => $this->requisitionStyle($pdo,$cong,$r->ID),
            ];
        }
        return $out;
    }

    /** Old requisition dropdown of the reports (approved amount - spent incl. drafts > 0); unchanged. */
    function GetGroupBalance($group,$type)
    {
        if($type === 2){
            $sql = 'SELECT r.ID,CONCAT("Req No ",r.ReqNo," - ",FORMAT(r.AmountApproved,2)) AS Formated 
                FROM `tblfundrequisition` r 
                WHERE r.GroupId = ? AND r.Status = 1 AND r.Deleted = 0
                HAVING getrequisitionbalance(r.ID) > 0;';
        }else{
            $sql = 'SELECT r.ID,CONCAT("Req No ",r.ReqNo," - ",FORMAT(r.AmountApproved,2)) AS Formated 
                FROM `tblfundrequisition` r 
                WHERE r.DistrictId = ? AND r.Status = 1 AND r.Deleted = 0
                HAVING getrequisitionbalance(r.ID) > 0;';
        }
        
        return loadresultset($this->db->dbh,$sql,[$group]);
    }

    // -----------------------------------------------------------------
    // Budget warning (screen only)
    // -----------------------------------------------------------------

    public function CheckOverSpent($data)
    {
        $yearid = getdbvalue($this->db->dbh,'SELECT getyearidbydate(?)',[$data['edate']]);
        $budgetedexists = 0;
        $budgetedamount = 0;
        $expensedamount = 0;
        if($data['type'] === 1) :
            $sql = 'SELECT COUNT(*) FROM tblchurchbudget_header WHERE (yearId = ?) AND (congregationId=?)';
            $budgetedexists = getdbvalue($this->db->dbh,$sql,[$yearid,$_SESSION['congId']]);
        elseif($data['type'] === 2) :
            $sql = 'SELECT COUNT(*) FROM tblgroupbudget_header WHERE (fiscalYearId = ?) AND (groupId=?)';
            $budgetedexists = getdbvalue($this->db->dbh,$sql,[$yearid,$data['gid']]);
        endif;

        if((int)$budgetedexists === 0) return false; //no bduget found
        
        if($data['type'] === 1) :
            $sql = 'SELECT getbudgetedamount_lcc(?,?,?) As amount';
            $budgetedamount = getdbvalue($this->db->dbh,$sql,[$data['aid'],$yearid,$_SESSION['congId']]);

            $sql2 = 'SELECT getexpensedamount(?,?,?) AS amount';
            $expensedamount = getdbvalue($this->db->dbh,$sql2,[$data['aid'],$data['edate'],$_SESSION['congId']]);
        elseif($data['type'] === 2) :
            $sql = 'SELECT getbudgetedamount(?,?,?) As amount';
            $budgetedamount = getdbvalue($this->db->dbh,$sql,[$data['aid'],$yearid,$data['gid']]);

            $sql2 = 'SELECT getgroupexpensedamount(?,?,?,?) AS amount';
            $expensedamount = getdbvalue($this->db->dbh,$sql2,[$data['aid'],$data['edate'],$data['gid'],$_SESSION['congId']]);
        endif;

        if(floatval($expensedamount) > floatval($budgetedamount)) return false;

        return true;
        
    }

    // -----------------------------------------------------------------
    // Rules shared by every action
    // -----------------------------------------------------------------

    /**
     * Server-side role rule, mirroring the buttons of expenses/index.php exactly:
     *   draft (status 0):    approve / edit / delete  -> userType < 3 or > 4
     *   approved (status 1): edit / delete / unapprove -> userType < 3
     * (checkrights('expenses') runs in the controller constructor.) Returns a message or null.
     */
    public static function roleProblem($action,$status)
    {
        if (!isset($_SESSION['userType'])) {
            return 'You do not have permission to ' . $action . ' this expense';
        }
        $t = (int)$_SESSION['userType'];
        if ((int)$status === 0) {
            $ok = in_array($action,['approve','edit','delete'],true) && ($t < 3 || $t > 4);
        } else {
            $ok = in_array($action,['edit','delete','unapprove'],true) && $t < 3;
        }
        return $ok ? null : 'You do not have permission to ' . $action . ' ' . ((int)$status === 0 ? 'an unapproved' : 'an approved') . ' expense';
    }

    /**
     * Checks the values of an expense (from the form, or from the stored row when approving) and returns the clean
     * values; throws LedgerException with every problem. Run inside the caller's transaction (the requisition row is
     * locked so two saves cannot overspend it).
     *
     * @param object|null $current the stored expense when editing / approving (own ID excluded from the requisition balance)
     * @param int         $keepAccount an inactive/legacy expense account that may stay (the account the row already uses)
     */
    private function check($pdo,$cong,$in,$current = null,$keepAccount = 0)
    {
        $errors = [];

        $date = (string)($in['date'] ?? '');
        $fid = 0;
        if (!date_is_valid($date)) {
            $errors[] = 'Enter a valid date';
        } else {
            if ($p = Ledger::dateProblem($pdo,$date)) {
                $errors[] = $p;
            }
            if ($current && ($p = Ledger::closedProblem($pdo,(string)$current->expenseDate))) {
                $errors[] = $p === Ledger::dateProblem($pdo,$date) ? $p : $p . ' (the expense is dated ' . $current->expenseDate . ')';
            }
            $fid = $this->yearIdFor($pdo,$date);
        }

        $amount = trim((string)($in['amount'] ?? ''));
        if ($err = Ledger::amountError($amount)) {
            $errors[] = $err;
        }

        $reference = trim((string)($in['reference'] ?? ''));
        if ($reference === '') {
            $errors[] = 'Enter payment reference';
        } elseif (mb_strlen($reference) > self::REFERENCE_LEN) {
            $errors[] = 'Payment reference cannot be longer than ' . self::REFERENCE_LEN . ' characters';
        }
        $description = trim((string)($in['description'] ?? ''));
        if ($description === '') {
            $errors[] = 'Enter brief description on expense';
        } elseif (mb_strlen($description) > self::NARRATION_LEN) {
            $errors[] = 'Description cannot be longer than ' . self::NARRATION_LEN . ' characters';
        }

        $type = (int)($in['expensetype'] ?? 0);
        $group = null;
        $district = null;
        $centre = (int)($in['costcentre'] ?? 0);
        if (!in_array($type,[1,2,3],true)) {
            $errors[] = 'Select a valid expense type';
        } elseif ($type === 2 || $type === 3) {
            $table = $type === 2 ? 'tblgroups' : 'tbldistricts';
            $st = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE ID = ? AND congregationId = ? AND deleted = 0");
            $st->execute([$centre,$cong]);
            if ($centre <= 0 || (int)$st->fetchColumn() === 0) {
                $errors[] = 'Select the ' . ($type === 2 ? 'group' : 'district') . ' (cost centre)';
            } elseif ($type === 2) {
                $group = $centre;
            } else {
                $district = $centre;
            }
        }

        $account = (int)($in['account'] ?? 0);
        if (in_array($type,[1,2,3],true)
            && ($why = $this->accountProblem($pdo,$cong,$account,$type,$keepAccount > 0 && $account === (int)$keepAccount))) {
            $errors[] = $why;
        }

        $method = (int)($in['paymethod'] ?? 0);
        $st = $pdo->prepare('SELECT COUNT(*) FROM tblpaymentmethods WHERE ID = ?');
        $st->execute([$method]);
        $methodOk = $method > 0 && (int)$st->fetchColumn() > 0;
        if (!$methodOk) {
            $errors[] = 'Select a valid payment method';
        }

        $paidFrom = (int)($in['paidfrom'] ?? 0);
        if ($paidFrom <= 0) {
            $errors[] = 'Select the account the expense is paid from';
        } elseif ($methodOk) {
            try {
                Ledger::assertPaymentAccount($pdo,$cong,$paidFrom,$this->payKind($method));
            } catch (LedgerException $e) {
                $errors = array_merge($errors,$e->getErrors());
            }
        }
        if ($paidFrom > 0 && $paidFrom === $account) {
            $errors[] = 'The expense account and the paid-from account cannot be the same';
        }

        $reqId = (int)($in['reqid'] ?? 0);
        if ($reqId > 0) {
            $errors = array_merge($errors,$this->requisitionProblems($pdo,$cong,$reqId,$type,$centre,$method,$paidFrom,$amount,$current));
        } elseif ($paidFrom === self::CASH_HOLDING) {
            $errors[] = 'The cash holding account can only pay an expense that accounts for an approved requisition: select the requisition';
        }

        if ($errors) {
            throw new LedgerException(array_values(array_unique($errors)));
        }

        return [
            'date' => $date,
            'fid' => $fid,
            'type' => $type,
            'group' => $group,
            'district' => $district,
            'account' => $account,
            'paymethod' => $method,
            'paidfrom' => $paidFrom,
            'reqid' => $reqId > 0 ? $reqId : null,
            'amount' => number_format((float)$amount,2,'.',''),
            'reference' => strtolower($reference),
            'description' => strtolower($description),
        ];
    }

    /** Problems of the requisition an expense accounts for (decision 8.5d-4). */
    private function requisitionProblems($pdo,$cong,$reqId,$type,$centre,$method,$paidFrom,$amount,$current)
    {
        $st = $pdo->prepare('SELECT * FROM tblfundrequisition WHERE ID = ? FOR UPDATE');
        $st->execute([$reqId]);
        $r = $st->fetch(PDO::FETCH_OBJ);
        if (!$r || (int)$r->CongregationId !== $cong || (int)$r->Deleted === 1) {
            return ['Requisition not found for this congregation'];
        }
        $no = 'Requisition No ' . $r->ReqNo;
        if ((int)$r->Status !== 1) {
            return ["$no is not approved"];
        }
        if ($type !== 2 && $type !== 3) {
            return ['Only group and district expenses can account for a requisition'];
        }
        $errors = [];
        if ($type === 2 ? ((int)$r->GroupId !== $centre) : ((int)$r->DistrictId !== $centre)) {
            $errors[] = "$no belongs to another " . ($type === 2 ? 'group' : 'district');
        }
        if ($method !== self::CASH_METHOD) {
            $errors[] = 'An expense that accounts for a requisition is paid in cash (from the cash holding account or a cash account)';
        }
        $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM tblexpenses
                             WHERE requisitionId = ? AND deleted = 0 AND status = 1 AND ID <> ?');
        $st->execute([$reqId,$current ? (int)$current->ID : 0]);
        $left = round((float)$r->AmountApproved - (float)$st->fetchColumn(),2);
        if (is_numeric($amount) && round((float)$amount * 100) > round($left * 100)) {
            $errors[] = "The amount exceeds the balance of $no (" . number_format($left,2) . ')';
        }
        $style = $this->requisitionStyle($pdo,$cong,$reqId);
        if ($style === null) {
            $errors[] = "$no has no single approval entry in the ledger, so it cannot be classified";
        } elseif ($style === 'new' && $paidFrom !== self::CASH_HOLDING) {
            $errors[] = "$no was funded into the cash holding account: pay it from the cash holding account";
        }
        return $errors;
    }

    /** The stored row as form values, so approve() runs the same checks as a save. */
    private function valuesOf($e)
    {
        return [
            'date' => $e->expenseDate,
            'expensetype' => $e->expenseType,
            'costcentre' => (int)$e->expenseType === 2 ? $e->groupId : ((int)$e->expenseType === 3 ? $e->districtId : 0),
            'account' => $e->accountId,
            'paymethod' => $e->paymethodId,
            'paidfrom' => $e->PaidFromAccountId,
            'reqid' => $e->requisitionId,
            'amount' => $e->amount,
            'reference' => $e->paymentReference,
            'description' => $e->narration,
        ];
    }

    private function fail($pdo,$e)
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e instanceof LedgerException) {
            return ['success' => false,'errors' => $e->getErrors()];
        }
        error_log($e->getMessage(),0);
        return ['success' => false,'errors' => []];
    }

    private function refuse($pdo,$message)
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false,'errors' => [$message]];
    }

    /** Own expense, row locked, or null (the caller turns that into a message). */
    private function lockExpense($pdo,$cong,$id)
    {
        if ((int)$id <= 0) {
            return null;
        }
        $st = $pdo->prepare('SELECT * FROM tblexpenses WHERE ID = ? AND congregationId = ? FOR UPDATE');
        $st->execute([(int)$id,$cong]);
        $e = $st->fetch(PDO::FETCH_OBJ);
        return $e ?: null;
    }

    // -----------------------------------------------------------------
    // Posting (one place builds the lines)
    // -----------------------------------------------------------------

    /**
     * Dr expense account / Cr paid-from account, then the bank rows (a credit of method Withdrawal, kept cleared when
     * unchanged; none when the paid-from account is not a bank) and the petty cash book row.
     */
    private function post($pdo,$cong,$id,$v)
    {
        Ledger::post($pdo,[
            'congregationId' => $cong,
            'entryDate' => $v['date'],
            'transactionTypeId' => self::TYPE_EXPENSE,
            'transactionId' => (int)$id,
            'reference' => $v['reference'],
            'description' => $v['description']
        ],[
            Ledger::debit($v['account'],$v['amount'],$v['description']),
            Ledger::credit($v['paidfrom'],$v['amount'],$v['description']),
        ]);
        Ledger::syncBankPostings($pdo,$cong,self::TYPE_EXPENSE,(int)$id,self::BANK_METHOD,$v['reference']);
        $this->syncPettyCash($pdo,$cong,$id,$v);
    }

    /**
     * Keeps the petty cash book row of an approved expense in step: one active row (same columns as the 8.5c
     * backfill) while it is paid from a petty cash account, updated in place; soft-deleted when it no longer is.
     * Rows are never hard-deleted.
     */
    private function syncPettyCash($pdo,$cong,$id,$v)
    {
        $st = $pdo->prepare('SELECT ID FROM tblpettycash WHERE ExpenseId = ? AND CongregationId = ? AND Deleted = 0 ORDER BY ID FOR UPDATE');
        $st->execute([(int)$id,$cong]);
        $active = array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));

        if (!Ledger::isPettyCashAccount($v['paidfrom'])) {
            foreach ($active as $rid) {
                $pdo->prepare('UPDATE tblpettycash SET Deleted = 1 WHERE ID = ?')->execute([$rid]);
            }
            return;
        }

        $keep = array_shift($active);
        foreach ($active as $rid) {
            $pdo->prepare('UPDATE tblpettycash SET Deleted = 1 WHERE ID = ?')->execute([$rid]);
        }
        $vals = [
            ':tdate' => $v['date'],
            ':credit' => $v['amount'],
            ':ref' => mb_substr($v['reference'],0,self::REFERENCE_LEN),
            ':narr' => mb_substr($v['description'],0,150),
            ':petty' => (int)$v['paidfrom'],
        ];
        if ($keep !== null) {
            $pdo->prepare('UPDATE tblpettycash SET TransactionDate=:tdate, Credit=:credit, Reference=:ref, Narration=:narr, PettyCashAccountId=:petty
                           WHERE ID = :id')->execute($vals + [':id' => $keep]);
        } else {
            $pdo->prepare('INSERT INTO tblpettycash (TransactionDate,Credit,IsReceipt,Reference,Narration,ExpenseId,CongregationId,PettyCashAccountId)
                           VALUES (:tdate,:credit,0,:ref,:narr,:eid,:cid,:petty)')->execute($vals + [':eid' => (int)$id,':cid' => $cong]);
        }
    }

    // -----------------------------------------------------------------
    // Actions
    // -----------------------------------------------------------------

    /**
     * New draft. $in: date, expensetype, account, costcentre, paymethod, paidfrom, reqid, amount, reference,
     * description, hasattachment, filename. deductfrom and bankId are legacy: left NULL (both columns are nullable).
     * Returns ['success','errors','id','voucher'].
     */
    public function create($in)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $v = $this->check($pdo,$cong,$in);
            $voucher = Ledger::nextNumber($pdo,$cong,Ledger::SEQ_VOUCHER,$v['fid']);

            $this->db->query('INSERT INTO tblexpenses (fiscalYearId,voucherNo,expenseType,expenseDate,accountId,groupId,districtId,
                                          paymethodId,PaidFromAccountId,amount,narration,paymentReference,`status`,hasAttachment,`fileName`,
                                          requisitionId,postedBy,postedDate,congregationId)
                              VALUES(:fid,:vno,:etype,:edate,:aid,:gid,:did,:pid,:paidfrom,:amount,:narr,:ref,0,:hasattach,:fname,
                                     :reqid,:post,:pdate,:cid)');
            $this->db->bind(':fid',$v['fid']);
            $this->db->bind(':vno',$voucher);
            $this->db->bind(':etype',$v['type']);
            $this->db->bind(':edate',$v['date']);
            $this->db->bind(':aid',$v['account']);
            $this->db->bind(':gid',$v['group']);
            $this->db->bind(':did',$v['district']);
            $this->db->bind(':pid',$v['paymethod']);
            $this->db->bind(':paidfrom',$v['paidfrom']);
            $this->db->bind(':amount',$v['amount']);
            $this->db->bind(':narr',$v['description']);
            $this->db->bind(':ref',$v['reference']);
            $this->db->bind(':hasattach',!empty($in['hasattachment']) ? 1 : 0);
            $this->db->bind(':fname',!empty($in['hasattachment']) ? ($in['filename'] ?? null) : null);
            $this->db->bind(':reqid',$v['reqid']);
            $this->db->bind(':post',(int)$_SESSION['userId']);
            $this->db->bind(':pdate',date('Y-m-d'));
            $this->db->bind(':cid',$cong);
            $this->db->execute();
            $id = (int)$pdo->lastInsertId();

            saveLog($pdo,'Created expense ' . $id . ' for ' . $v['date'] . ' voucher no ' . $voucher);
            $pdo->commit();
            return ['success' => true,'errors' => [],'id' => $id,'voucher' => $voucher];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }

    /**
     * The voucher number after a save: kept while the expense stays in its fiscal year; when the date moves it into
     * another year it gets the next number of the NEW year's sequence and the old number is retired (never reused).
     * Returns [voucherNo, message or null].
     */
    private function voucherAfterSave($pdo,$cong,$e,$newYearId)
    {
        if ((int)$e->fiscalYearId === (int)$newYearId) {
            return [(int)$e->voucherNo,null];
        }
        $no = Ledger::nextNumber($pdo,$cong,Ledger::SEQ_VOUCHER,(int)$newYearId);
        $year = getdbvalue($pdo,'SELECT yearName FROM tblfiscalyears WHERE ID=?',[(int)$newYearId]);
        return [$no,'Voucher renumbered from ' . $e->voucherNo . ' to ' . $no . ' because the date moved to ' . $year];
    }

    /** Draft -> approved: checks the stored row like a save, posts the entry, bank rows and petty cash row. */
    public function approve($id)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $e = $this->lockExpense($pdo,$cong,$id);
            if ($e === null) {
                return $this->refuse($pdo,'Expense not found for this congregation');
            }
            if ((int)$e->deleted === 1) {
                return $this->refuse($pdo,'This expense has been deleted');
            }
            if ((int)$e->status === 1) {
                return $this->refuse($pdo,'This expense is already approved');
            }
            if ($p = self::roleProblem('approve',0)) {
                return $this->refuse($pdo,$p);
            }
            $v = $this->check($pdo,$cong,$this->valuesOf($e),$e);

            [$voucher,$note] = $this->voucherAfterSave($pdo,$cong,$e,$v['fid']);
            $pdo->prepare('UPDATE tblexpenses SET `status` = 1, fiscalYearId = ?, voucherNo = ? WHERE ID = ?')->execute([$v['fid'],$voucher,(int)$e->ID]);
            $this->post($pdo,$cong,(int)$e->ID,$v);

            saveLog($pdo,'Approved expense ' . (int)$e->ID . ' for ' . $v['date'] . ' voucher no ' . $voucher . ($note ? ' (was ' . $e->voucherNo . ')' : ''));
            $pdo->commit();
            return ['success' => true,'errors' => [],'renumbered' => $note];
        } catch (\Exception $ex) {
            return $this->fail($pdo,$ex);
        }
    }

    /**
     * Edit. Draft: header only. Approved: the entry is replaced in place (bank rows and the petty cash row kept in step).
     * The voucher number is kept unless the date moves the expense into another fiscal year (then: next number of the new year);
     * deductfrom and bankId are left untouched.
     */
    public function update($id,$in)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $e = $this->lockExpense($pdo,$cong,$id);
            if ($e === null) {
                return $this->refuse($pdo,'Expense not found for this congregation');
            }
            if ((int)$e->deleted === 1) {
                return $this->refuse($pdo,'A deleted expense cannot be edited');
            }
            if ($p = self::roleProblem('edit',(int)$e->status)) {
                return $this->refuse($pdo,$p);
            }
            $v = $this->check($pdo,$cong,$in,$e,(int)$e->accountId);

            [$voucher,$note] = $this->voucherAfterSave($pdo,$cong,$e,$v['fid']);
            $this->db->query('UPDATE tblexpenses SET fiscalYearId=:fid,voucherNo=:vno,expenseType=:etype,expenseDate=:edate,accountId=:aid,groupId=:gid,
                                     districtId=:did,paymethodId=:pid,PaidFromAccountId=:paidfrom,amount=:amount,narration=:narr,
                                     paymentReference=:ref,requisitionId=:reqid
                              WHERE ID=:id AND congregationId=:cid');
            $this->db->bind(':fid',$v['fid']);
            $this->db->bind(':vno',$voucher);
            $this->db->bind(':etype',$v['type']);
            $this->db->bind(':edate',$v['date']);
            $this->db->bind(':aid',$v['account']);
            $this->db->bind(':gid',$v['group']);
            $this->db->bind(':did',$v['district']);
            $this->db->bind(':pid',$v['paymethod']);
            $this->db->bind(':paidfrom',$v['paidfrom']);
            $this->db->bind(':amount',$v['amount']);
            $this->db->bind(':narr',$v['description']);
            $this->db->bind(':ref',$v['reference']);
            $this->db->bind(':reqid',$v['reqid']);
            $this->db->bind(':id',(int)$e->ID);
            $this->db->bind(':cid',$cong);
            $this->db->execute();

            if ((int)$e->status === 1) {
                // a cleared (reconciled) bank row may stay, but not with another bank or amount
                if ($p = Ledger::clearedBankProblem($pdo,$cong,self::TYPE_EXPENSE,(int)$e->ID,['bankId' => $v['paidfrom'],'amount' => $v['amount']])) {
                    throw new LedgerException([$p]);
                }
                $this->post($pdo,$cong,(int)$e->ID,$v);
            }

            saveLog($pdo,'Updated expense ' . (int)$e->ID . ' for ' . $v['date'] . ' voucher no ' . $voucher . ($note ? ' (was ' . $e->voucherNo . ')' : ''));
            $pdo->commit();
            return ['success' => true,'errors' => [],'renumbered' => $note];
        } catch (\Exception $ex) {
            return $this->fail($pdo,$ex);
        }
    }

    /** Approved -> draft: voids the entry and bank rows, soft-deletes the petty cash row, in one transaction. */
    public function unapprove($id)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $e = $this->lockExpense($pdo,$cong,$id);
            if ($e === null) {
                return $this->refuse($pdo,'Expense not found for this congregation');
            }
            if ((int)$e->deleted === 1) {
                return $this->refuse($pdo,'This expense has been deleted');
            }
            if ((int)$e->status !== 1) {
                return $this->refuse($pdo,'This expense is not approved');
            }
            if ($p = self::roleProblem('unapprove',1)) {
                return $this->refuse($pdo,$p);
            }
            $this->voidPosting($pdo,$cong,$e);
            $pdo->prepare('UPDATE tblexpenses SET `status` = 0 WHERE ID = ?')->execute([(int)$e->ID]);

            saveLog($pdo,'Unapproved expense ' . (int)$e->ID . ' for ' . $e->expenseDate . ' voucher no ' . $e->voucherNo);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $ex) {
            return $this->fail($pdo,$ex);
        }
    }

    /** Draft: deleted = 1. Approved: also voids the entry and bank rows and soft-deletes the petty cash row. */
    public function delete($id)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $e = $this->lockExpense($pdo,$cong,$id);
            if ($e === null) {
                return $this->refuse($pdo,'Expense not found for this congregation');
            }
            if ((int)$e->deleted === 1) {
                return $this->refuse($pdo,'This expense has already been deleted');
            }
            if ($p = self::roleProblem('delete',(int)$e->status)) {
                return $this->refuse($pdo,$p);
            }
            if ((int)$e->status === 1) {
                $this->voidPosting($pdo,$cong,$e);
            } elseif ($p = Ledger::closedProblem($pdo,(string)$e->expenseDate)) {
                throw new LedgerException([$p . ' (the expense is dated ' . $e->expenseDate . ')']);
            }
            $pdo->prepare('UPDATE tblexpenses SET deleted = 1 WHERE ID = ?')->execute([(int)$e->ID]);

            saveLog($pdo,'Deleted expense ' . (int)$e->ID . ' for ' . $e->expenseDate . ' voucher no ' . $e->voucherNo);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $ex) {
            return $this->fail($pdo,$ex);
        }
    }

    /** Void the entry (refused in a closed year, even when there is no entry), the bank rows and the petty cash rows (soft). */
    private function voidPosting($pdo,$cong,$e)
    {
        if ($p = Ledger::closedProblem($pdo,(string)$e->expenseDate)) {
            throw new LedgerException([$p . ' (the expense is dated ' . $e->expenseDate . ')']);
        }
        if ($p = Ledger::clearedBankProblem($pdo,$cong,self::TYPE_EXPENSE,(int)$e->ID)) {
            throw new LedgerException([$p]);
        }
        Ledger::void($pdo,$cong,self::TYPE_EXPENSE,(int)$e->ID);
        Ledger::voidBankPostings($pdo,$cong,self::TYPE_EXPENSE,(int)$e->ID);
        $pdo->prepare('UPDATE tblpettycash SET Deleted = 1 WHERE ExpenseId = ? AND CongregationId = ? AND Deleted = 0')->execute([(int)$e->ID,$cong]);
    }

    // -----------------------------------------------------------------
    // View posting (voucher print and the eye icon)
    // -----------------------------------------------------------------

    /**
     * The journal of an own, non-deleted expense for display, or false. Approved: the ledger entry
     * (Ledger::getEntry). Draft: a two-line preview (Dr expense account / Cr paid-from account, names from the IDs)
     * whose rows carry notPosted = true. Rows: transactionDate, account, debit, credit, narration, notPosted.
     */
    public function getJournalDetails($id)
    {
        $e = $this->getExpense($id);
        if (!$e || (int)$e->deleted === 1) {
            return false;
        }
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];

        if ((int)$e->status === 1) {
            $entry = Ledger::getEntry($pdo,$cong,self::TYPE_EXPENSE,(int)$e->ID);
            if ($entry === null) {
                return [];
            }
            $rows = [];
            foreach ($entry['lines'] as $l) {
                $rows[] = (object)[
                    'ID' => (int)$l['lineNumber'],
                    'transactionDate' => $entry['entryDate'],
                    'account' => $l['accountName'],
                    'debit' => $l['dc'] === Ledger::DEBIT ? $l['amount'] : 0,
                    'credit' => $l['dc'] === Ledger::CREDIT ? $l['amount'] : 0,
                    'narration' => $l['memo'] ?? $entry['description'],
                    'notPosted' => false,
                ];
            }
            return $rows;
        }

        $name = function ($accountId) use ($pdo) {
            if (!$accountId) {
                return '(paid-from account not chosen)';
            }
            $st = $pdo->prepare('SELECT accountType FROM tblaccounttypes WHERE ID = ?');
            $st->execute([(int)$accountId]);
            $n = $st->fetchColumn();
            return $n === false ? '(unknown account)' : $n;
        };
        return [
            (object)['ID' => 1,'transactionDate' => $e->expenseDate,'account' => $name($e->accountId),'debit' => $e->amount,'credit' => 0,
                     'narration' => $e->narration,'notPosted' => true],
            (object)['ID' => 2,'transactionDate' => $e->expenseDate,'account' => $name($e->PaidFromAccountId),'debit' => 0,'credit' => $e->amount,
                     'narration' => $e->narration,'notPosted' => true],
        ];
    }
}
