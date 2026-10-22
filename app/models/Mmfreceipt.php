<?php
/**
 * MMF receipts (transaction type 11) on the Ledger library (task 8.9).
 *
 * A group draws on its held balance: tblmmf.Credit lowers the group's balance and the entry is
 *   Dr "Group funds held" account chosen on the form  /  Cr the chosen bank account, both for the amount,
 * plus one tblbankpostings row (method 'Withdrawal'). Nothing is inferred: both accounts come from the form and are
 * validated (funds held = an active posting liability account under 25 / 118, bank = PAY_BANK).
 *
 * Every action loads the record by ID + TransactionType 11 + session congregation (FOR UPDATE) and returns
 * ['success' => bool, 'errors' => string[]] (no errors on failure = unexpected error, logged).
 * Delete is a void: tblmmf.Deleted = 1, Ledger::void, Ledger::voidBankPostings.
 */
class Mmfreceipt
{
    const TYPE = 11;
    const BANK_METHOD = 'Withdrawal';
    /** parents of the "group funds held" accounts: 25 'group funds', 118 'district funds held' (confirmed by the user) */
    const FUNDS_HELD_PARENTS = [25,118];
    const FUNDS_HELD_TYPE = 4;      // liability
    const REFERENCE_LEN = 25;       // tblmmf.Reference

    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetMMFs()
    {
        $this->db->query("SELECT m.ID,
                            DATE_FORMAT(m.TransactionDate,'%d/%m/%y') As TransactionDate,
                            UCASE(g.groupName) AS GroupName,
                            FORMAT(m.Credit,2) As Debit,
                            UCASE(m.Reference) As Reference
                        FROM
                            tblmmf m
                                inner join tblgroups g
                                    on m.GroupId = g.ID
                        WHERE
                            m.CongregationId = :cid AND m.TransactionType = 11 AND m.Deleted = 0
                        ORDER BY m.TransactionDate DESC");
        $this->db->bind(':cid',intval($_SESSION['congId']));
        return $this->db->resultSet();
    }

    /**
     * The group's balance on $date (rows dated up to and including it, deleted rows ignored), leaving out the record
     * being edited. Computed here for the form AND for the save check, so both agree.
     */
    public function GetBalance($groupId,$date,$excludeId = 0)
    {
        $st = $this->db->pdo()->prepare('SELECT IFNULL(SUM(Debit),0) - IFNULL(SUM(Credit),0) FROM tblmmf
                                         WHERE GroupId = ? AND CongregationId = ? AND Deleted = 0 AND TransactionDate <= ? AND ID <> ?');
        $st->execute([(int)$groupId,(int)$_SESSION['congId'],$date,(int)$excludeId]);
        return round((float)$st->fetchColumn(),2);
    }

    public function GetGroups()
    {
        $this->db->query("SELECT ID,UCASE(groupName) AS GroupName
                          FROM tblgroups
                          WHERE (active = 1) AND (Deleted = 0) AND (congregationId = :cid)");
        $this->db->bind(':cid',intval($_SESSION['congId']));
        return $this->db->resultSet();
    }

    /** Banks for the credit dropdown: the PAY_BANK accounts (active posting banks of the congregation). [ (object)['ID','Bank'] ] */
    public function GetBanks()
    {
        $pdo = $this->db->pdo();
        $out = [];
        foreach (Ledger::listPaymentAccounts($pdo,(int)$_SESSION['congId'],Ledger::PAY_BANK) as $a) {
            $no = getdbvalue($pdo,'SELECT accountNo FROM tblaccounttypes WHERE ID = ?',[$a['ID']]);
            $out[] = (object)['ID' => $a['ID'],'Bank' => strtoupper($a['accountType']) . '-' . ($no ?? '')];
        }
        return $out;
    }

    /**
     * "Group funds held" accounts for the debit dropdown: active posting liability accounts that are children of
     * FUNDS_HELD_PARENTS. [ (object)['ID','Account'] ]
     */
    public function GetFundsHeldAccounts()
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        $in = implode(',',array_fill(0,count(self::FUNDS_HELD_PARENTS),'?'));
        $st = $pdo->prepare("SELECT ID,accountType FROM tblaccounttypes
                             WHERE accountTypeId = ? AND parentId IN ($in) AND deleted = 0 AND active = 1 AND isSubCategory = 1
                               AND congregationId IN (0,?)
                             ORDER BY accountType");
        $st->execute(array_merge([self::FUNDS_HELD_TYPE],self::FUNDS_HELD_PARENTS,[$cong]));
        $rows = $st->fetchAll(PDO::FETCH_OBJ);
        $ok = array_flip(Ledger::filterPostingAccounts($pdo,$cong,array_map(function ($r) { return (int)$r->ID; },$rows)));
        $out = [];
        foreach ($rows as $r) {
            if (isset($ok[(int)$r->ID])) {
                $out[] = (object)['ID' => (int)$r->ID,'Account' => strtoupper($r->accountType)];
            }
        }
        return $out;
    }

    /** The receipt (this congregation, type 11, not deleted) or false. */
    public function GetMmf($id)
    {
        $this->db->query('SELECT * FROM tblmmf WHERE ID = :id AND TransactionType = 11 AND CongregationId = :cid AND Deleted = 0');
        $this->db->bind(':id',intval($id));
        $this->db->bind(':cid',intval($_SESSION['congId']));
        return $this->db->single();
    }

    /** The debit (funds held) account of the receipt's active entry, to preselect on the edit form. */
    public function GetFundsHeldOf($id)
    {
        $entry = Ledger::getEntry($this->db->pdo(),(int)$_SESSION['congId'],self::TYPE,(int)$id);
        foreach (($entry['lines'] ?? []) as $l) {
            if ($l['dc'] === Ledger::DEBIT) {
                return (int)$l['accountId'];
            }
        }
        return '';
    }

    /**
     * Reference problems: required, at most REFERENCE_LEN characters, not used by another active MMF receipt of the
     * congregation. Returns a message or null.
     */
    public function ReferenceProblem($ref,$id)
    {
        $ref = trim((string)$ref);
        if ($ref === '') {
            return 'Enter reference';
        }
        if (mb_strlen($ref) > self::REFERENCE_LEN) {
            return 'Reference cannot be longer than ' . self::REFERENCE_LEN . ' characters';
        }
        $st = $this->db->pdo()->prepare('SELECT COUNT(*) FROM tblmmf
                                         WHERE Reference = ? AND TransactionType = 11 AND Deleted = 0 AND ID <> ? AND CongregationId = ?');
        $st->execute([strtolower($ref),(int)$id,(int)$_SESSION['congId']]);
        return (int)$st->fetchColumn() > 0 ? 'Reference already exists' : null;
    }

    /**
     * $data: isedit, id, tdate (Y-m-d), groupid, amount, bank (account ID), fundsheld (account ID), reference.
     * Returns ['success','errors','id'].
     */
    public function CreateUpdate($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();

            $current = null;
            if ($data['isedit']) {
                $st = $pdo->prepare('SELECT * FROM tblmmf WHERE ID = ? AND TransactionType = 11 AND CongregationId = ? AND Deleted = 0 FOR UPDATE');
                $st->execute([(int)$data['id'],$cong]);
                $current = $st->fetch(PDO::FETCH_OBJ);
                if (!$current) {
                    return $this->refuse($pdo,'MMF receipt not found');
                }
            }
            $id = $current ? (int)$current->ID : 0;

            $errors = [];
            $date = DateTime::createFromFormat('!Y-m-d',(string)$data['tdate']);
            if (!$date || $date->format('Y-m-d') !== $data['tdate']) {
                $errors[] = 'Select a valid date';
            } elseif ($data['tdate'] > date('Y-m-d')) {
                $errors[] = 'Invalid date selected';
            }

            $group = false;
            $st = $pdo->prepare('SELECT ID FROM tblgroups WHERE ID = ? AND congregationId = ? AND deleted = 0 AND (active = 1 OR ID = ?)');
            $st->execute([(int)$data['groupid'],$cong,$current ? (int)$current->GroupId : 0]);
            if ($st->fetchColumn() === false) {
                $errors[] = 'Select a valid group';
            } else {
                $group = (int)$data['groupid'];
            }

            if ($m = Ledger::amountError($data['amount'])) {
                $errors[] = $m;
            }
            if ($m = $this->ReferenceProblem($data['reference'],$id)) {
                $errors[] = $m;
            }

            $funds = (int)$data['fundsheld'];
            $allowed = array_map(function ($a) { return (int)$a->ID; },$this->GetFundsHeldAccounts());
            if (!in_array($funds,$allowed,true)) {
                $errors[] = 'Select a valid group funds held account';
            }
            $bank = (int)$data['bank'];
            try {
                Ledger::assertPaymentAccount($pdo,$cong,$bank,Ledger::PAY_BANK);
            } catch (LedgerException $e) {
                $errors[] = 'Select a valid bank';
            }

            if (!$errors && $date && $group) {
                $balance = $this->GetBalance($group,$data['tdate'],$id);
                if (round((float)$data['amount'] * 100) > round($balance * 100)) {
                    $errors[] = 'Cannot withdraw more than available amount (' . number_format($balance,2) . ')';
                }
            }
            if ($errors) {
                return $this->refuse($pdo,$errors);
            }

            $amount = number_format((float)$data['amount'],2,'.','');
            $ref = trim($data['reference']);
            if ($current) {
                $pdo->prepare('UPDATE tblmmf SET TransactionDate = ?, GroupId = ?, Credit = ?, BankId = ?, Reference = ?
                               WHERE ID = ? AND TransactionType = 11 AND CongregationId = ?')
                    ->execute([$data['tdate'],$group,$amount,$bank,strtolower($ref),$id,$cong]);
            } else {
                $pdo->prepare('INSERT INTO tblmmf (TransactionDate,GroupId,Credit,BankId,Reference,TransactionType,CongregationId)
                               VALUES (?,?,?,?,?,11,?)')
                    ->execute([$data['tdate'],$group,$amount,$bank,strtolower($ref),$cong]);
                $id = (int)$pdo->lastInsertId();
            }

            Ledger::post($pdo,[
                'congregationId' => $cong,'entryDate' => $data['tdate'],'transactionTypeId' => self::TYPE,'transactionId' => $id,
                'reference' => $ref,'description' => $ref,
            ],[
                Ledger::debit($funds,$amount,$ref),
                Ledger::credit($bank,$amount,$ref),
            ]);
            Ledger::syncBankPostings($pdo,$cong,self::TYPE,$id,self::BANK_METHOD,$ref);

            saveLog($pdo,($current ? 'Updated' : 'Created') . ' MMF receipt ' . $id . ' for ' . $data['tdate']);
            $pdo->commit();
            return ['success' => true,'errors' => [],'id' => $id];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }

    /** Void: tblmmf.Deleted = 1, the entry and its bank rows are voided (a closed financial year refuses it). */
    public function Delete($id)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT * FROM tblmmf WHERE ID = ? AND TransactionType = 11 AND CongregationId = ? AND Deleted = 0 FOR UPDATE');
            $st->execute([(int)$id,$cong]);
            $row = $st->fetch(PDO::FETCH_OBJ);
            if (!$row) {
                return $this->refuse($pdo,'MMF receipt not found');
            }

            Ledger::void($pdo,$cong,self::TYPE,(int)$row->ID);
            Ledger::voidBankPostings($pdo,$cong,self::TYPE,(int)$row->ID);
            $pdo->prepare('UPDATE tblmmf SET Deleted = 1 WHERE ID = ? AND TransactionType = 11 AND CongregationId = ?')
                ->execute([(int)$row->ID,$cong]);

            saveLog($pdo,'Deleted MMF receipt ' . (int)$row->ID . ' dated ' . $row->TransactionDate);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (\Exception $e) {
            return $this->fail($pdo,$e);
        }
    }

    private function refuse($pdo,$messages)
    {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false,'errors' => (array)$messages];
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
}
