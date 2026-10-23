<?php

/**
 * Suppliers and their opening balances (transaction type 15), task 8.7.
 *
 * The opening balance is a tblinvoice_header_suppliers row with invoiceNo NULL (it appears in the balances and can be
 * paid like an invoice); its credit account is stored in PayableAccountId (any posting account under 23). The entry is
 * keyed on the SUPPLIER ID (transactionType 15, transactionId = tblsuppliers.ID, as the legacy code did; a supplier has
 * at most one opening balance row, so the key is unique):
 *   positive balance (we owe the supplier): Dr 20 'opening balance equity' / Cr the payable account
 *   negative balance (the supplier owes us): Dr the payable account / Cr 20, both amounts positive
 * A zero balance posts nothing and voids an existing entry. The row stores the signed balance in inclusiveVat, as before.
 */
class Supplier
{
    const TYPE_OPENING = 15;
    /** 'opening balance equity', the fixed system account every opening balance uses (decision 79; same as Bank) */
    const OPENING_BALANCE_EQUITY_ID = 20;
    const MAX_CENTS = 9999999999999;

    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function GetSuppliers()
    {
        return loadresultset($this->db->dbh,'SELECT * FROM vw_suppliers WHERE congregationId = ?',[(int)$_SESSION['congId']]);
    }

    public function getPayableAccounts(): array
    {
        return array_map(function($a){ return (object)$a; },Ledger::listPayableAccounts($this->db->dbh,(int)$_SESSION['congId']));
    }

    public function getDefaultPayable(): ?int
    {
        return Ledger::accountsPayableId($this->db->dbh);
    }

    /** One account (ID, accountType, parentName), for a dropdown that must still show an account no longer on the list. */
    public function getAccountRow(int $id)
    {
        $st = $this->db->dbh->prepare('SELECT a.ID, a.accountType, p.accountType AS parentName FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID = a.parentId WHERE a.ID = ?');
        $st->execute([$id]);
        $r = $st->fetch(PDO::FETCH_OBJ);
        return $r ?: null;
    }

