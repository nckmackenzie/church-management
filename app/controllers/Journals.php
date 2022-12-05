<?php 
class Journals extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'journal entry');
        $this->journalModel = $this->model('Journal');
    }
    public function index()
    {
        $data= ['accounts' => $this->journalModel->getAccounts(),'date' => date('Y-m-d')];
        $this->view('journals/index',$data);
    }
    public function getjournalno()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $journalno = $this->journalModel->journalNo();
            echo json_encode(['success' => true,'journalno' => (int)$journalno]);
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }
    public function getfirstlastjournalno()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $type = isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : 'current';
            $journalno = $this->journalModel->getjournalno($type);
            echo json_encode(['success' => true,'journalno' => (int)$journalno]);
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'journal' => trim($_POST['journal']),
                'details' => $_POST['table_data']
            ];
            if ($this->journalModel->create($data)) {
                flash('journal_msg','Journal Entry Saved Successfully');
                redirect('journals');
            }
        }
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $fields = json_decode(file_get_contents('php://input')); //extract fields;
            $data = [
                'journalno' => converttobool($fields->isEdit) ? 
                               (isset($fields->journalNo) && !empty(trim($fields->journalNo)) ? (int)$fields->journalNo : null) 
                               : $this->journalModel->journalNo(),
                'isedit' => converttobool($fields->isEdit),
                'date' => isset($fields->date) && !empty(trim($fields->date)) ? date('Y-m-d',strtotime($fields->date)) : null,
                'entries' => $fields->entries
            ];
            //validation
            if(is_null($data['date']) || is_null($data['journalno'])){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required fields']);
                exit;
            }
            if(empty($data['entries'])){
                http_response_code(400);
                echo json_encode(['message' => 'No entries made']);
                exit;
            }
            //if error on create/update
            if(!$this->journalModel->createupdate($data)){
                http_response_code(500);
                echo json_encode(['message' => 'Unable to save entries. Retry or contact admin','success' => false]);
                exit;
            }

            echo json_encode(['message' => 'Saved successfully','success' => true]);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }
}