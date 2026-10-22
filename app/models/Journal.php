<?php
class Journal {
    private $db;
    public function __construct()
    {
        $this->db =  new Database;
    }
    public function CheckRights($form)
    {
        if (getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0) {
            return true;
        }else{
            return false;
        }
    }
    /**
     * Accounts offered in the journal dropdown: exactly the posting accounts for
     * this congregation (shared accounts and banks included, reporting accounts
     * excluded). The SQL is a pre-filter; Ledger decides.
     */
    public function getAccounts()
    {
        $cong = (int)$_SESSION['congId'];
        $this->db->query('SELECT ID,UCASE(accountType) as accountType
                          FROM tblaccounttypes
                          WHERE (deleted=0) AND (parentId <> 0) AND (active = 1) AND (isSubCategory = 1)
                                AND (congregationId IN (0,:cid))
                          ORDER BY accountType');
        $this->db->bind(':cid',$cong);
        $pdo = $this->db->pdo();
        return array_values(array_filter($this->db->resultSet(),function ($account) use ($pdo,$cong) {
            return Ledger::isPostingAccount($pdo,(int)$account->ID,$cong);
        }));
    }
    public function getAccountId($account)
    {
        $this->db->query('SELECT accountTypeId FROM tblaccounttypes WHERE (ID=:id)');
        $this->db->bind(':id',$account);
        return $this->db->getValue();
    }
    /** Next journal number, for DISPLAY on the form only (not reserved). */
    public function peekNumber()
    {
        return Ledger::peekNextJournalNo($this->db->pdo(),(int)$_SESSION['congId']);
    }

    /**
     * Create (new) or edit a journal. Returns
     * ['success' => bool, 'journalno' => ?int, 'errors' => string[]].
     * An empty errors list on failure means an unexpected error (logged).
     */
    public function createupdate($data)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();

            if($data['isedit']){
                $no = (int)$data['journalno'];
                if(is_null(Ledger::getEntry($pdo,$cong,5,$no))){
                    $pdo->rollBack();
                    return ['success' => false,'journalno' => $no,
                            'errors' => ['Journal no '.$no.' was not found for this congregation or has been deleted']];
                }
            }else{
                $no = Ledger::nextJournalNo($pdo,$cong);
            }

            $lines = [];
            $errors = [];
            foreach ($data['entries'] as $i => $entry) {
                $debit = !empty($entry->debit) ? trim($entry->debit) : '';
                $credit = !empty($entry->credit) ? trim($entry->credit) : '';
                $memo = !empty($entry->desc) ? trim($entry->desc) : 'Journal entries #'.$no;
                $accountid = (int)trim($entry->accountid);
                if(($debit !== '') === ($credit !== '')){
                    $errors[] = 'line '.($i + 1).': enter either a debit or a credit amount';
                    continue;
                }
                $lines[] = $debit !== '' ? Ledger::debit($accountid,$debit,$memo) : Ledger::credit($accountid,$credit,$memo);
            }
            if($errors){
                throw new LedgerException($errors);
            }

            if($data['isedit']){
                // a cleared (reconciled) bank row may stay, but not with another bank or amount
                $keep = [];
                foreach ($lines as $l) {
                    if((int)getdbvalue($pdo,'SELECT isBank FROM tblaccounttypes WHERE ID = ?',[$l['accountId']]) === 1){
                        $keep[] = ['bankId' => $l['accountId'],'amount' => $l['amount']];
                    }
                }
                if($problem = Ledger::clearedBankProblem($pdo,$cong,5,$no,$keep)){
                    throw new LedgerException([$problem]);
                }
            }

            Ledger::post($pdo,[
                'congregationId' => $cong,
                'entryDate' => $data['date'],
                'transactionTypeId' => 5,
                'transactionId' => $no,
                'reference' => null,
                'description' => null
            ],$lines);
            Ledger::syncBankPostings($pdo,$cong,5,$no,'journal');

            $narr = $data['isedit'] ? 'Made changes to journal no '.$no : 'Made entries for journal no '.$no;
            saveLog($pdo,$narr);

            $pdo->commit();
            return ['success' => true,'journalno' => $no,'errors' => []];
        } catch (LedgerException $e) {
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }
            return ['success' => false,'journalno' => null,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }
            error_log($e->getMessage(),0);
            return ['success' => false,'journalno' => null,'errors' => []];
        }
    }

    /** The active journal with its lines (account IDs), or null. */
    public function getjournal($journalno)
    {
        return Ledger::getEntry($this->db->pdo(),(int)$_SESSION['congId'],5,(int)$journalno);
    }

    /** Delete (void) a journal and its bank postings. Returns ['success' => bool, 'errors' => string[]]. */
    public function delete($id)
    {
        $pdo = $this->db->pdo();
        $cong = (int)$_SESSION['congId'];
        try {
            $pdo->beginTransaction();
            if($problem = Ledger::clearedBankProblem($pdo,$cong,5,(int)$id)){
                throw new LedgerException([$problem]);
            }
            if(!Ledger::void($pdo,$cong,5,(int)$id)){
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Journal no '.(int)$id.' was not found or is already deleted']];
            }
            Ledger::voidBankPostings($pdo,$cong,5,(int)$id);
            saveLog($pdo,'Deleted journal no '.(int)$id);
            $pdo->commit();
            return ['success' => true,'errors' => []];
        } catch (LedgerException $e) {
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }
            return ['success' => false,'errors' => $e->getErrors()];
        } catch (Throwable $e) {
            if($pdo->inTransaction()){
                $pdo->rollBack();
            }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }

    public function getaccount($account)
    {
        $count = getdbvalue($this->db->dbh,'SELECT COUNT(*) FROM tblaccounttypes WHERE LOWER(accountType) = ?',[$account]);
        if((int)$count === 0){
            return false;
        }
        return getdbvalue($this->db->dbh,'SELECT ID FROM tblaccounttypes WHERE LOWER(accountType) = ?',[$account]);
    }

    public function getJournalDetails($data)
    {
        if(!isset($data['id']) || empty(trim($data['id'])) || !is_numeric($data['id'])){
            return [];
        }
        if((int)$data['type'] === 2){
            // expenses: one shared method (posted entry, or a 'Not posted' preview of a draft); false = not found
            require_once APPROOT . '/models/Expense.php';
            return (new Expense)->getJournalDetails((int)$data['id']);
        }
        $sql = 'SELECT 
                    l.ID,
                    l.transactionDate,
                    l.account,
                    l.debit,
                    l.credit,
                    l.narration
                FROM `tblledger` l 
                WHERE (transactionId = ?) AND (transactionType = ?) AND (l.deleted = 0) AND (l.congregationId=?)';
        return loadresultset($this->db->dbh,$sql,[(int)$data['id'],$data['type'],(int)$_SESSION['congId']]);
    }
}