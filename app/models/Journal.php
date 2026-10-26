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
        $this->db->query('SELECT a.ID,UCASE(a.accountType) as accountType,p.accountType AS parentName
                          FROM tblaccounttypes a
                          LEFT JOIN tblaccounttypes p ON p.ID=a.parentId
                          WHERE (a.deleted=0) AND (a.parentId <> 0) AND (a.active = 1) AND (a.isSubCategory = 1)
                                AND (a.congregationId IN (0,:cid))
                          ORDER BY a.accountType,a.ID');
        $this->db->bind(':cid',$cong);
        $pdo = $this->db->pdo();
        $accounts = array_values(array_filter($this->db->resultSet(),function ($account) use ($pdo,$cong) {
            return Ledger::isPostingAccount($pdo,(int)$account->ID,$cong);
        }));
        $counts=[]; foreach($accounts as $a){$k=$this->normaliseAccountName($a->accountType);$counts[$k]=($counts[$k]??0)+1;}
        foreach($accounts as $a){$a->label=$a->accountType.($counts[$this->normaliseAccountName($a->accountType)]>1?' ('.$a->parentName.')':'');}
        return $accounts;
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
            $allowed = [];
            foreach ($this->getAccounts() as $a) { $allowed[(int)$a->ID] = true; }
            if ($data['isedit']) {
                $old = Ledger::getEntry($pdo,$cong,5,$no);
                foreach (($old['lines'] ?? []) as $line) { $allowed[(int)$line['accountId']] = true; }
            }
            foreach ($data['entries'] as $i => $entry) {
                $debit = isset($entry->debit) ? trim((string)$entry->debit) : '';
                $credit = isset($entry->credit) ? trim((string)$entry->credit) : '';
                $memo = isset($entry->desc) && trim((string)$entry->desc) !== '' ? trim((string)$entry->desc) : 'Journal entries #'.$no;
                $accountid = isset($entry->accountid) ? (int)trim((string)$entry->accountid) : 0;
                if(mb_strlen($memo,'UTF-8') > 500){
                    // journal_lines.memo is VARCHAR(500); the connection is not in strict mode, so a longer text would be cut silently
                    $errors[] = 'line '.($i + 1).': the narration is longer than 500 characters';
                    continue;
                }
                if(!isset($allowed[$accountid])){
                    $errors[] = 'line '.($i + 1).': choose an account from the journal account list';
                    continue;
                }
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

    /** Validate parsed import rows without writing anything. */
    public function validateImportRows(array $rows, ?string $formDate = null): array
    {
        if (count($rows) > JournalImport::MAX_ROWS) { throw new LengthException('The file has more than '.JournalImport::MAX_ROWS.' data rows'); }
        $cong = (int)$_SESSION['congId']; $pdo = $this->db->pdo();
        $accounts = $this->getAccounts(); $matches = []; $options = []; $nameCounts = [];
        foreach ($accounts as $a) { $nameCounts[$this->normaliseAccountName($a->accountType)] = ($nameCounts[$this->normaliseAccountName($a->accountType)] ?? 0) + 1; }
        foreach ($accounts as $a) {
            $key = $this->normaliseAccountName($a->accountType);
            $label = ucwords(strtolower((string)$a->accountType));
            if ($nameCounts[$key] > 1) { $label .= ' (' . ucwords(strtolower((string)$a->parentName)) . ')'; }
            $item = ['id'=>(int)$a->ID,'name'=>(string)$a->accountType,'parent'=>(string)$a->parentName,'label'=>$label];
            $matches[$key][] = $item; $options[] = $item;
        }
        $all = $pdo->query('SELECT a.*,p.parentId AS grandParentId,p.deleted AS parentDeleted,p.accountType AS parentName,
                                  (SELECT COUNT(*) FROM tblaccounttypes c WHERE c.parentId=a.ID AND c.deleted=0) childCount
                           FROM tblaccounttypes a LEFT JOIN tblaccounttypes p ON p.ID=a.parentId')->fetchAll(PDO::FETCH_ASSOC);
        $rawByName = []; foreach ($all as $a) { $rawByName[$this->normaliseAccountName($a['accountType'])][] = $a; }

        $validated = []; $debitCents = 0; $creditCents = 0; $journalDate = null; $invalid = 0;
        foreach ($rows as $offset => $entry) {
            $value = function($key) use ($entry) { return trim((string)($entry[$key] ?? '')); };
            if ($value('date')==='' && $value('account')==='' && $value('debit')==='' && $value('credit')==='' && $value('description')==='') { continue; }
            $number = (int)($entry['_row'] ?? ($offset + 2)); $reasons = []; $accountReason = ''; $accountId = null;
            $account = $value('account'); $key = $this->normaliseAccountName($account); $found = $matches[$key] ?? [];
            if (count($found) === 1) { $accountId = $found[0]['id']; }
            elseif (count($found) > 1) {
                $labels = array_map(function($a){ return $a['name'].' ('.$a['parent'].')'; },$found);
                $accountReason = "Account '$account' matches ".count($found).' accounts; choose the right one: '.implode(', ',$labels);
                $reasons[] = $accountReason;
            } else { $accountReason = $this->unavailableAccountReason($account,$rawByName[$key] ?? [],$cong); $reasons[] = $accountReason; }

            $rawDate = $value('date') !== '' ? $value('date') : trim((string)$formDate);
            $date = $this->importDate($rawDate);
            if ($date === null) { $reasons[] = "Date '$rawDate' is invalid; use YYYY-MM-DD, DD/MM/YYYY or DD-MM-YYYY"; }
            else {
                if (($problem = Ledger::dateProblem($pdo,$date)) !== null) { $reasons[] = $problem; }
                if ($journalDate === null) { $journalDate = $date; }
                elseif ($date !== $journalDate) { $reasons[] = "Date $date differs from the journal date $journalDate"; }
            }

            $debit=$value('debit'); $credit=$value('credit');
            if (($debit !== '') === ($credit !== '')) { $reasons[] = 'Enter exactly one of debit or credit'; }
            else {
                $side = $debit !== '' ? 'Debit' : 'Credit'; $amount = $debit !== '' ? $debit : $credit;
                if (($problem = Ledger::amountError($amount,$side)) !== null) { $reasons[] = $problem; }
                else { $cents=Ledger::exactCents($amount); if($debit!==''){$debitCents+=$cents;}else{$creditCents+=$cents;} }
            }
            if ($reasons) { $invalid++; }
            $otherReasons=array_values(array_filter($reasons,function($r)use($accountReason){return $r!==$accountReason;}));
            $validated[] = ['row'=>$number,'date'=>$date ?? $rawDate,'accountId'=>$accountId,'account'=>$account,
                            'debit'=>$debit,'credit'=>$credit,'narration'=>$value('description'),
                            'reason'=>implode('; ',$reasons),'accountReason'=>$accountReason,'otherReason'=>implode('; ',$otherReasons),'valid'=>!$reasons];
        }
        return ['entries'=>$validated,'accounts'=>$options,'journalDate'=>$journalDate,'invalid'=>$invalid,
                'totals'=>['debit'=>number_format($debitCents/100,2,'.',''),'credit'=>number_format($creditCents/100,2,'.',''),
                          'difference'=>number_format(($debitCents-$creditCents)/100,2,'.',''),'balanced'=>$debitCents===$creditCents]];
    }

    private function normaliseAccountName($name): string
    {
        return mb_strtolower(preg_replace('/\s+/u',' ',trim((string)$name)),'UTF-8');
    }

    private function unavailableAccountReason(string $name,array $rows,int $cong): string
    {
        if (!$rows) { return "Account '$name' is not in the list of accounts you can post to"; }
        $reasons=[];
        foreach($rows as $a){
            if(!in_array((int)$a['congregationId'],[0,$cong],true)){$reasons[]='not available to your congregation';continue;}
            if((int)$a['deleted']===1){$reasons[]='deleted';continue;}
            if((int)$a['parentId']===0){$reasons[]='a root account';continue;}
            if((int)$a['grandParentId']===0){$reasons[]='a top-level reporting account';continue;}
            if((int)$a['childCount']>0){$reasons[]='a reporting account with sub-accounts';continue;}
            if((int)$a['active']!==1){$reasons[]='inactive';continue;}
            $reasons[]='not in the journal account list';
        }
        return "Account '$name' is ".implode(' / ',array_values(array_unique($reasons)));
    }

    private function importDate(string $value): ?string
    {
        if($value===''){return null;}
        if(is_numeric($value)){
            $n=(int)floor((float)$value); if($n>0&&$n<100000){$d=new DateTime('1899-12-30');$d->modify('+'.$n.' days');return $d->format('Y-m-d');}
        }
        foreach(['!Y-m-d','!d/m/Y','!d-m-Y'] as $format){$d=DateTime::createFromFormat($format,$value);if($d&&$d->format(substr($format,1))===$value){return $d->format('Y-m-d');}}
        return null;
    }

    public function getJournalDetails($data)
    {
        if(!isset($data['id']) || empty(trim($data['id'])) || !is_numeric($data['id'])){
            return [];
        }
        if((int)$data['type'] !== 2){
            return null;
        }
        require_once APPROOT . '/models/Expense.php';
        return (new Expense)->getJournalDetails((int)$data['id']);
    }
}
