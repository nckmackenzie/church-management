<?php

/** Customer master data. Customer records are always owned by the session congregation. */
class Customer
{
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function index(): array
    {
        $st = $this->db->dbh->prepare('SELECT * FROM tblcustomers WHERE congregationId = ? AND deleted = 0 ORDER BY customerName');
        $st->execute([(int)$_SESSION['congId']]);
        return $st->fetchAll(PDO::FETCH_OBJ);
    }

    public function getCustomer($id)
    {
        if (!ctype_digit((string)$id) || (int)$id <= 0) { return false; }
        $st = $this->db->dbh->prepare('SELECT * FROM tblcustomers WHERE ID = ? AND congregationId = ? AND deleted = 0');
        $st->execute([(int)$id,(int)$_SESSION['congId']]);
        return $st->fetch(PDO::FETCH_OBJ);
    }

    /** True when no other active customer of this congregation has the normalized name. */
    public function checkExists($name, $id = 0): bool
    {
        $st = $this->db->dbh->prepare('SELECT COUNT(*) FROM tblcustomers
                                       WHERE LOWER(TRIM(customerName)) = ? AND ID <> ? AND deleted = 0 AND congregationId = ?');
        $st->execute([strtolower(trim((string)$name)),(int)$id,(int)$_SESSION['congId']]);
        return (int)$st->fetchColumn() === 0;
    }

    /** Create/update with validation and an ownership check under the congregation lock. */
    public function save(array $data): array
    {
        $pdo = $this->db->dbh;
        try {
            $cong = (int)$_SESSION['congId'];
            $edit = !empty($data['isedit']);
            $id = self::scalar($data['id'] ?? '');
            $name = self::scalar($data['customername'] ?? '');
            $errors = [];

            if ($edit && ($id === null || !ctype_digit($id) || (int)$id <= 0)) { $errors[] = 'Customer not found for this congregation'; }
            if ($name === null || $name === '') { $errors[] = 'Enter customer name'; }
            elseif (strlen($name) > 255) { $errors[] = 'Customer name cannot be longer than 255 characters'; }

            $values = [];
            foreach ([['contact',20,'Contact'],['address',255,'Address'],['pin',15,'PIN'],['email',255,'Email'],['contactperson',255,'Contact person']] as [$key,$max,$label]) {
                $values[$key] = self::scalar($data[$key] ?? '');
                if ($values[$key] === null || strlen($values[$key]) > $max) { $errors[] = "$label cannot be longer than $max characters"; }
            }
            if ($values['contactperson'] === '') { $errors[] = 'Enter contact person'; }
            if ($values['email'] !== '' && !filter_var($values['email'],FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email'; }
            if (!$errors && !$this->checkExists($name,$edit ? (int)$id : 0)) { $errors[] = 'Customer name exists'; }
            if ($errors) { throw new LedgerException(array_values(array_unique($errors))); }

            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT ID FROM tblcongregation WHERE ID = ? FOR UPDATE');
            $lock->execute([$cong]);
            if (!$lock->fetchColumn()) { throw new LedgerException(['Customer congregation was not found']); }
            if (!$this->checkExists($name,$edit ? (int)$id : 0)) { throw new LedgerException(['Customer name exists']); }

            $fields = [
                strtolower($name), self::blankToNull($values['contact']), self::blankToNull($values['address'],true),
                self::blankToNull($values['pin']), self::blankToNull($values['email'],true), self::blankToNull($values['contactperson'],true),
            ];
            if ($edit) {
                $st = $pdo->prepare('SELECT customerName FROM tblcustomers WHERE ID = ? AND congregationId = ? AND deleted = 0 FOR UPDATE');
                $st->execute([(int)$id,$cong]);
                if ($st->fetchColumn() === false) { throw new LedgerException(['Customer not found for this congregation']); }
                $pdo->prepare('UPDATE tblcustomers SET customerName=?,contact=?,`address`=?,pin=?,email=?,contactPerson=? WHERE ID=? AND congregationId=?')
                    ->execute(array_merge($fields,[(int)$id,$cong]));
                $customerId = (int)$id;
            } else {
                $pdo->prepare('INSERT INTO tblcustomers(customerName,contact,`address`,pin,email,contactPerson,congregationId) VALUES(?,?,?,?,?,?,?)')
                    ->execute(array_merge($fields,[$cong]));
                $customerId = (int)$pdo->lastInsertId();
            }
            saveLog($pdo,($edit ? 'Updated customer ' : 'Created customer ') . strtolower($name));
            $pdo->commit();
            return ['success' => true,'id' => $customerId];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Unable to save customer. Retry or contact admin']];
        }
    }

    /** Existing behaviour is a soft delete; any invoice (the table has no deleted flag) prevents it. */
    public function delete($id): array
    {
        $pdo = $this->db->dbh;
        try {
            if (!ctype_digit((string)$id) || (int)$id <= 0) { throw new LedgerException(['Customer not found for this congregation']); }
            $id = (int)$id;
            $cong = (int)$_SESSION['congId'];
            $pdo->beginTransaction();
            $st = $pdo->prepare('SELECT customerName FROM tblcustomers WHERE ID = ? AND congregationId = ? AND deleted = 0 FOR UPDATE');
            $st->execute([$id,$cong]);
            $name = $st->fetchColumn();
            if ($name === false) { throw new LedgerException(['Customer not found for this congregation']); }
            // Any invoice reference blocks deletion, including an inconsistent legacy row carrying another congregation.
            $st = $pdo->prepare('SELECT COUNT(*) FROM tblinvoice_header WHERE customerId = ?');
            $st->execute([$id]);
            if ((int)$st->fetchColumn() > 0) { throw new LedgerException(['Cannot delete customer as they have invoices']); }
            $pdo->prepare('UPDATE tblcustomers SET deleted = 1 WHERE ID = ? AND congregationId = ?')->execute([$id,$cong]);
            saveLog($pdo,'Deleted customer ' . $name);
            $pdo->commit();
            return ['success' => true];
        } catch (LedgerException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage());
            return ['success' => false,'errors' => ['Unable to delete customer. Retry or contact admin']];
        }
    }

    private static function scalar($value): ?string
    {
        return is_scalar($value) || $value === null ? trim((string)$value) : null;
    }

    private static function blankToNull(string $value, bool $lower = false): ?string
    {
        if ($value === '') { return null; }
        return $lower ? strtolower($value) : $value;
    }
}
