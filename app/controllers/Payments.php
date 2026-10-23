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
        $this->reusemodel = $this->model('Reusables');
    }

    private function json(int $status, array $body)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
        exit;
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
            'paymethods' => $this->paymentmodel->paymentMethods(),
            'cashaccounts' => $this->paymentmodel->getCashAccounts(),
            'banks' => $this->paymentmodel->getBanks()
        ];
        $this->view('payments/add',$data);
        exit;
    }

    public function create()
    {
        if($_SERVER['REQUEST_METHOD'] !== 'POST'){
            $this->json(405,['message' => 'Method not allowed']);
        }
        $fields = json_decode(file_get_contents('php://input'),true);
        if(!is_array($fields) || !isset($fields['header']) || !is_array($fields['header'])){
            $this->json(400,['message' => 'Fill all required fields']);
        }
        $header = $fields['header'];
        $result = $this->paymentmodel->Create([
            'paydate' => $header['paydate'] ?? '',
            'paymethod' => $header['paymethod'] ?? '',
            'bank' => $header['bank'] ?? '',
            'cashaccount' => $header['cashaccount'] ?? '',
            'payments' => $fields['payments'] ?? null,
        ]);
        if(!$result['success']){
            // escaped: utils.js displayAlert() inserts the message as HTML
            $this->json(422,['message' => htmlspecialchars(implode('; ',$result['errors']),ENT_QUOTES,'UTF-8'),'errors' => $result['errors']]);
        }
        $this->json(200,['message' => 'Payments saved successfully!','success' => true,'paymentNo' => $result['paymentNo']]);
    }

    public function print($id)
    {
        $paymentdetails = $this->paymentmodel->PaymentDetails($id);
        if(!$paymentdetails){
            redirect('payments');
            exit;
        }
        $supplier = $paymentdetails->supplierId;
        $data = [
            'paymentno' => formatStringId($paymentdetails->paymentNo),
            'chequeno' => strtoupper((string)$paymentdetails->paymentReference),
            'bank' => (int)$paymentdetails->paymentId !== 1 ? $this->paymentmodel->GetMoneyAccount($paymentdetails->moneyAccountId) : 'N/A',
            'congregationinfo' => $this->reusemodel->GetCongregationDetails(),
            'supplier' => $this->paymentmodel->GetSupplierDetails($supplier),
            'paymentdate' => date('d-m-Y',strtotime($paymentdetails->paymentDate)),
            'invoicedetails' => $this->paymentmodel->GetInvoicedetails($paymentdetails->paymentNo,$supplier),
            'total' => $this->paymentmodel->GetPaymentSupplierValue($paymentdetails->paymentNo,$supplier)
        ];
        $this->view('payments/printvoucher',$data);
        exit;
    }
}
