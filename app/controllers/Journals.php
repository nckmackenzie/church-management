<?php 
class Journals extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else{
            $this->journalModel = $this->model('Journal');
        }
    }
    public function index()
    {
        $form = 'Journal Entry';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->journalModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $data= [];
        $this->view('journals/index',$data);
    }
    public function add()
    {
        $form = 'Journal Entry';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->journalModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $accounts = $this->journalModel->getAccounts();
        $journalno = $this->journalModel->journalNo();
        $data = [
            'accounts' => $accounts,
            'journalno' => $journalno
        ];
        $this->view('journals/add',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
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
}