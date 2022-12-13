<?php

class Payments extends Controller 
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'payments');
        $this->paymentmodel = $this->model('Payment');
        $this->depositmodel = $this->model('Deposit');
        $this->reusemodel = $this->model('Reusables');
    }

    public function index()
    {
        $data = [
            'payments' => $this->paymentmodel->GetPayments(),
        ];
        $this->view('payments/index',$data);
        exit;
    }

    public function add()
    {
        $data = [
            'invoices' => $this->paymentmodel->GetPendingInvoices(),
            'paymethods' => $this->depositmodel->GetBanks(),
            'paymentno' => $this->paymentmodel->GetPaymentId(),
            'banks' => $this->depositmodel->GetBanks()
        ];
        $this->view('payments/add',$data);
        exit;
    }

    public function create()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $fields = json_decode(file_get_contents('php://input'));
            $header = $fields->header;
            $payments = $fields->payments;
            //get data from decoded json
            $data = [
                'paydate' => isset($header->paydate) && !empty($header->paydate) ? date('Y-m-d',strtotime($header->paydate)) : null,
                'paymethod' => isset($header->paymethod) && !empty($header->paymethod) ? (int)trim($header->paymethod) : null,
                'bank' => isset($header->bank) && !empty($header->bank) ? (int)trim($header->bank) : null,
                'payments' => is_countable($payments) ? $payments : null,
            ];
            //validate
            if(is_null($data['paydate']) || is_null($data['paymethod']) || is_null($data['bank'])){
                http_response_code(400);
                echo json_encode(['message' => 'Fill all required fields']);
                exit;
            }
            if($data['paydate'] > date('Y-m-d')){
                http_response_code(400);
                echo json_encode(['message' => 'Payment date cannot be greater than current date']);
                exit;
            }
            $chequeerror = 0; $overpaymenterror = 0; $checkentered =0; $checks = [];

            //validate payment
            foreach($data['payments'] as $payment) 
            {
                if(!isset($payment->cheque) || empty($payment->cheque)){
                    $chequeerror ++;
                }
                if(floatval($payment->payment) > floatval($payment->balance)){
                    $overpaymenterror ++;
                }
            }

            if($chequeerror > 0){
                http_response_code(400);
                echo json_encode(['message' => 'Payment reference not entered for one or more payments']);
                exit;
            }
            
            //check if cheque no is already entered
            foreach($data['payments'] as $payment){
                if(!in_array(strtolower(trim($payment->cheque)),$checks)){
                    array_push($checks,strtolower(trim($payment->cheque)));
                }else{
                    $checkentered ++;
                }
            }

            if($overpaymenterror > 0){
                http_response_code(400);
                echo json_encode(['message' => 'Overpayment of one or more payments']);
                exit;
            }

            if($checkentered > 0){
                http_response_code(400);
                echo json_encode(['message' => 'One or more cheques entered more than once']);
                exit;
            }

           
            if(!$this->paymentmodel->Create($data)){
                http_response_code(500);
                echo json_encode(['message' => 'Couldnt save selected payment(s)! Retry or contact admin']);
                exit;
            }

            http_response_code(200);
            echo json_encode(['message' => 'Payments saved successfully!','success' => true]);
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function print($id)
    {
        $params = explode('-', $id);
        $paymentNo = $params[0];
        $supplier = $params[1];
        
        $data = [
            'paymentno' => $paymentNo,
            'congregationinfo' => $this->reusemodel->GetCongregationDetails(),
            'supplier' => $this->paymentmodel->GetSupplierDetails($supplier),
            'paymentdate' => date('d-m-Y',strtotime($this->paymentmodel->GetPaymentDate($paymentNo))),
            'invoicedetails' => $this->paymentmodel->GetInvoicedetails($paymentNo,$supplier),
            'total' => $this->paymentmodel->GetPaymentSupplierValue($paymentNo,$supplier)
        ];
        $this->view('payments/print',$data);
        exit;
    }
}
