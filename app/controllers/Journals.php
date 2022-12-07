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
            echo json_encode(
                ['success' => true,
                'journalno' => (int)$journalno,
                'firstno' =>  $this->journalModel->getjournalno('first')]
            );
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }
    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $fields = json_decode(file_get_contents('php://input')); //extract fields;
            $data = [
                'journalno' => converttobool($fields->isEdit) ? 
                               (isset($fields->editId) && !empty(trim($fields->editId)) ? (int)$fields->editId : null) 
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
            if($data['isedit'] && is_null($data['journalno'])){
                http_response_code(400);
                echo json_encode(['message' => 'Unable to update. Please try again']);
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
    public function getjournalentry()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $journalno = isset($_GET['journalno']) && !empty(trim($_GET['journalno'])) ? trim(htmlentities($_GET['journalno'])) : null;
            //validation
            if(is_null($journalno)){
                http_response_code(400);
                echo json_encode(['message' => 'No journal no provided',"success" => false]);
                exit;
            }
            if(!is_numeric($journalno)){
                http_response_code(400);
                echo json_encode(['message' => 'Journal Number has to be numeric',"success" => false]);
                exit;
            }
            //check if journal exists
            if(!$this->journalModel->checkexists($journalno)){
                http_response_code(404);
                echo json_encode(['message' => 'Journal Number not found for this congregation',"success" => false]);
                exit;
            }

            $entries = $this->journalModel->getjournal($journalno);
            $date = date('Y-m-d',strtotime($entries[0]->transactionDate));
            $data = [];
            $totaldebits = 0;
            $totalcredits = 0;
            foreach($entries as $entry){
                $totalcredits += floatval($entry->credit);
                $totaldebits += floatval($entry->debit);
                array_push($data,[
                    'accountid' => (int)$entry->ID,
                    'accountname' => ucwords(trim($entry->account)),
                    'debit' => floatval($entry->debit)  === 0 ? '' : floatval($entry->debit),
                    'credit' => floatval($entry->credit)  === 0 ? '' : floatval($entry->credit),
                    'narration' => ucwords($entry->narration)
                ]);
            }
            echo json_encode(['success' => true,'journalDate' => $date,'entries' => $data,"totals" => [$totaldebits,$totalcredits]]);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }
    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $id = isset($_POST['id']) && !empty(trim($_POST['id'])) ? (int)trim(htmlentities($_POST['id'])) : null;
            if(is_null($id)){
                flash('journal_msg','Unable to get selected journal',alerterrorclass());
                redirect('journals');
                exit;
            }
            if(!$this->journalModel->delete($id)){
                flash('journal_msg','Unable to delete selected journal. Please try again or contact admin!',alerterrorclass());
                redirect('journals');
                exit;
            }
            flash('journal_msg','Deleted successfully!');
            redirect('journals');
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }
}