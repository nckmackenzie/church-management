<?php
/**
 * Chart of accounts screens (task 8.10). The rules live in AccountChart (decisions 65-75 of docs/ledger_inventory.md);
 * every write here runs in ONE transaction together with the isSubCategory recompute and the log entry.
 * create() / update() / delete() return ['success' => bool, 'errors' => string[], ...]; empty errors on failure = unexpected error (logged).
 */
class Account {
    private $db;
    public function __construct()
    {
        $this->db = new Database;
    }
    public function CheckRights($form)
    {
        if (getUserAccess($this->db->dbh,$_SESSION['userId'],$form,$_SESSION['isParish']) > 0) {
            return true;
        }else{
            return false;
        }
    }

    /** The session fields the chart rules look at. */
    public static function session()
    {
        return [
            'userId' => (int)($_SESSION['userId'] ?? 0),
            'userType' => (int)($_SESSION['userType'] ?? 9),
            'isParish' => converttobool($_SESSION['isParish'] ?? 0),
            'congId' => (int)($_SESSION['congId'] ?? 0)
        ];
    }

    /**
     * The whole tree the user can see (shared + own congregation), any depth, from ONE query, depth first, children by name.
     * Each row: ID, name, depth, typeName (root), isBank, active, congregationId, isEditable, canEdit, canDelete, note.
     */
    public function index()
    {
        $s = self::session();
        $this->db->query('SELECT ID,accountType,parentId,isBank,isEditable,active,congregationId,forGroup
                          FROM   tblaccounttypes
                          WHERE  (deleted = 0) AND (congregationId = 0 OR congregationId = :cong)');
        $this->db->bind(':cong',$s['congId']);
        $rows = [];
        $kids = [];
        foreach ($this->db->resultSet() as $r) {
            $rows[(int)$r->ID] = $r;
        }
        foreach ($rows as $id => $r) {
            $p = (int)$r->parentId;
            $kids[($p === 0 || isset($rows[$p])) ? $p : -1][] = $id;   // -1: parent not visible (listed last, never lost)
        }
        $byName = function ($a,$b) use ($rows) {
            return strcmp($rows[$a]->accountType,$rows[$b]->accountType);
        };
        foreach ($kids as &$list) { usort($list,$byName); }
        unset($list);

        $out = [];
        $walk = function ($id,$depth,$typeName) use (&$walk,&$out,$rows,$kids,$s) {
            $r = $rows[$id];
            if ($depth === 1) { $typeName = $r->accountType; }
            $out[] = $this->describe($r,$depth,$typeName,$s);
            foreach ($kids[$id] ?? [] as $c) {
                if ($depth < AccountChart::MAX_DEPTH) { $walk($c,$depth + 1,$typeName); }
            }
        };
        foreach ($kids[0] ?? [] as $root) { $walk($root,1,''); }
        foreach ($kids[-1] ?? [] as $orphan) { $walk($orphan,2,'?'); }
        return $out;
    }

    private function describe($r,$depth,$typeName,$s)
    {
        $row = (object)[
            'ID' => (int)$r->ID,
            'accountType' => $r->accountType,
            'depth' => $depth,
            'typeName' => $typeName,
            'isBank' => (int)$r->isBank === 1,
            'active' => (int)$r->active === 1,
            'congregationId' => (int)$r->congregationId,
            'isEditable' => (int)$r->isEditable === 1,
            'canEdit' => false,
            'canDelete' => false,
            'note' => ''
        ];
        $acct = ['ID' => $row->ID,'isBank' => $row->isBank ? 1 : 0,'congregationId' => $row->congregationId];
        if ($row->isBank) {
            $row->note = 'Managed in Banks';
        }
        elseif ($depth === 1) {
            $row->note = 'Account type';
        }
        elseif (AccountChart::manageProblem($acct,$s) !== null) {
            $row->note = (int)$r->congregationId === 0 ? 'Shared: changed by the parish' : '';
        }
        elseif (!$row->isEditable) {
            $row->note = 'Not editable';
        }
        else {
            $row->canEdit = true;
            $row->canDelete = !AccountChart::isProtected($acct);
            if (!$row->canDelete) { $row->note = 'Protected account'; }
        }
        return $row;
    }

    public function getAccountTypes()
    {
        $this->db->query('SELECT ID,UCASE(accountType) as accountType
                          FROM   tblaccounttypes
                          WHERE  (parentId=0) AND (deleted=0)
                          ORDER BY ID');
        return $this->db->resultSet();
    }

    public function getAccount($id)
    {
        $id = (string)$id;
        if (!ctype_digit($id) || (int)$id <= 0) { return null; }
        $a = AccountChart::load($this->db->dbh,(int)$id);
        return $a ? (object)$a : null;
    }

    /** Allowed parents for the picker (decisions 67 / 68). See AccountChart::allowedParents(). */
    public function getParents($congregation,$selfId = null,$root = null)
    {
        return AccountChart::allowedParents($this->db->dbh,(int)$congregation,$selfId,$root);
    }


    /**
     * Parent options for the edit form: the allowed new parents plus the CURRENT parent (always listed, even when it would not be a
     * valid new parent because it has postings), and the current parent's path. A root has no parent: [[], ''].
     * @return array [parents, parentPath]
     */
    public function editParents($a)
    {
        if ((int)$a->parentId === 0) { return [[],'']; }
        $pdo = $this->db->pdo();
        $parents = $this->getParents((int)$a->congregationId,(int)$a->ID);
        $names = [];
        $cur = AccountChart::load($pdo,(int)$a->parentId);
        $current = $cur;
        for ($i = 0; $cur && $i < AccountChart::MAX_DEPTH; $i++) {
            array_unshift($names,$cur['accountType']);
            $cur = (int)$cur['parentId'] === 0 ? null : AccountChart::load($pdo,(int)$cur['parentId']);
        }
        $path = implode(' > ',$names);
        if ($current && !in_array((int)$a->parentId,array_column($parents,'ID'),true)) {
            array_unshift($parents,['ID' => (int)$a->parentId,'path' => $path,'forGroup' => (int)$current['forGroup'],'rootId' => 0]);
        }
        return [$parents,$path];
    }
    /** The congregation a new account gets: a parish login or user type 1 / 2 may choose shared (0) or own, everyone else gets their own. */
    public static function createCongregation($posted,$s)
    {
        if (AccountChart::mayManageShared($s) && $posted !== null && (string)$posted === '0') {
            return 0;
        }
        return $s['congId'];
    }

    private static function description($v)
    {
        $v = strtolower(trim((string)$v));
        return ($v === '' || $v === '0') ? null : $v;
    }

    /** $data: accountname, parent (ID), description, forgroup (0/1), congregation (0 or the session congregation). */
    public function create($data)
    {
        $pdo = $this->db->pdo();
        $s = self::session();
        $errors = [];
        $cong = (int)$data['congregation'];
        $parent = (int)$data['parent'];
        $name = strtolower(trim($data['accountname']));
        try {
            $pdo->beginTransaction();
            if ($cong !== $s['congId'] && !($cong === 0 && AccountChart::mayManageShared($s))) {
                $errors['congregation'] = 'You cannot create an account for that congregation';
            }
            if ($parent <= 0) {
                $errors['parent'] = 'Select the account this one is a sub category of';
            }
            else {
                AccountChart::load($pdo,$parent,true);   // lock the parent: nothing posts or changes under it meanwhile
                $why = AccountChart::parentProblem($pdo,$parent,$cong);
                if ($why !== null) { $errors['parent'] = $why; }
            }
            $why = AccountChart::nameProblem($pdo,$name,$cong);
            if ($why !== null) { $errors['name'] = $why; }
            if ($errors) {
                $pdo->rollBack();
                return ['success' => false,'errors' => $errors];
            }

            $root = AccountChart::rootOf($pdo,$parent);
            $st = $pdo->prepare('INSERT INTO tblaccounttypes (accountType,parentId,accountTypeId,isSubCategory,`description`,forGroup,congregationId)
                                 VALUES (?,?,?,0,?,?,?)');
            $st->execute([$name,$parent,$root,self::description($data['description']),(int)$data['forgroup'] === 1 ? 1 : 0,$cong]);
            $id = (int)$pdo->lastInsertId();
            AccountChart::recomputeFlags($pdo,[$id,$parent]);
            saveLog($pdo,'Created Account '.$name);
            $pdo->commit();
            Ledger::clearCashCache();
            return ['success' => true,'errors' => [],'id' => $id];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }

    /**
     * $data: id, accountname, parent (ID, or null = unchanged), description, forgroup, active.
     * Returns success, errors (field => message), warnings (string[]).
     */
    public function update($data)
    {
        $pdo = $this->db->pdo();
        $s = self::session();
        $errors = [];
        $warnings = [];
        try {
            $pdo->beginTransaction();
            $idRaw = (string)$data['id'];
            $a = ctype_digit($idRaw) && (int)$idRaw > 0 ? AccountChart::load($pdo,(int)$idRaw,true) : null;
            if (!$a) {
                $pdo->rollBack();
                return ['success' => false,'errors' => ['general' => 'Account not found'],'warnings' => []];
            }
            $id = (int)$a['ID'];
            $why = (int)$a['deleted'] === 1 ? 'This account is deleted' : AccountChart::manageProblem($a,$s);
            if ($why === null && (int)$a['isBank'] === 1) { $why = 'Banks are managed in the Banks module'; }
            if ($why === null && (int)$a['isEditable'] !== 1) { $why = 'This account cannot be edited'; }
            if ($why !== null) {
                $pdo->rollBack();
                return ['success' => false,'errors' => ['general' => $why],'warnings' => []];
            }

            $protected = AccountChart::isProtected($a);
            $oldName = trim(strtolower($a['accountType']));
            $name = strtolower(trim($data['accountname']));
            $renamed = $name !== $oldName;
            $oldParent = (int)$a['parentId'];
            $parent = $data['parent'] === null ? $oldParent : (int)$data['parent'];
            $moved = $parent !== $oldParent;
            $active = (int)$data['active'] === 1 ? 1 : 0;

            if ($protected && $renamed) { $errors['name'] = "'".$a['accountType']."' is a protected account and cannot be renamed"; }
            elseif ($renamed) {
                $w = AccountChart::nameProblem($pdo,$name,(int)$a['congregationId'],$id);
                if ($w !== null) { $errors['name'] = $w; }
            }
            if ($protected && $moved) { $errors['parent'] = "'".$a['accountType']."' is a protected account and cannot be moved"; }
            elseif ($moved) {
                if ($parent <= 0) { $errors['parent'] = 'Select the account this one is a sub category of'; }
                else {
                    AccountChart::load($pdo,$parent,true);
                    $w = AccountChart::moveProblem($pdo,$id,$parent);
                    if ($w !== null) { $errors['parent'] = $w; }
                }
            }
            if ($protected && $active !== (int)$a['active']) { $errors['active'] = "'".$a['accountType']."' is a protected account and cannot be deactivated"; }
            if ($errors) {
                $pdo->rollBack();
                return ['success' => false,'errors' => $errors,'warnings' => []];
            }

            $newRoot = $a['accountTypeId'] === null ? null : (int)$a['accountTypeId'];   // a root keeps NULL
            $touched = [$id,$oldParent,$parent];
            if ($moved) {
                $newRoot = AccountChart::rootOf($pdo,$parent);
                $subtree = AccountChart::subtreeIds($pdo,$id);
                $touched = array_merge($touched,$subtree);
                if ($newRoot !== AccountChart::rootOf($pdo,$oldParent)) {
                    $in = implode(',',array_fill(0,count($subtree),'?'));
                    $pdo->prepare("UPDATE tblaccounttypes SET accountTypeId = ? WHERE ID IN ($in)")->execute(array_merge([$newRoot],$subtree));
                }
            }
            $pdo->prepare('UPDATE tblaccounttypes SET accountType = ?, parentId = ?, accountTypeId = ?, `description` = ?, forGroup = ?, active = ?
                           WHERE ID = ?')
                ->execute([$name,$parent,$newRoot,self::description($data['description']),(int)$data['forgroup'] === 1 ? 1 : 0,$active,$id]);

            if ($renamed && defined('LEDGER_LEGACY_WRITE') && LEDGER_LEGACY_WRITE === true) {
                // dev mirror only (old reports still match tblledger text until task 9): keyed on the STORED old name and the account's scope
                foreach (['account','parentaccount'] as $col) {
                    $sql = "UPDATE tblledger SET $col = ? WHERE TRIM(LOWER($col)) = ?" . ((int)$a['congregationId'] === 0 ? '' : ' AND congregationId = ?');
                    $args = [$name,$oldName];
                    if ((int)$a['congregationId'] !== 0) { $args[] = (int)$a['congregationId']; }
                    $pdo->prepare($sql)->execute($args);
                }
            }
            AccountChart::recomputeFlags($pdo,$touched);

            if ($active === 0 && (int)$a['active'] === 1) {
                $st = $pdo->prepare('SELECT accountType FROM tblaccounttypes WHERE parentId = ? AND deleted = 0 AND active = 1 ORDER BY accountType');
                $st->execute([$id]);
                $kids = $st->fetchAll(PDO::FETCH_COLUMN);
                if ($kids) {
                    $warnings[] = "'".$a['accountType']."' was deactivated but still has active sub accounts: ".implode(', ',array_slice($kids,0,10)).(count($kids) > 10 ? ', ...' : '');
                }
            }
            $act = 'Updated Account '.$oldName.($renamed ? ' (renamed to '.$name.')' : '').($moved ? ' (moved under account '.$parent.')' : '');
            saveLog($pdo,$act);
            $pdo->commit();
            Ledger::clearCashCache();
            return ['success' => true,'errors' => [],'warnings' => $warnings];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => [],'warnings' => []];
        }
    }

    /** Soft delete (decision 70). errors = every reason it cannot be deleted. */
    public function delete($id)
    {
        $pdo = $this->db->pdo();
        $s = self::session();
        try {
            $pdo->beginTransaction();
            $idRaw = (string)$id;
            $a = ctype_digit($idRaw) && (int)$idRaw > 0 ? AccountChart::load($pdo,(int)$idRaw,true) : null;
            if (!$a) {
                $pdo->rollBack();
                return ['success' => false,'errors' => ['Account not found']];
            }
            $why = AccountChart::manageProblem($a,$s);
            if ($why !== null) {
                $pdo->rollBack();
                return ['success' => false,'errors' => [$why]];
            }
            $reasons = AccountChart::deleteReasons($pdo,(int)$a['ID']);
            if ($reasons) {
                $pdo->rollBack();
                return ['success' => false,'errors' => $reasons];
            }
            $pdo->prepare('UPDATE tblaccounttypes SET deleted = 1 WHERE ID = ?')->execute([(int)$a['ID']]);
            AccountChart::recomputeFlags($pdo,[(int)$a['parentId']]);
            saveLog($pdo,'Deleted Account '.$a['accountType']);
            $pdo->commit();
            Ledger::clearCashCache();
            return ['success' => true,'errors' => []];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log($e->getMessage(),0);
            return ['success' => false,'errors' => []];
        }
    }
}