    private static function cents(string $s): int
    {
        $parts = explode('.',ltrim($s,'-'),2);
        $c = ((int)$parts[0]) * 100 + (int)str_pad(substr($parts[1] ?? '',0,2),2,'0');
        return $s !== '' && $s[0] === '-' ? -$c : $c;
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100,2,'.','');
    }

    private static function scalar($v): ?string
    {
        return is_scalar($v) || $v === null ? trim((string)$v) : null;
    }

    /** Names are stored lowercase; unique per congregation among the non-deleted suppliers. */
    public function CheckExists($name,$id)
    {
        $count = getdbvalue($this->db->dbh,'SELECT COUNT(*)
                                            FROM tblsuppliers
                                            WHERE (LOWER(TRIM(supplierName)) = ?) AND (ID <> ?) AND (deleted = 0) AND (congregationId = ?)',
                            [strtolower(trim((string)$name)),(int)$id,(int)$_SESSION['congId']]);
        return (int)$count === 0;
    }

    /** The supplier's single active opening balance row (locked when $lock), null, or false when there are several. */
    private function openingRow(int $supplierId, bool $lock)
    {
        $st = $this->db->dbh->prepare('SELECT * FROM tblinvoice_header_suppliers
                                       WHERE supplierId = ? AND congregationId = ? AND invoiceNo IS NULL AND deleted = 0 ORDER BY ID' . ($lock ? ' FOR UPDATE' : ''));
        $st->execute([$supplierId,(int)$_SESSION['congId']]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) { return false; }
        return $rows ? $rows[0] : null;
    }

    private function activePayments(int $headerId): int
    {
        $st = $this->db->dbh->prepare('SELECT COUNT(*) FROM tblinvoice_payments_suppliers WHERE invoice_Id = ? AND Deleted = 0');
        $st->execute([$headerId]);
        return (int)$st->fetchColumn();
    }

    /**
     * Create or update a supplier and its opening balance. $data: id, isedit, suppliername, contact, pin, address, email,
     * contactperson, balance (signed, blank = 0), asof, payable. Returns ['success'=>true,'id'=>ID] or ['success'=>false,'errors'=>[...]].
     */
    public function CreateUpdate($data): array
    {
        $pdo = $this->db->dbh;
        try {
            $cong = (int)$_SESSION['congId'];
            $errors = [];
            $isedit = !empty($data['isedit']);

            $id = self::scalar($data['id'] ?? 0);
            if ($isedit && ($id === null || !ctype_digit($id) || (int)$id <= 0)) { $errors[] = 'Supplier not found for this congregation'; }
            $name = self::scalar($data['suppliername'] ?? '');
            if ($name === null || $name === '') { $errors[] = 'Enter supplier name'; }
            elseif (strlen($name) > 255) { $errors[] = 'Supplier name cannot be longer than 255 characters'; }
            elseif (!$errors && !$this->CheckExists($name,$isedit ? (int)$id : 0)) { $errors[] = 'Supplier name exists'; }
            foreach ([['contact',20,'Contact'],['pin',15,'PIN'],['address',255,'Address'],['email',255,'Email'],['contactperson',255,'Contact person']] as [$k,$max,$label]) {
                $v = self::scalar($data[$k] ?? '');
                if ($v === null || strlen($v) > $max) { $errors[] = "$label cannot be longer than $max characters"; }
            }
            $email = self::scalar($data['email'] ?? '');
            if ($email !== null && $email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email'; }

            // opening balance: signed, up to 2 decimals
            $balanceText = self::scalar($data['balance'] ?? '');
            $balance = 0;
            if ($balanceText === null || ($balanceText !== '' && !preg_match('/^-?(\d+\.?\d*|\.\d+)$/',$balanceText))) { $errors[] = 'Opening balance must be a number'; }
            elseif ($balanceText !== '') {
                if (round((float)$balanceText,2) != (float)$balanceText) { $errors[] = 'Opening balance cannot have more than 2 decimal places'; }
                else { $balance = self::cents($balanceText); }
                if (abs($balance) > self::MAX_CENTS) { $errors[] = 'Opening balance is too large'; $balance = 0; }
            }
            $asof = self::scalar($data['asof'] ?? '');
            $payable = self::scalar($data['payable'] ?? '');
            if ($balance !== 0) {
                if ($asof === null || !date_is_valid($asof)) { $errors[] = 'Enter a valid date of balance'; }
                elseif ($asof > date('Y-m-d')) { $errors[] = 'Invalid balance date selected'; }
                if ($payable === null || !ctype_digit($payable) || (int)$payable <= 0) { $errors[] = 'Select the crediting account'; }
            }
            if ($errors) { throw new LedgerException(array_values(array_unique($errors))); }

            $pdo->beginTransaction();
            $pdo->prepare('SELECT ID FROM tblcongregation WHERE ID = ? FOR UPDATE')->execute([$cong]);
            // the name rule again under the lock (two saves of the same name at once)
            if (!$this->CheckExists($name,$isedit ? (int)$id : 0)) { throw new LedgerException(['Supplier name exists']); }

            if ($isedit) {
                $st = $pdo->prepare('SELECT * FROM tblsuppliers WHERE ID = ? AND congregationId = ? AND deleted = 0 FOR UPDATE');
                $st->execute([(int)$id,$cong]);
                if (!$st->fetch()) { throw new LedgerException(['Supplier not found for this congregation']); }
                $pdo->prepare('UPDATE tblsuppliers SET supplierName=?,contact=?,`address`=?,pin=?,email=?,contactPerson=? WHERE ID = ? AND congregationId = ?')
                    ->execute([strtolower($name),$this->nullIfBlank($data['contact']),$this->nullIfBlank($data['address'],true),$this->nullIfBlank($data['pin']),$this->nullIfBlank($data['email'],true),$this->nullIfBlank($data['contactperson'],true),(int)$id,$cong]);
                $sid = (int)$id;
            } else {
                $pdo->prepare('INSERT INTO tblsuppliers (supplierName,contact,`address`,pin,email,contactPerson,congregationId) VALUES(?,?,?,?,?,?,?)')
                    ->execute([strtolower($name),$this->nullIfBlank($data['contact']),$this->nullIfBlank($data['address'],true),$this->nullIfBlank($data['pin']),$this->nullIfBlank($data['email'],true),$this->nullIfBlank($data['contactperson'],true),$cong]);
                $sid = (int)$pdo->lastInsertId();
            }

            $this->saveOpeningBalance($sid,$balance,$asof,$payable !== null && ctype_digit($payable) ? (int)$payable : 0);

            saveLog($pdo,($isedit ? 'Updated supplier ' : 'Created supplier ') . strtolower($name));
            $pdo->commit();
            return ['success' => true,'id' => $sid];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Unable to save supplier. Retry or contact admin']];
        }
    }

    private function nullIfBlank($v, bool $lower = false): ?string
    {
        $v = trim((string)$v);
        if ($v === '') { return null; }
        return $lower ? strtolower($v) : $v;
    }

    /**
     * Bring the opening balance row and its type-15 entry to $balance (cents, signed) as of $asof credited to $payable.
     * Runs inside CreateUpdate's transaction; throws a LedgerException for every refusal.
     */
    private function saveOpeningBalance(int $sid, int $balance, ?string $asof, int $payable): void
    {
        $pdo = $this->db->dbh;
        $cong = (int)$_SESSION['congId'];
        $row = $this->openingRow($sid,true);
        if ($row === false) { throw new LedgerException(['This supplier has more than one opening balance row; correct the data first']); }

        if ($row !== null) {
            // nothing changed (a zero balance has no date or account to compare): leave the row and its entry alone, in any year
            $stored = self::cents((string)$row['inclusiveVat']);
            if ($balance === $stored && ($balance === 0 || ($asof === $row['invoiceDate'] && $payable === (int)$row['PayableAccountId']))) { return; }
            if (($p = Ledger::closedProblem($pdo,$row['invoiceDate'])) !== null) {
                throw new LedgerException([$p . ': the opening balance is dated ' . $row['invoiceDate'] . ' and cannot be changed']);
            }
            if ($this->activePayments((int)$row['ID']) > 0) {
                throw new LedgerException(['The opening balance has payments, so its amount, date and crediting account cannot be changed']);
            }
        }

        if ($balance === 0) {
            if ($row !== null) {
                Ledger::void($pdo,$cong,self::TYPE_OPENING,$sid);
                Ledger::voidBankPostings($pdo,$cong,self::TYPE_OPENING,$sid);
                $pdo->prepare('UPDATE tblinvoice_header_suppliers SET deleted = 1 WHERE ID = ? AND congregationId = ?')->execute([(int)$row['ID'],$cong]);
            }
            return;
        }

        $keepPayable = $row !== null && (int)$row['PayableAccountId'] === $payable;
        Ledger::assertPayableAccount($pdo,$cong,$payable,$keepPayable);
        if (($p = Ledger::dateProblem($pdo,$asof)) !== null) { throw new LedgerException([$p]); }
        $year = Ledger::yearForDate($pdo,$asof);

        if ($row !== null) {
            $pdo->prepare('UPDATE tblinvoice_header_suppliers SET invoiceDate = ?, fiscalYearId = ?, PayableAccountId = ?, inclusiveVat = ?, `status` = 0 WHERE ID = ? AND congregationId = ?')
                ->execute([$asof,(int)$year['ID'],$payable,self::money($balance),(int)$row['ID'],$cong]);
        } else {
            $pdo->prepare('INSERT INTO tblinvoice_header_suppliers (invoiceDate,supplierId,fiscalYearId,PayableAccountId,inclusiveVat,postedBy,congregationId) VALUES(?,?,?,?,?,?,?)')
                ->execute([$asof,$sid,(int)$year['ID'],$payable,self::money($balance),(int)$_SESSION['userId'],$cong]);
        }

        $amount = self::money(abs($balance));
        $memo = 'supplier opening balance';
        $equity = self::OPENING_BALANCE_EQUITY_ID;
        $lines = $balance > 0
            ? [Ledger::debit($equity,$amount,$memo),Ledger::credit($payable,$amount,$memo)]
            : [Ledger::debit($payable,$amount,$memo),Ledger::credit($equity,$amount,$memo)];
        Ledger::post($pdo,[
            'congregationId' => $cong,
            'entryDate' => $asof,
            'transactionTypeId' => self::TYPE_OPENING,
            'transactionId' => $sid,
            'description' => $memo,
        ],$lines);
    }

    /** A supplier of this congregation with its opening balance (balance, asof, payable) for the edit form, or false. */
    public function GetSupplier($id)
    {
        if (!ctype_digit((string)$id) || (int)$id <= 0) { return false; }
        $this->db->query('SELECT * FROM tblsuppliers WHERE ID = :id AND congregationId = :cid AND deleted = 0');
        $this->db->bind(':id',(int)$id);
        $this->db->bind(':cid',(int)$_SESSION['congId']);
        $supplier = $this->db->single();
        if (!$supplier) { return false; }
        $row = $this->openingRow((int)$supplier->ID,false);
        $supplier->opening = $row ?: null;
        $supplier->openingHasPayments = $row ? $this->activePayments((int)$row['ID']) > 0 : false;
        return $supplier;
    }

    /** Why a supplier cannot be deleted (a normal invoice or any payment exists), or null. */
    public function ReferencedReason(int $id): ?string
    {
        $pdo = $this->db->dbh;
        $st = $pdo->prepare('SELECT COUNT(*) FROM tblinvoice_header_suppliers WHERE supplierId = ? AND congregationId = ? AND invoiceNo IS NOT NULL AND deleted = 0');
        $st->execute([$id,(int)$_SESSION['congId']]);
        if ((int)$st->fetchColumn() > 0) { return 'Cannot delete supplier as they have invoices'; }
        $st = $pdo->prepare('SELECT COUNT(*) FROM tblinvoice_payments_suppliers p JOIN tblinvoice_header_suppliers h ON h.ID = p.invoice_Id
                             WHERE h.supplierId = ? AND h.congregationId = ? AND p.Deleted = 0');
        $st->execute([$id,(int)$_SESSION['congId']]);
        if ((int)$st->fetchColumn() > 0) { return 'Cannot delete supplier as they have payments'; }
        return null;
    }

    /** Delete = void the opening balance entry and soft-delete the supplier with its opening row. Returns ['success','errors']. */
    public function Delete($id): array
    {
        $pdo = $this->db->dbh;
        try {
            $cong = (int)$_SESSION['congId'];
            if (!ctype_digit((string)$id) || (int)$id <= 0) { throw new LedgerException(['Unable to get selected supplier']); }
            $id = (int)$id;
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT supplierName FROM tblsuppliers WHERE ID = ? AND congregationId = ? AND deleted = 0 FOR UPDATE');
            $st->execute([$id,$cong]);
            $supplier = $st->fetchColumn();
            if ($supplier === false) { throw new LedgerException(['Supplier not found for this congregation']); }
            if (($why = $this->ReferencedReason($id)) !== null) { throw new LedgerException([$why]); }

            $row = $this->openingRow($id,true);
            if ($row === false) { throw new LedgerException(['This supplier has more than one opening balance row; correct the data first']); }
            if ($row !== null && ($p = Ledger::closedProblem($pdo,$row['invoiceDate'])) !== null) {
                throw new LedgerException([$p . ': the opening balance is dated ' . $row['invoiceDate'] . ' and cannot be deleted']);
            }
            Ledger::void($pdo,$cong,self::TYPE_OPENING,$id);
            Ledger::voidBankPostings($pdo,$cong,self::TYPE_OPENING,$id);

            $pdo->prepare('UPDATE tblinvoice_header_suppliers SET deleted = 1 WHERE supplierId = ? AND congregationId = ? AND invoiceNo IS NULL')->execute([$id,$cong]);
            $pdo->prepare('UPDATE tblsuppliers SET deleted = 1 WHERE ID = ? AND congregationId = ?')->execute([$id,$cong]);
            saveLog($pdo,'Deleted supplier ' . $supplier);
            $pdo->commit();
            return ['success' => true];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Something went wrong while deleting. Retry or contact admin']];
        }
    }
}
