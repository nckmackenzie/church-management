<?php
class Contribution {
    private $db;

    /** Method name of the bank row of a receipt (tbltransactionmethods 1). */
    const BANK_METHOD = 'Deposit';
    /** tblcontributions_details.narration is varchar(50) */
    const NARRATION_LEN = 50;

    public function __construct()
    {
        $this->db =  new Database;
    }

    public function getContributions()
    {
        $this->db->query("SELECT h.ID,
                                 h.receiptNo,
                                 d.contributionDate,
                                 FORMAT(SUM(d.amount),2) As Total
                          FROM   tblcontributions_header h inner join tblcontributions_details d on h.ID = d.HeaderId
                          WHERE  (h.congregationId = :cid) AND (h.deleted = 0)
                                 AND h.fiscalYearId IN (SELECT ID FROM tblfiscalyears WHERE closed = 0 AND deleted = 0)
                          GROUP BY h.ID,h.receiptNo,d.contributionDate
                          ORDER BY d.contributionDate DESC");
        $this->db->bind(':cid',$_SESSION['congId']);
        return $this->db->resultSet();
    }

    /** "2025-2026/005" when the fiscal year prefixes its references, otherwise the plain number. */
    private function formatReceiptNo($yearId,$no)
    {
        $pdo = $this->db->pdo();
        if (converttobool(getdbvalue($pdo,'SELECT prefixReferences FROM tblfiscalyears WHERE ID=?',[(int)$yearId]))) {
            return getdbvalue($pdo,'SELECT yearName FROM tblfiscalyears WHERE ID=?',[(int)$yearId]) . '/' . format_string($no);
        }
        return (string)$no;
    }

    /** Receipt number to SHOW on a new receipt (not reserved: the number is allocated inside the save transaction). */
    public function GetReceiptNo($yearId)
    {
        if (empty($yearId)) {
            return '';
        }
        return $this->formatReceiptNo($yearId,Ledger::peekNextNumber($this->db->pdo(),(int)$_SESSION['congId'],Ledger::SEQ_RECEIPT,(int)$yearId));
    }

    /** Number to show for a receipt being edited on $date: its own number, unless the date moves it to another fiscal year. */
    public function GetReceiptNoForEdit($id,$yearId)
    {
        $row = loadresultset($this->db->pdo(),'SELECT fiscalYearId,receiptNo FROM tblcontributions_header
                                               WHERE ID=? AND congregationId=? AND Deleted=0',[(int)$id,(int)$_SESSION['congId']]);
        if ($row && (int)$row[0]->fiscalYearId === (int)$yearId) {
            return $row[0]->receiptNo;
        }
        return $this->GetReceiptNo($yearId);
    }

    public function getCategories()
    {
        $this->db->query('SELECT ID,UCASE(categoryName) AS category FROM tblcontributioncategories WHERE (ForParish=:parish)');
        $this->db->bind(':parish',(int)$_SESSION['isParish']);
        return $this->db->resultSet();
    }

    public function getBanks()
    {
       if ($_SESSION['isParish'] == 1) {
           return getBanksAll($this->db->dbh);
       }else{
           return getBanks($this->db->dbh,$_SESSION['congId']);
       }
    }

    /** Cash-type accounts for a cash receipt (same rule as assertPaymentAccount(PAY_CASH)); 34 'cash at hand' is the default. */
    public function getCashAccounts()
    {
        $list = [];
        foreach (Ledger::listPaymentAccounts($this->db->pdo(),(int)$_SESSION['congId'],Ledger::PAY_CASH) as $a) {
            $list[] = (object)['ID' => $a['ID'],'Account' => strtoupper($a['accountType'])];
        }
        return $list;
    }

    /** Income account dropdown: the old list (non-bank, active, sub-category) restricted to accounts the Ledger can post to. */
    public function getIncomeAccounts()
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $rows = loadresultset($pdo,'SELECT ID,UCASE(accountType) AS accountType FROM tblaccounttypes
                                    WHERE (deleted=0) AND (isBank=0) AND (parentId<>0) AND (active=1) AND (isSubCategory=1)
                                          AND (congregationId=0 OR congregationId=?) ORDER BY accountType',[$cong]);
        $ok = Ledger::filterPostingAccounts($pdo,$cong,array_map(function($r){ return (int)$r->ID; },$rows));
        return array_values(array_filter($rows,function($r) use ($ok){
            return in_array((int)$r->ID,$ok,true);
        }));
    }

    public function getContributor($category)
    {
        if ($category == 1 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(memberName) AS contributor
                    FROM tblmember WHERE (deleted=0) AND (memberStatus=1) AND (congregationId=:cid)
                    ORDER BY contributor');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
        elseif ($category == 2 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(groupName) AS contributor
                    FROM tblgroups WHERE (deleted=0) AND (active=1) AND (congregationId=:cid)
                    ORDER BY contributor');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
        elseif ($category == 3 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(districtName) AS contributor
                    FROM tbldistricts WHERE (deleted=0) AND (congregationId=:cid)
                    ORDER BY contributor');
            $this->db->bind(':cid',$_SESSION['congId']);
            return $this->db->resultSet();
        }
        elseif ($category == 4 && $_SESSION['isParish'] !=1 ) {
            $this->db->query('SELECT ID,UCASE(serviceName) AS contributor
                    FROM tblservices WHERE (deleted=0)
                    ORDER BY contributor');
            return $this->db->resultSet();
        }
        elseif ($_SESSION['isParish'] == 1 ) {
            $this->db->query("SELECT ID,ucase(CongregationName) AS contributor FROM tblcongregation WHERE (deleted = 0) AND (ID <> :id) ORDER BY CongregationName");
            $this->db->bind(':id',$_SESSION['congId']);
            return $this->db->resultSet();
        }
    }

    // -----------------------------------------------------------------
    // Save / update / delete (Ledger, task 8.4b)
    // -----------------------------------------------------------------

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

    /** Active receipt header of this congregation (row locked), or null. */
    private function lockHeader($pdo,$cong,$id)
    {
        $row = loadresultset($pdo,'SELECT ID,fiscalYearId,receiptNo FROM tblcontributions_header
                                   WHERE ID=? AND congregationId=? AND Deleted=0 FOR UPDATE',[(int)$id,$cong]);
        return $row ? $row[0] : null;
    }

    /**
     * Checks everything the form sends and returns the normalised receipt:
     *   yearId, reference (as typed), refLower, narration, receiving (account ID), bank (ID or null), lines[]
     * Every problem is collected and thrown together as a LedgerException (nothing is written).
     * $usedAccounts: accounts the receipt's current ledger entry already uses (an inactive one is allowed on edit).
     */
    private function prepare($pdo,$cong,$data,array $usedAccounts)
    {
        $errors = [];

        $date = (string)($data['date'] ?? '');
        $yearId = 0;
        if (!date_is_valid($date)) {
            $errors[] = 'Enter a valid date';
        } else {
            // no year at all, a closed year (deleted or not) and a deleted year each get their own message
            if ($problem = fiscalYearDateProblem($pdo,$date)) {
                $errors[] = $problem;
            } else {
                $yearId = (int)getYearId($pdo,$date);
            }
        }

        $reference = trim((string)($data['reference'] ?? ''));
        if (strlen($reference) > 50) {
            $errors[] = 'Reference cannot be longer than 50 characters';
        }

        $paymethod = (int)($data['paymethod'] ?? 0);
        if ($paymethod <= 0 || getdbvalue($pdo,'SELECT COUNT(*) FROM tblpaymentmethods WHERE ID=?',[$paymethod]) < 1) {
            $errors[] = 'Select a valid payment method';
        }

        // receiving side: cash method -> the cash account chosen on the form; mpesa, cheque, bank -> the chosen bank
        $receiving = 0;
        $bank = null;
        if ($paymethod === 1) {
            if (empty($data['cashaccount'])) {
                $errors[] = 'Select the cash account';
            } else {
                try {
                    $receiving = Ledger::assertPaymentAccount($pdo,$cong,(int)$data['cashaccount'],Ledger::PAY_CASH);
                } catch (LedgerException $e) {
                    $errors = array_merge($errors,$e->getErrors());
                }
            }
        } elseif ($paymethod > 1) {
            if ($reference === '') {
                $errors[] = 'Enter Payment Reference';
            }
            if (empty($data['bank'])) {
                $errors[] = 'Select Bank';
            } else {
                try {
                    $receiving = $bank = Ledger::assertPaymentAccount($pdo,$cong,(int)$data['bank'],Ledger::PAY_BANK);
                } catch (LedgerException $e) {
                    $errors = array_merge($errors,$e->getErrors());
                }
            }
        }

        // lines
        $ids = (array)($data['accountsid'] ?? []);
        $n = count($ids);
        if ($n === 0) {
            $errors[] = 'No contributions added';
        }
        foreach (['amounts','categoriesid','contributorsid','subaccount'] as $k) {
            if (count((array)($data[$k] ?? [])) !== $n) {
                $errors[] = 'The contribution lines are incomplete; reload the page and try again';
                $n = 0;
                break;
            }
        }

        $categories = array_map('intval',$pdo->query('SELECT ID FROM tblcontributioncategories')->fetchAll(PDO::FETCH_COLUMN));
        $lines = [];
        $total = 0;
        for ($i = 0; $i < $n; $i++) {
            $no = $i + 1;
            $amount = trim((string)$data['amounts'][$i]);
            if ($err = Ledger::amountError($amount,"Line $no amount")) {
                $errors[] = $err;
                continue;
            }
            $cat = (int)$data['categoriesid'][$i];
            if (!in_array($cat,$categories,true)) {
                $errors[] = "Line $no: select a valid category";
                continue;
            }
            $contributor = (int)$data['contributorsid'][$i];
            if ($contributor <= 0) {
                $errors[] = "Line $no: select the contributor";
                continue;
            }

            $sub = trim((string)$data['subaccount'][$i]);
            $subId = null;
            if ($sub === '0') {
                $sub = '';   // the old update stored a blank sub account as 0: not a collection line
            } elseif ($sub !== '' && !ctype_digit($sub)) {
                $errors[] = "Line $no: sub account is not valid";
                continue;
            }
            if ($sub !== '') {
                // group / district collection: the income account is the one set up on the sub account
                $subId = (int)$sub;
                $row = loadresultset($pdo,'SELECT s.AccountName,s.AccountId,s.GroupId,s.DistrictId
                                           FROM tblbanksubaccounts s JOIN tblaccounttypes b ON b.ID = s.BankId
                                           WHERE s.ID=? AND s.Deleted=0 AND b.deleted=0 AND b.congregationId=?',[$subId,$cong]);
                if (!$row) {
                    $errors[] = "Line $no: sub account not found for this congregation";
                    continue;
                }
                $row = $row[0];
                $name = strtoupper($row->AccountName);
                if (!(($cat === 2 && (int)$row->GroupId === $contributor) || ($cat === 3 && (int)$row->DistrictId === $contributor))) {
                    $errors[] = "Line $no: sub account '$name' does not belong to the selected group / district";
                    continue;
                }
                $account = (int)$row->AccountId;
                if ($problem = Ledger::postingProblem($pdo,$account,$cong,isset($usedAccounts[$account]))) {
                    $errors[] = "Line $no: the account set up on sub account '$name' cannot be posted to ($problem); correct the sub account before saving";
                    continue;
                }
            } else {
                $account = (int)$data['accountsid'][$i];
                if ($account <= 0) {
                    $errors[] = "Line $no: select the contribution type";
                    continue;
                }
                // checked here, before any row is written, so a bad account gives a message instead of a database error
                if ($problem = Ledger::postingProblem($pdo,$account,$cong,isset($usedAccounts[$account]))) {
                    $errors[] = "Line $no: account $account cannot be posted to ($problem)";
                    continue;
                }
                if ((int)getdbvalue($pdo,'SELECT isBank FROM tblaccounttypes WHERE ID=?',[$account]) === 1) {
                    $errors[] = "Line $no: a bank account cannot be a contribution type";
                    continue;
                }
            }

            $lines[] = [
                'account' => $account,
                'amount' => $amount,
                'category' => $cat,
                'contributor' => $contributor,
                'sub' => $subId,
                'forgroup' => (int)getdbvalue($pdo,'SELECT forGroup FROM tblaccounttypes WHERE ID=?',[$account]),
            ];
            $total += (int)round((float)$amount * 100);
        }

        if ($errors) {
            throw new LedgerException($errors);
        }

        return [
            'yearId' => $yearId,
            'reference' => $reference,
            'refLower' => $reference !== '' ? strtolower($reference) : null,
            'receiving' => $receiving,
            'bank' => $bank,
            'lines' => $lines,
            'total' => number_format($total / 100,2,'.',''),
        ];
    }

    /** Detail rows, plus tblmmf and sub account rows for group / district collection lines. */
    private function saveDetails($pdo,$cong,$id,$data,$p,$narration)
    {
        $ins = $pdo->prepare('INSERT INTO tblcontributions_details(HeaderId,contributionDate,contributionTypeId,paymentMethodId,bankId,
                                    amount,category,contributor,contributotGroup,contributotDistrict,contributotService,contributotCong,
                                    paymentReference,narration,incomeType,forGroup,subaccount)
                              VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)');
        $mmf = $pdo->prepare('INSERT INTO tblmmf (TransactionDate,`Type`,DistrictId,GroupId,Debit,SubAccountId,Narration,TransactionType,
                                      TransactionId,CongregationId) VALUES(?,?,?,?,?,?,?,1,?,?)');
        $subrow = $pdo->prepare('INSERT INTO tblbanktransactions_subaccounts (TransactionDate,TransactionId,SubAccountId,Amount,
                                         TransactionType,Narration,Reference,CongregationId) VALUES (?,?,?,?,1,?,?,?)');
        $isParish = (int)$_SESSION['isParish'] === 1;

        foreach ($p['lines'] as $l) {
            $c = $l['contributor'];
            $ins->execute([
                $id,$data['date'],$l['account'],(int)$data['paymethod'],$p['bank'],$l['amount'],$l['category'],
                !$isParish && $l['category'] === 1 ? $c : null,
                !$isParish && $l['category'] === 2 ? $c : null,
                !$isParish && $l['category'] === 3 ? $c : null,
                !$isParish && $l['category'] === 4 ? $c : null,
                $isParish ? $c : null,
                $p['refLower'],$narration,$l['forgroup'],$l['sub']
            ]);

            if ($l['sub'] !== null) {
                $mmf->execute([$data['date'],$l['category'] === 2 ? 'group' : 'district',
                               $l['category'] === 3 ? $c : null,$l['category'] === 2 ? $c : null,
                               $l['amount'],$l['sub'],$narration,$id,$cong]);
                $subrow->execute([$data['date'],$id,$l['sub'],$l['amount'],'Collection',$p['refLower'],$cong]);
            }
        }
    }

    /** Dr the receiving account (total) / Cr one income line per detail row, then the bank row of a bank receipt. */
    private function postReceipt($pdo,$cong,$id,$data,$p,$description)
    {
        $lines = [Ledger::debit($p['receiving'],$p['total'],$description)];
        foreach ($p['lines'] as $l) {
            $lines[] = Ledger::credit($l['account'],$l['amount'],$description);
        }
        Ledger::post($pdo,[
            'congregationId' => $cong,
            'entryDate' => $data['date'],
            'transactionTypeId' => 1,
            'transactionId' => (int)$id,
            'reference' => $p['reference'],
            'description' => $description
        ],$lines);
        Ledger::syncBankPostings($pdo,$cong,1,(int)$id,self::BANK_METHOD,$p['reference']);
    }

    private function descriptions($data)
    {
        $desc = mb_substr(trim((string)($data['description'] ?? '')),0,255);
        if ($desc === '') {
            $desc = 'receipts for ' . date('d-m-Y',strtotime($data['date']));
        }
        $narr = mb_substr(strtolower($desc),0,self::NARRATION_LEN);
        return [$desc,$narr];
    }

    /** @return array ['success'=>bool,'errors'=>string[],'id'=>int,'receiptno'=>string] (no errors on failure = unexpected error, logged) */
    public function save($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $p = $this->prepare($pdo,$cong,$data,[]);
            [$desc,$narr] = $this->descriptions($data);

            $receiptNo = $this->formatReceiptNo($p['yearId'],Ledger::nextNumber($pdo,$cong,Ledger::SEQ_RECEIPT,$p['yearId']));
            $st = $pdo->prepare('INSERT INTO tblcontributions_header (fiscalYearId,postedBy,postedDate,receiptNo,congregationId)
                                 VALUES (?,?,?,?,?)');
            $st->execute([$p['yearId'],$_SESSION['userId'],date('Y-m-d'),$receiptNo,$cong]);
            $id = (int)$pdo->lastInsertId();

            $this->saveDetails($pdo,$cong,$id,$data,$p,$narr);
            $this->postReceipt($pdo,$cong,$id,$data,$p,$desc);

            saveLog($pdo,'Created Contribution For '.$data['date']);
            $pdo->commit();
            return ['success' => true,'errors' => [],'id' => $id,'receiptno' => $receiptNo];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }

    public function create($data)
    {
        if(!$data['isedit']){
            return $this->save($data);
        }else{
            return $this->update($data);
        }
    }

    public function getforgroup($account)
    {
        $this->db->query('SELECT forGroup,accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        $results = $this->db->fetch();
        foreach ($results as $result ) {
            $output['forGroup'] = $result['forGroup'];
            $output['accountTypeId'] = $result['accountTypeId'];
        }
        return json_encode($output);
    }

    public function approve($data)
    {
        $this->db->query('UPDATE tblcontributions_header SET `status`=:app WHERE (ID=:id) AND (congregationId=:cid)');
        $this->db->bind(':app',1);
        $this->db->bind(':id',$data['id']);
        $this->db->bind(':cid',$_SESSION['congId']);
        if ($this->db->execute()) {
            $act = 'Approved Contribution For '.$data['contributor']. ' For Date '.$data['date'];
            saveLog($this->db->dbh,$act);
            return true;
        }
        else{
            return false;
        }
    }

    /** Soft delete + void. Returns ['success'=>bool,'errors'=>string[]]. */
    public function delete($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $id = (int)($data['id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $hdr = $id > 0 ? $this->lockHeader($pdo,$cong,$id) : null;
            if ($hdr === null) {
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Contribution not found for this congregation']];
            }
            if ($problem = fiscalYearIdClosedProblem($pdo,(int)$hdr->fiscalYearId)) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [$problem]];
            }

            if ($problem = Ledger::clearedBankProblem($pdo,$cong,1,$id)) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [$problem]];
            }

            $pdo->prepare('UPDATE tblcontributions_header SET Deleted=1 WHERE ID=?')->execute([$id]);
            Ledger::void($pdo,$cong,1,$id);
            Ledger::voidBankPostings($pdo,$cong,1,$id);
            $pdo->prepare('UPDATE tblmmf SET Deleted=1 WHERE TransactionType=1 AND TransactionId=? AND CongregationId=?')->execute([$id,$cong]);
            $pdo->prepare('DELETE FROM tblbanktransactions_subaccounts WHERE TransactionType=1 AND TransactionId=? AND CongregationId=?')->execute([$id,$cong]);

            saveLog($pdo,'Deleted Contribution receipt '.$hdr->receiptNo);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }

    /** First detail row of an active receipt of this congregation (date, method, bank, reference, narration), or false. */
    public function contributionHeader($id)
    {
        $this->db->query('SELECT
                            h.receiptNo,
                            `fiscalYearId`,
                            `contributionDate`,
                            `paymentMethodId`,
                            `bankId`,
                            `narration`,
                            `paymentReference`,
                            h.congregationId,
                            h.ID
                          FROM `tblcontributions_details` d
                            INNER JOIN tblcontributions_header h
                            ON d.HeaderId = h.ID
                          WHERE HeaderId = :id AND h.Deleted = 0
                          LIMIT 1;');
        $this->db->bind(':id',intval($id));
        return $this->db->single();
    }

    /** The cash account a cash receipt was posted to (the entry's debit line), or null when the receipt has no entry. */
    public function getReceivingAccount($id,$cong)
    {
        $entry = Ledger::getEntry($this->db->pdo(),(int)$cong,1,(int)$id);
        if ($entry === null) {
            return null;
        }
        foreach ($entry['lines'] as $l) {
            if ($l['dc'] === Ledger::DEBIT) {
                return (int)$l['accountId'];
            }
        }
        return null;
    }

    public function getContribution($id)
    {
        $this->db->query('SELECT * FROM vw_contributorspertxn WHERE (HeaderId = :id)');
        $this->db->bind(':id',$id);
        return $this->db->resultSet();
    }

    /** @return array ['success'=>bool,'errors'=>string[],'id'=>int,'receiptno'=>string] */
    public function update($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $id = (int)($data['id'] ?? 0);
        try {
            $pdo->beginTransaction();
            $hdr = $id > 0 ? $this->lockHeader($pdo,$cong,$id) : null;
            if ($hdr === null) {
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Contribution not found for this congregation']];
            }
            if ($problem = fiscalYearIdClosedProblem($pdo,(int)$hdr->fiscalYearId)) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [$problem]];
            }

            $used = [];
            if ($entry = Ledger::getEntry($pdo,$cong,1,$id)) {
                foreach ($entry['lines'] as $l) { $used[(int)$l['accountId']] = true; }
            }
            $p = $this->prepare($pdo,$cong,$data,$used);
            [$desc,$narr] = $this->descriptions($data);
            // a cleared (reconciled) bank row may stay, but not with another bank or total
            if ($problem = Ledger::clearedBankProblem($pdo,$cong,1,$id,['bankId' => $p['receiving'],'amount' => $p['total']])) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [$problem]];
            }

            // the number stays; when the date moves the receipt into another fiscal year it gets the next number of the
            // new year's sequence and the old number is retired (never handed out again)
            $oldNo = $hdr->receiptNo;
            $moved = (int)$hdr->fiscalYearId !== $p['yearId'];
            $receiptNo = !$moved
                ? $hdr->receiptNo
                : $this->formatReceiptNo($p['yearId'],Ledger::nextNumber($pdo,$cong,Ledger::SEQ_RECEIPT,$p['yearId']));
            $pdo->prepare('UPDATE tblcontributions_header SET fiscalYearId=?,receiptNo=? WHERE ID=?')->execute([$p['yearId'],$receiptNo,$id]);

            $pdo->prepare('DELETE FROM tblcontributions_details WHERE HeaderId=?')->execute([$id]);
            $pdo->prepare('DELETE FROM tblmmf WHERE TransactionType=1 AND TransactionId=? AND CongregationId=?')->execute([$id,$cong]);
            $pdo->prepare('DELETE FROM tblbanktransactions_subaccounts WHERE TransactionType=1 AND TransactionId=? AND CongregationId=?')->execute([$id,$cong]);

            $this->saveDetails($pdo,$cong,$id,$data,$p,$narr);
            $this->postReceipt($pdo,$cong,$id,$data,$p,$desc);

            saveLog($pdo,'Updated Contribution For '.$data['date'].($moved ? ' (receipt renumbered from '.$oldNo.' to '.$receiptNo.')' : ''));
            $pdo->commit();
            $res = ['success' => true,'errors' => [],'id' => $id,'receiptno' => $receiptNo];
            if ($moved) {
                $res['renumbered'] = 'Receipt renumbered from '.$oldNo.' to '.$receiptNo.' because the date moved to '.getdbvalue($pdo,'SELECT yearName FROM tblfiscalyears WHERE ID=?',[$p['yearId']]);
            }
            return $res;
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }
}
