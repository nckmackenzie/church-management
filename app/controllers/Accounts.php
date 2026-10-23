<?php
class Accounts extends Controller{
    private $authmodel;
    private $accountModel;
    private $congregationModel;
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'g/l accounts');
        $this->accountModel = $this->model('Account');
        $this->congregationModel = $this->model('Congregation');
    }

    private static function esc($v)
    {
        return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    }

    private function congregationName()
    {
        $c = $this->congregationModel->getCongregation($_SESSION['congId']);
        return $c ? strtoupper($c->CongregationName) : '';
    }

    /** Errors of a model call as one flash line (each message escaped: flash() prints raw HTML). */
    private function flashErrors($errors,$default)
    {
        $errors = array_values((array)$errors);
        $msg = $errors ? implode('<br>',array_map([self::class,'esc'],$errors)) : $default;
        flash('account_msg',$msg,'alert custom-danger alert-dismissible fade show');
    }

    private function json($code,$payload)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    public function index()
    {
        $accounts = $this->accountModel->index();
        $data = ['accounts' => $accounts];
        $this->view('accounts/index',$data);
    }

    public function add()
    {
        $s = Account::session();
        $data = [
            'id' => '',
            'accountname' => '',
            'congregationName' => $this->congregationName(),
            'accounttypes' => $this->accountModel->getAccountTypes(),
            'root' => '',
            'parents' => $this->accountModel->getParents($s['congId']),
            'parent' => '',
            'description' => '',
            'congregation' => $s['congId'],
            'mayshare' => AccountChart::mayManageShared($s),
            'forgroup' => false,
            'errors' => []
        ];
        $this->view('accounts/add',$data);
    }

    /**
     * AJAX: the parents an account may have (decisions 67 / 68), as JSON [{id,label,forgroup}].
     * POST: congregation (0 = shared, or the session congregation), root (optional filter), exclude (account being edited).
     */
    public function getparents()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(405,['success' => false,'message' => 'POST only']);
        }
        $s = Account::session();
        $cong = isset($_POST['congregation']) && $_POST['congregation'] !== '' ? $_POST['congregation'] : (string)$s['congId'];
        $root = isset($_POST['root']) && $_POST['root'] !== '' ? $_POST['root'] : null;
        $exclude = isset($_POST['exclude']) && $_POST['exclude'] !== '' ? $_POST['exclude'] : null;
        foreach ([$cong,$root,$exclude] as $v) {
            if ($v !== null && !ctype_digit((string)$v)) {
                $this->json(400,['success' => false,'message' => 'Invalid input']);
            }
        }
        $cong = (int)$cong;
        if ($cong !== $s['congId'] && !($cong === 0 && AccountChart::mayManageShared($s))) {
            $this->json(403,['success' => false,'message' => 'Access denied']);
        }
        if ($exclude !== null) {
            $a = $this->accountModel->getAccount($exclude);
            if (!$a || AccountChart::manageProblem((array)$a,$s) !== null) {
                $this->json(403,['success' => false,'message' => 'Access denied']);
            }
            $cong = (int)$a->congregationId;   // an existing account keeps its congregation
        }
        $out = [];
        foreach ($this->accountModel->getParents($cong,$exclude === null ? null : (int)$exclude,$root === null ? null : (int)$root) as $p) {
            $out[] = ['id' => $p['ID'],'label' => strtoupper($p['path']),'forgroup' => $p['forGroup']];
        }
        $this->json(200,$out);
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('accounts/add');
            return;
        }
        $s = Account::session();
        $post = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW) ?: [];
        $parent = isset($post['parent']) ? trim((string)$post['parent']) : '';
        $data = [
            'accountname' => isset($post['accountname']) ? trim((string)$post['accountname']) : '',
            'parent' => $parent,
            'description' => isset($post['description']) ? trim((string)$post['description']) : '',
            'forgroup' => isset($post['forgroup']) ? 1 : 0,
            'congregation' => Account::createCongregation($post['congregation'] ?? null,$s)
        ];
        if ($parent !== '' && !ctype_digit($parent)) {
            $result = ['success' => false,'errors' => ['parent' => 'Select a valid parent account']];
        } else {
            $data['parent'] = (int)$parent;
            $result = $this->accountModel->create($data);
        }
        if ($result['success']) {
            flash('account_msg','Account Created Successfully');
            redirect('accounts');
            return;
        }
        $root = isset($post['root']) && ctype_digit((string)$post['root']) ? (int)$post['root'] : null;
        $view = [
            'id' => '',
            'accountname' => $data['accountname'],
            'congregationName' => $this->congregationName(),
            'accounttypes' => $this->accountModel->getAccountTypes(),
            'root' => $root,
            'parents' => $this->accountModel->getParents($data['congregation'],null,$root),
            'parent' => $parent,
            'description' => $data['description'],
            'congregation' => $data['congregation'],
            'mayshare' => AccountChart::mayManageShared($s),
            'forgroup' => $data['forgroup'] === 1,
            'errors' => $result['errors'] ?: ['general' => 'Something went wrong saving the account. Contact admin']
        ];
        $this->view('accounts/add',$view);
    }

    /** The edit form for an account the user may edit; otherwise a flash message and the list. */
    public function edit($id = '')
    {
        $a = $this->accountModel->getAccount($id);
        $s = Account::session();
        $why = null;
        if (!$a) { $why = 'Account not found'; }
        elseif ((int)$a->deleted === 1) { $why = 'This account is deleted'; }
        elseif (($w = AccountChart::manageProblem((array)$a,$s)) !== null) { $why = $w; }
        elseif ((int)$a->isBank === 1) { $why = 'Banks are managed in the Banks module'; }
        elseif ((int)$a->isEditable !== 1) { $why = 'This account cannot be edited'; }
        if ($why !== null) {
            flash('account_msg',self::esc($why),'alert custom-danger alert-dismissible fade show');
            redirect('accounts');
            return;
        }
        $this->renderEdit($a,[
            'accountname' => ucwords($a->accountType),
            'parent' => (int)$a->parentId,
            'description' => ($a->description === null || $a->description === '0') ? '' : $a->description,
            'forgroup' => (int)$a->forGroup === 1,
            'active' => (int)$a->active === 1
        ],[]);
    }

    private function renderEdit($a,$values,$errors)
    {
        [$parents,$parentPath] = $this->accountModel->editParents($a);
        $data = [
            'id' => (int)$a->ID,
            'accountname' => $values['accountname'],
            'storedname' => $a->accountType,
            'parents' => $parents,
            'parent' => $values['parent'],
            'parentpath' => $parentPath,
            'description' => $values['description'],
            'congregation' => (int)$a->congregationId,
            'congregationName' => (int)$a->congregationId === 0 ? 'ALL (SHARED)' : $this->congregationName(),
            'forgroup' => $values['forgroup'],
            'active' => $values['active'],
            'protected' => AccountChart::isProtected((array)$a),
            'isroot' => (int)$a->parentId === 0,
            'errors' => $errors
        ];
        $this->view('accounts/edit',$data);
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('accounts');
            return;
        }
        $post = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW) ?: [];
        $id = isset($post['id']) ? trim((string)$post['id']) : '';
        $parent = isset($post['parent']) && trim((string)$post['parent']) !== '' ? trim((string)$post['parent']) : null;
        $data = [
            'id' => $id,
            'accountname' => isset($post['accountname']) ? trim((string)$post['accountname']) : '',
            'parent' => $parent,
            'description' => isset($post['description']) ? trim((string)$post['description']) : '',
            'forgroup' => isset($post['forgroup']) ? 1 : 0,
            'active' => isset($post['active']) ? 1 : 0
        ];
        if ($parent !== null && !ctype_digit($parent)) {
            $result = ['success' => false,'errors' => ['parent' => 'Select a valid parent account'],'warnings' => []];
        } else {
            $data['parent'] = $parent === null ? null : (int)$parent;
            $result = $this->accountModel->update($data);
        }
        if ($result['success']) {
            flash('account_msg','Account Updated Successfully');
            if (!empty($result['warnings'])) {
                // flash() keeps one message: append the warning to it
                unset($_SESSION['account_msg'],$_SESSION['account_msg_class']);
                flash('account_msg','Account Updated Successfully<br>'.implode('<br>',array_map([self::class,'esc'],$result['warnings'])),
                      'alert alert-warning alert-dismissible fade show');
            }
            redirect('accounts');
            return;
        }
        $a = $this->accountModel->getAccount($id);
        if (!$a || isset($result['errors']['general'])) {
            $this->flashErrors($result['errors'],'Something went wrong updating the account. Contact admin');
            redirect('accounts');
            return;
        }
        $this->renderEdit($a,[
            'accountname' => $data['accountname'],
            'parent' => $parent === null ? (int)$a->parentId : $parent,
            'description' => $data['description'],
            'forgroup' => $data['forgroup'] === 1,
            'active' => $data['active'] === 1
        ],$result['errors'] ?: ['general' => 'Something went wrong updating the account. Contact admin']);
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('accounts');
            return;
        }
        $id = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
        if($id === ''){
            flash('account_msg','Unable to get selected account','alert custom-danger alert-dismissible fade show');
            redirect('accounts');
            return;
        }
        $result = $this->accountModel->delete($id);
        if(!$result['success'])
        {
            $this->flashErrors($result['errors'],'Something went wrong deleting the account. Contact admin');
            redirect('accounts');
            return;
        }
        flash('account_msg','Deleted successfully!');
        redirect('accounts');
    }
}
