<?php
class Cashreceipts extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit();
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'cash receipts');
        $this->receiptmodel = $this->model('Cashreceipt');
    }

    public function index()
    {
        $receipts = $this->receiptmodel->GetReceipts();
        $data = [
            'receipts' => $receipts
        ];
        $this->view('cashreceipts/index',$data);
    }
    
    public function add()
    {
        $banks = $this->receiptmodel->GetBanks();
        $data = [
            'banks' => $banks,
            'isedit' => false,
            'receiptno' => $this->receiptmodel->GetReceiptNo(),
            'id' => '',
            'date' => date('Y-m-d'),
            'bank' => '',
            'amount' => '',
            'reference' => '',
            'description' => '',
            'date_err' => '',
            'bank_err' => '',
            'amount_err' => '',
            'reference_err' => '',
        ];
        $this->view('cashreceipts/add',$data);
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $banks = $this->receiptmodel->GetBanks();
            $data = [
                'banks' => $banks,
                'isedit' => converttobool($_POST['isedit']),
                'id' => trim($_POST['id']),
                'receiptno' => trim($_POST['receiptno']),
                'date' => date("Y-m-d", strtotime($_POST['date'])),
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'amount' => trim($_POST['amount']),
                'reference' => trim($_POST['reference']),
                'description' => trim($_POST['description']),
                'date_err' => '',
                'bank_err' => '',
                'amount_err' => '',
                'reference_err' => '',
            ];

            if(empty($data['date'])){
                $data['date_err'] = 'Select date';
            }else{
                if($data['date'] > date('Y-m-d')){
                    $data['date_err'] = 'Invalid date';
                }
            }

            if(empty($data['amount'])){
                $data['amount_err'] = 'Enter amount';
            }

            if(empty($data['bank'])){
                $data['bank_err'] = 'Select bank';
            }

            if(empty($data['reference'])){
                $data['reference_err'] = 'Enter cheque no';
            }

            if(!empty($data['date_err']) || !empty($data['amount_err']) || !empty($data['bank_err']) 
               || !empty($data['reference_err'])){
                $this->view('cashreceipts/add',$data);
                exit();
            }else{
                if(!$this->receiptmodel->CreateUpdate($data)){
                    flash('receipt_msg','Something Went Wrong!','alert custom-danger');
                    redirect('cashreceipts');
                    exit();
                }else{
                    flash('receipt_msg','Saved successfully!');
                    redirect('cashreceipts');
                    exit();
                }
            }

        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }

    public function edit($id)
    {
        $receipt = $this->receiptmodel->GetReceipt($id);
        $banks = $this->receiptmodel->GetBanks();
        $data = [
            'banks' => $banks,
            'isedit' => true,
            'id' => $receipt->ID,
            'receiptno' => $receipt->ReceiptNo,
            'date' => $receipt->TransactionDate,
            'bank' => $receipt->BankId,
            'amount' => $receipt->Debit,
            'reference' => strtoupper($receipt->Reference),
            'description' => strtoupper($receipt->Narration),
            'date_err' => '',
            'bank_err' => '',
            'amount_err' => '',
            'reference_err' => '',
        ];
        $this->view('cashreceipts/add',$data);
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $id = $_POST['id'];
            if(empty($id)){
                flash('receipt_msg','Unable to get selected entry!','alert custom-danger');
                redirect('cashreceipts');
                exit();
            }

            if(!$this->receiptmodel->Delete($id)){
                flash('receipt_msg','Something went wrong.Contact admin!','alert custom-danger');
                redirect('cashreceipts');
                exit();
            }

            flash('receipt_msg','Deleted successfully!');
            redirect('cashreceipts');
            exit();
        }else{
            redirect('users/deniedaccess');
            exit();
        }
    }
}