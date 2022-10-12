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
        $this->reusemodel = $this->model('Reusables');
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
            'supplier' => '',
            'idate' => '',
            'ddate' => '',
            'vattype' => '',
            'vat' => '',
            'invoiceno' => '',
            'isedit' => false,
            'id' => '',
            'email' => '',
            'pin' => '',
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
        checkcenter($header->congregationId);
        if($this->reusemodel->CheckYearClosed($header->fiscalYearId)){
            flash('supplierinvoice_msg','Cannot edit for closed year','custom-danger alert-dismissible fade show');
            exit;
        }
        $suppliers  = $this->invoicemodel->getSuppliers();
        $products  = $this->invoicemodel->getProducts();
        $vats  = $this->invoicemodel->getVats();
        $supplierdetails = $this->invoicemodel->getSupplierDetails($header->supplierId);
        $data = [
            'accounts'  => $this->invoicemodel->getAccounts(),
            'suppliers' => $suppliers,
            'email' => $supplierdetails->email,
            'pin' => strtoupper($supplierdetails->pin),
            'products' => $products,
            'vats' => $vats,
            'details' => $details,
            'isedit' => true,
            'supplier' => $header->supplierId,
            'idate' => $header->invoiceDate,
            'ddate' => $header->duedate,
            'vattype' => $header->vattype,
            'vat' => $header->vat,
            'invoiceno' => $header->invoiceNo,
            'id' => $header->ID,
            'table' => [],
        ];
        foreach($details as $detail){
            array_push($data['table'],[
                'pid' => $detail->productId,
                'pname' => $detail->accountType,
                'qty' => $detail->qty,
                'rate' => $detail->rate,
                'gross' => $detail->gross
            ]);
        }
        $this->view('supplierinvoices/add',$data);
        exit;
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