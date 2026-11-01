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
    /** A request value as a positive int (optionally one of $allowed), or null when missing/invalid. Output is escaped where it is printed. */
    private function requestInt($input,$name,array $allowed = [])
    {
        $raw = filter_input($input,$name,FILTER_UNSAFE_RAW,FILTER_REQUIRE_SCALAR);
        if(!is_string($raw) || !preg_match('/^[0-9]{1,10}$/',trim($raw))){
            return null;
        }
        $value = (int)trim($raw);
        if($value < 1 || ($allowed && !in_array($value,$allowed,true))){
            return null;
        }
        return $value;
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
            if(!is_object($fields)){
                http_response_code(400); echo json_encode(['message'=>'Invalid request','success'=>false]); exit;
            }
            $dateRaw = isset($fields->date) ? trim((string)$fields->date) : '';
            $dateObj = DateTime::createFromFormat('!Y-m-d',$dateRaw);
            $isEdit = isset($fields->isEdit) ? converttobool($fields->isEdit) : false;
            $data = [
                // new journals get their number from Ledger inside the save transaction
                'journalno' => $isEdit ?
                               (isset($fields->editId) && is_scalar($fields->editId) && preg_match('/^[0-9]{1,10}$/',trim((string)$fields->editId)) && (int)trim((string)$fields->editId) >= 1
                                    ? (int)trim((string)$fields->editId) : null)
                               : 0,
                'isedit' => $isEdit,
                'date' => $dateObj && $dateObj->format('Y-m-d') === $dateRaw ? $dateRaw : null,
                'entries' => isset($fields->entries) && is_array($fields->entries) ? $fields->entries : []
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
            //if error on create/update
            $result = $this->journalModel->createupdate($data);
            if(!$result['success']){
                if($result['errors']){
                    http_response_code(422);
                    echo json_encode(['message' => implode('; ',$result['errors']),'success' => false]);
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
            // journal numbers are positive integers (journal_number_seq); anything else counts as not given
            $journalno = $this->requestInt(INPUT_GET,'journalno');
            //validation
            if(is_null($journalno)){
                http_response_code(400);
                echo json_encode(['message' => 'No journal no provided',"success" => false]);
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
                    // narrations are shown exactly as typed (decision 166)
                    'narration' => (string)$line['memo']
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
            $id = $this->requestInt(INPUT_POST,'id');
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
        header('Content-Type: application/json');
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            http_response_code(405); echo json_encode(['message'=>'POST a .csv or .xlsx file','success'=>false]); exit;
        }
        if(!isset($_FILES['file']) || !is_array($_FILES['file'])){
            http_response_code(400); echo json_encode(['message'=>'Choose a .csv or .xlsx file','success'=>false]); exit;
        }
        $file=$_FILES['file'];
        $uploadError=(int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if($uploadError!==UPLOAD_ERR_OK){
            $tooLarge=in_array($uploadError,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true);
            http_response_code($tooLarge?413:400); echo json_encode(['message'=>$tooLarge?'The file is larger than the server upload limit':'The upload failed','success'=>false]); exit;
        }
        if((int)($file['size'] ?? 0)>JournalImport::MAX_BYTES){
            http_response_code(413); echo json_encode(['message'=>'The file is larger than 2 MiB','success'=>false]); exit;
        }
        $ext=strtolower(pathinfo((string)($file['name'] ?? ''),PATHINFO_EXTENSION));
        if(!in_array($ext,['csv','xlsx'],true)){
            http_response_code(415); echo json_encode(['message'=>'Only .csv and .xlsx files are supported','success'=>false]); exit;
        }
        try{
            $rows=JournalImport::read((string)$file['tmp_name'],$ext);
            $result=$this->journalModel->validateImportRows($rows,isset($_POST['date'])?(string)$_POST['date']:null);
            if(!$result['entries']){throw new InvalidArgumentException('The file has no nonblank journal rows');}
            echo json_encode(['message'=>'Import checked','success'=>true]+$result); exit;
        }catch(LengthException $e){
            http_response_code(413); echo json_encode(['message'=>$e->getMessage(),'success'=>false]); exit;
        }catch(InvalidArgumentException $e){
            http_response_code(422); echo json_encode(['message'=>$e->getMessage(),'success'=>false]); exit;
        }catch(Throwable $e){
            error_log($e->getMessage(),0); http_response_code(500);
            echo json_encode(['message'=>'The import could not be read','success'=>false]); exit;
        }
    }

    public function getJournalDetails()
    {
        if($_SERVER['REQUEST_METHOD'] !== 'GET'){
            redirect('users/deniedaccess');
            exit;
        }

        $data = [
            'id' => $this->requestInt(INPUT_GET,'id'),
            // 2 = expense, the only transaction type with journal details
            'type' => $this->requestInt(INPUT_GET,'type',[2])
        ];

        if(is_null($data['id']) || is_null($data['type'])){
            http_response_code(400);
            echo json_encode(['message' => 'Provide all required fields','success' => false]);
            exit;
        }

        $details = $this->journalModel->getJournalDetails($data);

        if($details === null){
            http_response_code(400);
            echo json_encode(['message' => 'Only expense journal details are available','success' => false]);
            exit;
        }

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
            $account = htmlspecialchars(ucwords((string)$detail->account),ENT_QUOTES,'UTF-8');
            $narration = htmlspecialchars((string)$detail->narration,ENT_QUOTES,'UTF-8');
            $output .= 
            '<tr>
                <td>'.date('d/m/Y', strtotime($detail->transactionDate)).'</td>
                <td>'.$account.'</td>
                <td>'.$debit.'</td>
                <td>'.$credit.'</td>
                <td>'.$narration.'</td>
            </tr>';
        }
        $output .= 
          '</tbody>
        </table>';
        
        http_response_code(200);
        echo $output;
    }
}
