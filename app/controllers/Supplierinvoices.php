<?php

class Supplierinvoices extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) ) {
            redirect('');
        }else {
            $this->invoiceModel = $this->model('Supplierinvoice');
        }
    }
    public function index()
    {
        $form = 'Supplier Invoice';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->invoiceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $invoices = $this->invoiceModel->index();
        $data = ['invoices' => $invoices];
        $this->view('supplierinvoices/index',$data);
    }
    public function add()
    {
        $suppliers  = $this->invoiceModel->getSuppliers();
        $products  = $this->invoiceModel->getProducts();
        $accounts  = $this->invoiceModel->getAccounts();
        $vats  = $this->invoiceModel->getVats();
        $data = [
            'suppliers' => $suppliers,
            'products' => $products,
            'accounts' => $accounts,
            'vats' => $vats
        ];
        $this->view('supplierinvoices/add',$data);
    }
    public function fetchsupplierdetails()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $id = trim($_POST['id']);
            $details = $this->invoiceModel->getSupplierDetails($id);
           
            $output['email'] = trim($details->email);
            $output['pin'] = trim(strtoupper($details->pin));
            echo json_encode($output);
        }
    }
    public function getrate()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $vat = trim($_POST['vat']);
            echo $this->invoiceModel->getRate($vat);
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
                $this->invoiceModel->create($data);
            }
        }
        else {
            redirect('supplierinvoices');
        }
    }
    public function edit($id)
    {
        $header = $this->invoiceModel->getInvoiceHeader(trim($id));
        $details = $this->invoiceModel->getInvoiceDetails(trim($id));
        $suppliers  = $this->invoiceModel->getSuppliers();
        $products  = $this->invoiceModel->getProducts();
        $vats  = $this->invoiceModel->getVats();
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
                $this->invoiceModel->update($data);
            }
        }
        else {
            redirect('supplierinvoices');
        }
    }
    public function pay($id)
    {
        $invoice = $this->invoiceModel->fillInvoiceDetails(trim($id));
        $paymethods = $this->invoiceModel->paymethods();
        $banks = $this->invoiceModel->banks();
        // $invoice = $this->invoiceModel->getInvoiceDetails(trim($id));
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
            
            $paymethods = $this->invoiceModel->paymethods();
            $banks = $this->invoiceModel->banks();
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
            $invoice = $this->invoiceModel->fillInvoiceDetails(encryptId($data['id']));
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
                if ($this->invoiceModel->payment($data)) {
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
        $header = $this->invoiceModel->getInvoiceHeader(trim($id));
        $details = $this->invoiceModel->getInvoiceDetails(trim($id));
        $congregationinfo = $this->invoiceModel->getCongregationInfo();
        $supplierinfo = $this->invoiceModel->getSupplierInfo($header->supplierId); 
        $data = [
            'congregationinfo' => $congregationinfo,
            'header' => $header,
            'supplierinfo' => $supplierinfo,
            'details' => $details
        ];
        $this->view('supplierinvoices/print',$data);
    }
}