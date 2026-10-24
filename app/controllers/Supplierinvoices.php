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

    private function json(int $status, array $body)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($body);
        exit;
    }

    private function e($v): string
    {
        return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
    }

    /**
     * Validation answer: 'message' is escaped because the shared utils.js displayAlert() inserts it as HTML (it echoes
     * product / account names); 'errors' keeps the raw texts.
     */
    private function fail(array $errors, int $status = 422)
    {
        $this->json($status,['message' => $this->e(implode('; ',$errors)),'errors' => $errors]);
    }

    /** Lists every invoice form needs, plus blank/edit values; $values overrides the blanks. */
    private function formData(array $values = []): array
    {
        return array_merge([
            'suppliers' => $this->invoicemodel->getSuppliers(),
            'products' => $this->invoicemodel->getProducts(),
            'accounts' => $this->invoicemodel->lineAccounts(),
            'payables' => $this->invoicemodel->getPayableAccounts(),
            'defaultpayable' => $this->invoicemodel->getDefaultPayable(),
            'vats' => $this->invoicemodel->getVats(),
            'supplier' => '',
            'idate' => '',
            'ddate' => '',
            'vattype' => '',
            'vat' => '',
            'payable' => '',
            'invoiceno' => '',
            'isedit' => false,
            'haspayments' => false,
            'id' => '',
            'email' => '',
            'pin' => '',
            'table' => []
        ],$values);
    }

    public function index()
    {
        $invoices = $this->invoicemodel->index();
        $data = ['invoices' => $invoices];
        $this->view('supplierinvoices/index',$data);
    }

    public function add()
    {
        $data = $this->formData();
        $data['payable'] = (string)($data['defaultpayable'] ?? '');
        $this->view('supplierinvoices/add',$data);
        exit;
    }

    public function fetchsupplierdetails()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(405,['message' => 'Method not allowed']);
        }
        $id = isset($_GET['sid']) ? trim((string)$_GET['sid']) : '';
        if ($id === '') {
            $this->json(400,['message' => 'Select supplier']);
        }
        $details = $this->invoicemodel->getSupplierDetails($id);
        if (!$details) {
            $this->json(404,['message' => 'Supplier not found']);
        }
        $this->json(200,[
            'email' => is_null($details->email) ? '' : $details->email,
            'pin' => is_null($details->pin) ? '' : strtoupper($details->pin),
        ]);
    }

    /** Rate and debit account of a product, for the line the user is adding. */
    public function getproduct()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(405,['message' => 'Method not allowed']);
        }
        $pid = isset($_GET['pid']) ? trim((string)$_GET['pid']) : '';
        if ($pid === '') {
            $this->json(400,['message' => 'Select product']);
        }
        $product = $this->invoicemodel->getProductDetails($pid);
        if (!$product) {
            $this->json(404,['message' => 'Product not found']);
        }
        if ($product['problem'] !== null) {
            $this->fail(['This product cannot be used on an invoice: its account ' . $product['problem']]);
        }
        $this->json(200,[
            'rate' => $product['rate'],
            'accountId' => (int)$product['accountId'],
            'account' => $product['account'],
        ]);
    }

    public function saveproduct()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(405,['message' => 'Method not allowed']);
        }
        $fields = json_decode(file_get_contents('php://input'),true);
        if (!is_array($fields)) {
            $this->json(400,['message' => 'Provide all required fields']);
        }
        $result = $this->invoicemodel->SaveProduct([
            'productname' => $fields['productName'] ?? '',
            'description' => $fields['description'] ?? '',
            'rate' => $fields['rate'] ?? '',
            'account' => $fields['account'] ?? '',
        ]);
        if (!$result['success']) {
            $this->fail($result['errors']);
        }

        $output = '<option value="0" style="background-color: #a7f3d0; color :black;"><span class="selectspan">Add NEW</span></option>';
        foreach ($this->invoicemodel->getProducts() as $product) {
            $output .= '<option value="' . (int)$product->ID . '">' . $this->e($product->productName) . '</option>';
        }
        $this->json(200,['productid' => $result['id'],'products' => $output]);
    }

    public function createupdate()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(405,['message' => 'Method not allowed']);
        }
        $fields = json_decode(file_get_contents('php://input'),true);
        if (!is_array($fields) || !isset($fields['header']) || !is_array($fields['header'])) {
            $this->json(400,['message' => 'Provide all required information']);
        }
        $header = $fields['header'];
        $result = $this->invoicemodel->save([
            'id' => $header['id'] ?? '',
            'isedit' => converttobool($header['isEdit'] ?? false),
            'supplier' => $header['supplier'] ?? '',
            'idate' => $header['invoiceDate'] ?? '',
            'ddate' => $header['dueDate'] ?? '',
            'vattype' => $header['vatType'] ?? '',
            'vat' => $header['vat'] ?? '',
            'invoiceno' => $header['invoiceNo'] ?? '',
            'payable' => $header['payable'] ?? '',
            'table' => $fields['table'] ?? null,
        ]);
        if (!$result['success']) {
            $this->fail($result['errors']);
        }
        $this->json(200,['message' => 'Invoice saved successfully','success' => true,'id' => $result['id']]);
    }

    public function edit($id)
    {
        $header = $this->invoicemodel->getInvoiceHeader(trim($id));
        if (!$header) {
            flash('supplierinvoice_msg','Invoice not found for this congregation','custom-danger alert-dismissible fade show');
            redirect('supplierinvoices');
            exit;
        }
        if ($this->reusemodel->CheckYearClosed($header->fiscalYearId)) {
            flash('supplierinvoice_msg','Cannot edit for closed year','custom-danger alert-dismissible fade show');
            redirect('supplierinvoices');
            exit;
        }
        $type = (int)$_SESSION['userType'];
        if ($type > 2 && $type <= 4 && ((int)$header->postedBy !== (int)$_SESSION['userId'] || (int)$header->status !== 0)) {
            flash('supplierinvoice_msg','You can only edit your own unpaid invoices','custom-danger alert-dismissible fade show');
            redirect('supplierinvoices');
            exit;
        }
        $supplierdetails = $this->invoicemodel->getSupplierDetails($header->supplierId);
        $vatRate = '';
        if (!is_null($header->vatId)) {
            foreach ($this->invoicemodel->getVats() as $vat) {
                if ((int)$vat->ID === (int)$header->vatId) { $vatRate = $vat->rate; }
            }
        }
        $data = $this->formData([
            'email' => $supplierdetails ? $supplierdetails->email : '',
            'pin' => $supplierdetails ? strtoupper((string)$supplierdetails->pin) : '',
            'isedit' => true,
            'haspayments' => $this->invoicemodel->hasPayments($header->ID),
            'supplier' => $header->supplierId,
            'idate' => $header->invoiceDate,
            'ddate' => $header->duedate,
            'vattype' => $header->vattype,
            'vat' => $vatRate,
            // a legacy invoice has no stored crediting account: the user picks one (accounts payable is preselected)
            'payable' => !is_null($header->PayableAccountId) ? (string)$header->PayableAccountId : (string)($this->invoicemodel->getDefaultPayable() ?? ''),
            'invoiceno' => $header->invoiceNo,
            'id' => $header->ID,
        ]);
        foreach ($this->invoicemodel->getInvoiceDetails($header->ID) as $detail) {
            array_push($data['table'],[
                'pid' => $detail->productId,
                'pname' => $detail->accountType,
                'account' => $detail->account,
                'qty' => $detail->qty,
                'rate' => $detail->rate,
                'gross' => $detail->gross
            ]);
        }
        // the stored crediting account may have been deactivated since: keep it listed so the form still shows it
        if (!is_null($header->PayableAccountId) && !in_array((int)$header->PayableAccountId,array_map('intval',array_column($data['payables'],'ID')),true)) {
            $st = $this->invoicemodel->getPayableAccountRow((int)$header->PayableAccountId);
            if ($st) { array_unshift($data['payables'],$st); }
        }
        $this->view('supplierinvoices/add',$data);
        exit;
    }

    public function print($id)
    {
        $header = $this->invoicemodel->getInvoiceHeader(trim($id));
        if (!$header) {
            flash('supplierinvoice_msg','Invoice not found for this congregation','custom-danger alert-dismissible fade show');
            redirect('supplierinvoices');
            exit;
        }
        $details = $this->invoicemodel->getInvoiceDetails($header->ID);
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

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('users/deniedaccess');
            exit;
        }
        $id = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
        $result = $this->invoicemodel->Delete($id);
        if (!$result['success']) {
            flash('supplierinvoice_msg',$this->e(implode(' ',$result['errors'])),'custom-danger alert-dismissible fade show');
            redirect('supplierinvoices');
            exit;
        }
        flash('supplierinvoice_msg','Deleted successfully');
        redirect('supplierinvoices');
        exit;
    }
}
