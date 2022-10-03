<?php
class Invoices extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }else {
            $this->invoiceModel = $this->model('Invoice');
        }
    }
    public function index()
    {
        $form = 'Invoices';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->invoiceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $invoices = $this->invoiceModel->index();
        $data = ['invoices' => $invoices];
        $this->view('invoices/index',$data);
    }
    public function add()
    {
        $form = 'Invoices';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->invoiceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $customers  = $this->invoiceModel->getCustomers();
        $products  = $this->invoiceModel->getProducts();
        $accounts  = $this->invoiceModel->getIncomeAccounts();
        $invoiceno = $this->invoiceModel->getInvoiceNo();
        $vats  = $this->invoiceModel->getVats();
        $data = [
            'invoiceno' => $invoiceno,
            'customers' => $customers,
            'products' => $products,
            'accounts' => $accounts,
            'vats' => $vats
        ];
        $this->view('invoices/add',$data);
    }
    public function fetchcustomerdetails()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            $id = trim($_POST['id']);
            $details = $this->invoiceModel->getCustomerDetails($id);
           
            $output['email'] = trim($details->email);
            $output['pin'] = trim(strtoupper($details->pin));
            echo json_encode($output);
        }
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            $data = [
                'customerid' => trim($_POST['customerId']),
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
            redirect('invoices');
        }
    }
    public function getrate()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            $vat = trim($_POST['vat']);
            echo $this->invoiceModel->getRate($vat);
        }
    }
    public function print($id)
    {
        $form = 'Invoices';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->invoiceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $header = $this->invoiceModel->getInvoiceHeader(trim($id));
        $details = $this->invoiceModel->getInvoiceDetails(trim($id));
        $congregationinfo = $this->invoiceModel->getCongregationInfo();
        $customerinfo = $this->invoiceModel->getCustomernInfo($header->customerId); 
        $data = [
            'congregationinfo' => $congregationinfo,
            'header' => $header,
            'customerinfo' => $customerinfo,
            'details' => $details
        ];
        $this->view('invoices/print',$data);
    }
    public function pay($id)
    {
        $form = 'Invoices';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->invoiceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
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
        $this->view('invoices/pay',$data);
    }
    public function payment()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            
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
                    redirect('invoices');
                }
            }
            else{
                $this->view('invoices/pay',$data);
            }
        }
        else {
           redirect('invoices');
        }
    }
    public function edit($id)
    {
        $form = 'Invoices';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->invoiceModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $header = $this->invoiceModel->getInvoiceHeader(trim($id));
        $details = $this->invoiceModel->getInvoiceDetails(trim($id));
        $customers  = $this->invoiceModel->getCustomers();
        $products  = $this->invoiceModel->getProducts();
        $vats  = $this->invoiceModel->getVats();
        $data = [
            'customers' => $customers,
            'products' => $products,
            'vats' => $vats,
            'header' => $header,
            'details' => $details
        ];
        $this->view('invoices/edit',$data);
    }
    public function newproduct()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            $data = [
                'name' => trim($_POST['name']),
                'desc' => trim($_POST['desc']),
                'sp' => trim($_POST['sp']),
                'account' => trim($_POST['account']),
            ];
            $id = $this->invoiceModel->newProduct($data);
            // $id = 6;
            echo $data['sp'];
        }
    }
    public function reloadproducts()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST =filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            $products = $this->invoiceModel->getProducts();
            // print_r($products);
            $output = '';
            $output .='<option value="0"><strong>Add NEW</strong></option>';
            foreach ($products as $product ) {
                $output .= '<option value="'.$product->ID.'" selected>'.$product->productName.'</option>';
            }
            echo $output;
        }
    }
    public function getproductrate()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST =filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            $product = trim($_POST['product']);
            $rate = $this->invoiceModel->getProductRate($product);
            echo $rate;
        }
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            $data = [
                'id' => trim($_POST['id']),
                'customerid' => trim($_POST['customerId']),
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
            redirect('invoices');
        }
    }
}