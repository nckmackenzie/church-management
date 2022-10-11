<?php

class Supplierinvoices extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'supplier invoices');
        $this->invoicemodel = $this->model('Supplierinvoice');
    }
    
    public function index()
    {
        $invoices = $this->invoicemodel->index();
        $data = ['invoices' => $invoices];
        $this->view('supplierinvoices/index',$data);
    }

    public function add()
    {
        $suppliers  = $this->invoicemodel->getSuppliers();
        $products  = $this->invoicemodel->getProducts();
        $accounts  = $this->invoicemodel->getAccounts();
        $vats  = $this->invoicemodel->getVats();
        $data = [
            'suppliers' => $suppliers,
            'products' => $products,
            'accounts' => $accounts,
            'vats' => $vats
        ];
        $this->view('supplierinvoices/add',$data);
        exit;
    }
    
    public function fetchsupplierdetails()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $id = isset($_GET['sid']) && !empty($_GET['sid']) ? trim($_GET['sid']) : NULL;

            if(is_null($id)){
                http_response_code(400);
                echo json_encode(['message' => 'Select supplier']);
                exit;
            }
            $details = $this->invoicemodel->getSupplierDetails($id);

            $data = [
                'email' => is_null($details->email) ? '' : $details->email,
                'pin' => is_null($details->pin) ? '' : strtoupper( $details->pin),
            ];

            echo json_encode($data);
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
    public function getrate()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $vat = trim($_POST['vat']);
            echo $this->invoicemodel->getRate($vat);
        }
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'supplierId' => trim($_POST['supplierId']),
                'invoicedate' => trim($_POST['invoicedate']),
                'invoice' => trim($_POST['invoice']),
                'duedate' => trim($_POST['duedate']),
                'vattype' => trim($_POST['vattype']),
                'vat' => !empty($_POST['vat']) ? trim($_POST['vat']) : NULL,
                'totals' => trim($_POST['totals']),
                'details' => $_POST['table_data'],
            ];
            if (!empty($data['invoice'])) {
                $this->invoicemodel->create($data);
            }
        }
        else {
            redirect('supplierinvoices');
        }
    }
    public function edit($id)
    {
        $header = $this->invoicemodel->getInvoiceHeader(trim($id));
        $details = $this->invoicemodel->getInvoiceDetails(trim($id));
        $suppliers  = $this->invoicemodel->getSuppliers();
        $products  = $this->invoicemodel->getProducts();
        $vats  = $this->invoicemodel->getVats();
        $data = [
            'suppliers' => $suppliers,
            'products' => $products,
            'vats' => $vats,
            'header' => $header,
            'details' => $details
        ];
        $this->view('supplierinvoices/edit',$data);
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'supplierId' => trim($_POST['supplierId']),
                'invoicedate' => trim($_POST['invoicedate']),
                'invoice' => trim($_POST['invoice']),
                'duedate' => trim($_POST['duedate']),
                'vattype' => trim($_POST['vattype']),
                'vat' => !empty($_POST['vat']) ? trim($_POST['vat']) : NULL,
                'totals' => trim($_POST['totals']),
                'details' => $_POST['table_data'],
            ];
            if (!empty($data['invoice'])) {
                $this->invoicemodel->update($data);
            }
        }
        else {
            redirect('supplierinvoices');
        }
    }
    public function pay($id)
    {
        $invoice = $this->invoicemodel->fillInvoiceDetails(trim($id));
        $paymethods = $this->invoicemodel->paymethods();
        $banks = $this->invoicemodel->banks();
        // $invoice = $this->invoicemodel->getInvoiceDetails(trim($id));
        $data = [
            'id' => '',
            'invoice' => $invoice,
            'paydate' => '',
            'amount' => '',
            'paymethods' => $paymethods,
            'paymethod' => 3,
            'banks' => $banks,
            'bank' => '',
            'reference' => '',
            'date_err' => '',
            'amount_err' => '',
            'bank_err' => '',
            'ref_err' => ''
        ];
        $this->view('supplierinvoices/pay',$data);
    }
    public function payment()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            
            $paymethods = $this->invoicemodel->paymethods();
            $banks = $this->invoicemodel->banks();
            $data = [
                'id' => trim($_POST['id']),
                'invoice' => '',
                'invoiceno' => trim($_POST['invoiceno']),
                'balance' => trim($_POST['balance']),
                'paydate' => trim($_POST['paydate']),
                'amount' => trim($_POST['amount']),
                'paymethods' => $paymethods,
                'paymethod' => trim($_POST['paymethod']),
                'banks' => $banks,
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : NULL,
                'reference' => trim($_POST['reference']),
                'date_err' => '',
                'amount_err' => '',
                'bank_err' => '',
                'ref_err' => ''
            ];
            $invoice = $this->invoicemodel->fillInvoiceDetails(encryptId($data['id']));
            $data['invoice'] = $invoice;
            
            if (empty($data['paydate'])) {
                $data['date_err'] = 'Select Date';
            }
            if (empty($data['amount'])) {
                $data['amount_err'] = 'Enter Amount';
            }
            if ($data['paymethod'] > 2 && (empty($data['bank']) || $data['bank'] == NULL)) {
                $data['bank_err'] = 'Select Bank';
            }
            if ($data['paymethod'] > 1 && empty($data['reference'])) {
                $data['ref_err'] = 'Enter Reference';
            }
            if (empty($data['date_err']) && empty($data['amount_err']) && empty($data['bank_err']) 
                && empty($data['ref_err'])) {
                if ($this->invoicemodel->payment($data)) {
                    redirect('supplierinvoices');
                }
            }
            else{
                $this->view('supplierinvoices/pay',$data);
            }
        }
        else {
           redirect('supplierinvoices');
        }
    }
    public function print($id)
    {
        $header = $this->invoicemodel->getInvoiceHeader(trim($id));
        $details = $this->invoicemodel->getInvoiceDetails(trim($id));
        $congregationinfo = $this->invoicemodel->getCongregationInfo();
        $supplierinfo = $this->invoicemodel->getSupplierInfo($header->supplierId); 
        $data = [
            'congregationinfo' => $congregationinfo,
            'header' => $header,
            'supplierinfo' => $supplierinfo,
            'details' => $details
        ];
        $this->view('supplierinvoices/print',$data);
    }
}