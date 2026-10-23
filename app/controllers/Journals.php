<?php 
class Journals extends Controller{
    private $journalModel;
    private $authmodel;
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
            $journalno = $this->journalModel->peekNumber();
            echo json_encode(
                ['success' => true,
                'journalno' => (int)$journalno]
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
                // new journals get their number from Ledger inside the save transaction
                'journalno' => converttobool($fields->isEdit) ? 
                               (isset($fields->editId) && !empty(trim($fields->editId)) ? (int)$fields->editId : null) 
                               : 0,
                'isedit' => converttobool($fields->isEdit),
                'date' => isset($fields->date) && !empty(trim($fields->date)) ? date('Y-m-d',strtotime($fields->date)) : null,
                'entries' => $fields->entries
            ];
            //validation
            if(is_null($data['date']) || ($data['isedit'] && is_null($data['journalno']))){
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
            $result = $this->journalModel->createupdate($data);
            if(!$result['success']){
                if($result['errors']){
                    http_response_code(422);
                    echo json_encode(['message' => implode('<br>',array_map('htmlspecialchars',$result['errors'])),'success' => false]);
                    exit;
                }
                http_response_code(500);
                echo json_encode(['message' => 'Unable to save entries. Retry or contact admin','success' => false]);
                exit;
            }

            echo json_encode(['message' => 'Journal no '.$result['journalno'].' saved successfully','success' => true,
                              'journalno' => $result['journalno']]);
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
            $journal = $this->journalModel->getjournal($journalno);
            if(is_null($journal)){
                http_response_code(404);
                echo json_encode(['message' => 'Journal Number not found for this congregation',"success" => false]);
                exit;
            }

            $date = date('Y-m-d',strtotime($journal['entryDate']));
            $data = [];
            $totaldebits = 0;
            $totalcredits = 0;
            foreach($journal['lines'] as $line){
                $isdebit = $line['dc'] === Ledger::DEBIT;
                $amount = floatval($line['amount']);
                $totaldebits += $isdebit ? $amount : 0;
                $totalcredits += $isdebit ? 0 : $amount;
                array_push($data,[
                    'accountid' => (int)$line['accountId'],
                    'accountname' => ucwords(trim($line['accountName'])),
                    'debit' => $isdebit ? $amount : '',
                    'credit' => $isdebit ? '' : $amount,
                    'narration' => ucwords((string)$line['memo'])
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
            $result = $this->journalModel->delete($id);
            if(!$result['success']){
                $message = $result['errors'] ? implode('<br>',array_map('htmlspecialchars',$result['errors']))
                            : 'Unable to delete selected journal. Please try again or contact admin!';
                flash('journal_msg',$message,alerterrorclass());
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

    public function validateimport()
    {
        $fields = json_decode(file_get_contents('php://input')); //extract fields;
        $data = [
            'entries' => $fields->data
        ];

        $validated = [];
        $errorCount = 0;

        foreach($data['entries'] as $entry){
            $accid = $this->journalModel->getaccount($entry->account);
            if($accid === false){
                $errorCount++;
                continue;
            }
            array_push($validated,[
                'accountId' => $accid,
                'account' => $entry->account,
                'debit' => floatval($entry->debit),
                'credit' => floatval($entry->credit),
                'narration' => $entry->description
            ]);
        }

        if($errorCount > 0){
            http_response_code(400);
            echo json_encode(['message' => 'Entries not found','success' => false]);
            exit;
        }
        else
        {
            echo json_encode(['message' => 'Entries found','success' => true,'data' => $validated]);
            exit;
        }
    }

    public function getJournalDetails()
    {
        if($_SERVER['REQUEST_METHOD'] !== 'GET'){
            redirect('users/deniedaccess');
            exit;
        }

        $data = [
            'id' => isset($_GET['id']) && !empty(trim($_GET['id'])) ? (int)trim(htmlentities($_GET['id'])) : null,
            'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim(htmlentities($_GET['type'])) : null
        ];

        if(is_null($data['id']) || is_null($data['type'])){
            http_response_code(400);
            echo json_encode(['message' => 'Provide all required fields','success' => false]);
            exit;
        }

        $details = $this->journalModel->getJournalDetails($data);

        if($details === false){
            http_response_code(404);
            echo json_encode(['message' => 'Journal not found','success' => false]);
            exit;
        }

        $output = '';
        if(!empty($details) && !empty($details[0]->notPosted)){
            $output .= '<div class="alert alert-warning py-1"><strong>Not posted</strong> - preview only. Nothing is in the ledger until the expense is approved.</div>';
        }
        $output .=
        '<table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Account</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Narration</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach($details as $detail){
            $debit = floatval($detail->debit) > 0 ? number_format(floatval($detail->debit),2) : '';
            $credit = floatval($detail->credit) > 0 ? number_format(floatval($detail->credit),2) : '';
            $output .= 
            '<tr>
                <td>'.date('d/m/Y', strtotime($detail->transactionDate)).'</td>
                <td>'.ucwords($detail->account).'</td>
                <td>'.$debit.'</td>
                <td>'.$credit.'</td>
                <td>'.ucwords($detail->narration).'</td>
            </tr>';
        }
        $output .= 
          '</tbody>
        </table>';
        
        http_response_code(200);
        echo $output;
    }
}