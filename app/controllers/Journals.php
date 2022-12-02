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
        $data= ['accounts' => $this->journalModel->getAccounts()];
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
}