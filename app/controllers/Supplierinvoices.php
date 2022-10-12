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
            'vats' => $vats,
            'isedit' => false,
            'id' => ''
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

    public function saveproduct()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $fields = json_decode(file_get_contents('php://input'));
            $data = [
                'productname' => isset($fields->productName) && !empty(trim($fields->productName)) ? trim($fields->productName) : NULL,
                'description' => isset($fields->description) && !empty(trim($fields->description)) ? trim($fields->description) : NULL,
                'rate' => isset($fields->rate) && !empty(trim($fields->rate)) ? floatval(trim($fields->rate)) : NULL,
                'account' => isset($fields->account) && !empty(trim($fields->account)) ? trim($fields->account) : NULL,
            ];

            //validate
            if(is_null($data['productname']) || is_null($data['rate']) || is_null($data['account'])){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required fields']);
                exit;
            }

            $product = $this->invoicemodel->SaveProduct($data);
            
            if(!converttobool($product[0])){
                http_response_code(500);
                echo json_encode(['message' => 'Unable to save product. Retry or contact admin']);
                exit;
            }

            $productid = $this->invoicemodel->GetProductId();
            
            $output = '';
            $output .='<option value="0" style="background-color: #a7f3d0; color :black;"><span class="selectspan">Add NEW</span></option>';
            foreach ($this->invoicemodel->getProducts() as $product ) {
                $output .= '<option value="'.$product->ID.'">'.$product->productName.'</option>';
            }

            echo json_encode(['productid' => $productid,'products' => $output]);

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function createupdate()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = json_decode(file_get_contents('php://input'));
            $header = $fields->header;
            $table = $fields->table;
            $data = [
                'id' => !empty($header->id) ? trim($header->id) : null,
                'supplier' => !empty($header->supplier) ? trim($header->supplier) : null,
                'idate' => !empty($header->invoiceDate) ? date('Y-m-d',strtotime(trim($header->invoiceDate))) : null,
                'ddate' => !empty($header->dueDate) ? date('Y-m-d',strtotime(trim($header->dueDate))) : null,
                'vattype' => !empty($header->vatType) ? (int)trim($header->vatType) : null,
                'vat' => !empty($header->vat) ? trim($header->vat) : null,
                'invoiceno' => !empty($header->invoiceNo) ? trim($header->invoiceNo) : null,
                'isedit' => converttobool($header->isEdit),
                'table' => is_countable($table) ? $table : null,
                'totals' => floatval($header->total)
            ];

            //validate
            if(is_null($data['supplier']) || is_null($data['idate']) || is_null($data['ddate']) 
               || is_null($data['vattype']) || is_null($data['invoiceno'])){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required information']);
                exit;
            }

            if($data['idate'] > $data['ddate']){
                http_response_code(400);
                echo json_encode(['message' => 'Invoice date cannot be greater than due date']);
                exit;
            }

            if($data['vattype'] > 1 && is_null($data['vat'])){
                http_response_code(400);
                echo json_encode(['message' => 'Select vat']);
                exit;
            }

            if(!is_null($data['invoiceno']) && !$this->invoicemodel->CheckInvoiceNo($data['invoiceno'],$data['id'])){
                http_response_code(400);
                echo json_encode(['message' => 'Invoice no already exists']);
                exit;
            }

            if(!$this->invoicemodel->CreateUpdate($data)){
                http_response_code(500);
                echo json_encode(['message' => 'Unable to save. Retry or contact admin']);
                exit;
            }

            echo json_encode(['message' => 'Invoice saved successfully', 'success' => true]);
            exit;
        }
        else {
            redirect('users/deniedaccess');
            exit;
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