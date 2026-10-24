<?php
class Invoices extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'customer invoices');
        $this->invoiceModel = $this->model('Invoice');
        $this->reusemodel = $this->model('Reusables');
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

    /** Lists every invoice form needs, plus blank / edit values; $values overrides the blanks. */
    private function formData(array $values = []): array
    {
        return array_merge([
            'customers' => $this->invoiceModel->getCustomers(),
            'products' => $this->invoiceModel->getProducts(),
            'accounts' => $this->invoiceModel->lineAccounts(),
            'receivables' => $this->invoiceModel->getReceivableAccounts(),
            'vats' => $this->invoiceModel->getVats(),
            'customer' => '',
            'idate' => '',
            'ddate' => '',
            'vattype' => '',
            'vat' => '',
            'receivable' => '',
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
        $this->view('invoices/index',['invoices' => $this->invoiceModel->index()]);
    }

    public function add()
    {
        $this->view('invoices/add',$this->formData(['invoiceno' => $this->invoiceModel->peekInvoiceNo()]));
    }

    public function fetchcustomerdetails()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(405,['message' => 'Method not allowed']);
        }
        $id = isset($_GET['cid']) ? trim((string)$_GET['cid']) : '';
        if ($id === '') {
            $this->json(400,['message' => 'Select customer']);
        }
        $details = $this->invoiceModel->getCustomerDetails($id);
        if (!$details) {
            $this->json(404,['message' => 'Customer not found']);
        }
        $this->json(200,[
            'email' => is_null($details->email) ? '' : trim($details->email),
            'pin' => is_null($details->pin) ? '' : trim(strtoupper($details->pin)),
        ]);
    }

    /** Rate and income account of a product, for the line the user is adding. */
    public function getproduct()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(405,['message' => 'Method not allowed']);
        }
        $pid = isset($_GET['pid']) ? trim((string)$_GET['pid']) : '';
        if ($pid === '') {
            $this->json(400,['message' => 'Select product']);
        }
        $product = $this->invoiceModel->getProductDetails($pid);
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

    public function newproduct()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(405,['message' => 'Method not allowed']);
        }
        $fields = json_decode(file_get_contents('php://input'),true);
        if (!is_array($fields)) {
            $this->json(400,['message' => 'Provide all required fields']);
        }
        $result = $this->invoiceModel->newProduct([
            'name' => $fields['name'] ?? '',
            'desc' => $fields['desc'] ?? '',
            'sp' => $fields['sp'] ?? '',
            'account' => $fields['account'] ?? '',
        ]);
        if (!$result['success']) {
            $this->fail($result['errors']);
        }
        $output = '<option value="0" style="background-color: #a7f3d0; color :black;">Add NEW</option>';
        foreach ($this->invoiceModel->getProducts() as $product) {
            $output .= '<option value="' . (int)$product->ID . '">' . $this->e($product->productName) . '</option>';
        }
        $this->json(200,['productid' => $result['id'],'products' => $output]);
    }

    /** create and update take the same JSON; only the id / isEdit differ. */
    private function save(bool $isEdit)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(405,['message' => 'Method not allowed']);
        }
        $fields = json_decode(file_get_contents('php://input'),true);
        if (!is_array($fields) || !isset($fields['header']) || !is_array($fields['header'])) {
            $this->json(400,['message' => 'Provide all required information']);
        }
        $header = $fields['header'];
        $result = $this->invoiceModel->save([
            'id' => $header['id'] ?? '',
            'isedit' => $isEdit,
            'customer' => $header['customer'] ?? '',
            'idate' => $header['invoiceDate'] ?? '',
            'ddate' => $header['dueDate'] ?? '',
            'vattype' => $header['vatType'] ?? '',
            'vat' => $header['vat'] ?? '',
            'receivable' => $header['receivable'] ?? '',
            'table' => $fields['table'] ?? null,
        ]);
        if (!$result['success']) {
            $this->fail($result['errors']);
        }
        $this->json(200,['message' => 'Invoice saved successfully','success' => true,'id' => $result['id'],'invoiceNo' => $result['invoiceNo']]);
    }

    public function create()
    {
        $this->save(false);
    }

    public function update()
    {
        $this->save(true);
    }

    public function edit($id)
    {
        $header = $this->invoiceModel->getInvoiceHeader(trim($id));
        if (!$header) {
            flash('invoice_msg','Invoice not found for this congregation','alert custom-danger alert-dismissible fade show');
            redirect('invoices');
            exit;
        }
        if ($this->reusemodel->CheckYearClosed($header->fiscalYearId)) {
            flash('invoice_msg','Cannot edit for closed year','alert custom-danger alert-dismissible fade show');
            redirect('invoices');
            exit;
        }
        $type = (int)$_SESSION['userType'];
        if ($type > 2 && $type <= 4 && ((int)$header->postedBy !== (int)$_SESSION['userId'] || (int)$header->status !== 0)) {
            flash('invoice_msg','You can only edit your own unpaid invoices','alert custom-danger alert-dismissible fade show');
            redirect('invoices');
            exit;
        }
        $customer = $this->invoiceModel->getCustomerDetails($header->customerId);
        $vatRate = '';
        if (!is_null($header->vatId)) {
            foreach ($this->invoiceModel->getVats() as $vat) {
                if ((int)$vat->ID === (int)$header->vatId) { $vatRate = $vat->rate; }
            }
        }
        $data = $this->formData([
            'email' => $customer ? $customer->email : '',
            'pin' => $customer ? strtoupper((string)$customer->pin) : '',
            'isedit' => true,
            'haspayments' => $this->invoiceModel->hasPayments($header->ID),
            'customer' => $header->customerId,
            'idate' => $header->invoiceDate,
            'ddate' => $header->duedate,
            'vattype' => $header->vattype,
            'vat' => $vatRate,
            // a legacy invoice has no stored receivable account: the user picks one (no default)
            'receivable' => !is_null($header->ReceivableAccountId) ? (string)$header->ReceivableAccountId : '',
            'invoiceno' => $header->invoiceNo,
            'id' => $header->ID,
        ]);
        foreach ($this->invoiceModel->getInvoiceDetails($header->ID) as $detail) {
            array_push($data['table'],[
                'pid' => $detail->productId,
                'pname' => $detail->accountType,
                'account' => $detail->account,
                'desc' => $detail->description,
                'qty' => $detail->qty,
                'rate' => $detail->rate,
                'gross' => $detail->gross
            ]);
        }
        // the stored account may have been deactivated since: keep it listed so the form still shows it
        if (!is_null($header->ReceivableAccountId) && !in_array((int)$header->ReceivableAccountId,array_map('intval',array_column($data['receivables'],'ID')),true)) {
            $row = $this->invoiceModel->getAccountRow((int)$header->ReceivableAccountId);
            if ($row) { array_unshift($data['receivables'],$row); }
        }
        $this->view('invoices/add',$data);
    }

    public function print($id)
    {
        $header = $this->invoiceModel->getInvoiceHeader(trim($id));
        if (!$header) {
            flash('invoice_msg','Invoice not found for this congregation','alert custom-danger alert-dismissible fade show');
            redirect('invoices');
            exit;
        }
        $data = [
            'congregationinfo' => $this->invoiceModel->getCongregationInfo(),
            'header' => $header,
            'customerinfo' => $this->invoiceModel->getCustomernInfo($header->customerId),
            'details' => $this->invoiceModel->getInvoiceDetails($header->ID)
        ];
        $this->view('invoices/print',$data);
    }

    private function payData(array $values = []): array
    {
        return array_merge([
            'paymethods' => $this->invoiceModel->paymethods(),
            'cashaccounts' => $this->invoiceModel->getCashAccounts(),
            'banks' => $this->invoiceModel->getBanks(),
            'paydate' => date('Y-m-d'),
            'amount' => '',
            'paymethod' => 3,
            'cashaccount' => '',
            'bank' => '',
            'reference' => '',
            'errors' => []
        ],$values);
    }

    public function pay($id)
    {
        $invoice = $this->invoiceModel->fillInvoiceDetails(trim($id));
        if (!$invoice) {
            flash('invoice_msg','Invoice not found for this congregation','alert custom-danger alert-dismissible fade show');
            redirect('invoices');
            exit;
        }
        $this->view('invoices/pay',$this->payData(['invoice' => $invoice]));
    }

    public function payment()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('invoices');
            exit;
        }
        $data = [
            'id' => $_POST['id'] ?? '',
            'paydate' => $_POST['paydate'] ?? '',
            'amount' => $_POST['amount'] ?? '',
            'paymethod' => $_POST['paymethod'] ?? '',
            'cashaccount' => $_POST['cashaccount'] ?? '',
            'bank' => $_POST['bank'] ?? '',
            'reference' => $_POST['reference'] ?? '',
        ];
        $result = $this->invoiceModel->payment($data);
        if ($result['success']) {
            flash('invoice_msg','Payment saved successfully');
            redirect('invoices');
            exit;
        }
        $invoice = ctype_digit((string)$data['id']) ? $this->invoiceModel->fillInvoiceDetails(encryptId((int)$data['id'])) : false;
        if (!$invoice) {
            flash('invoice_msg',$this->e(implode(' ',$result['errors'])),'alert custom-danger alert-dismissible fade show');
            redirect('invoices');
            exit;
        }
        $this->view('invoices/pay',$this->payData([
            'invoice' => $invoice,
            'paydate' => is_scalar($data['paydate']) ? $data['paydate'] : '',
            'amount' => is_scalar($data['amount']) ? $data['amount'] : '',
            'paymethod' => is_scalar($data['paymethod']) ? $data['paymethod'] : '',
            'cashaccount' => is_scalar($data['cashaccount']) ? $data['cashaccount'] : '',
            'bank' => is_scalar($data['bank']) ? $data['bank'] : '',
            'reference' => is_scalar($data['reference']) ? $data['reference'] : '',
            'errors' => $result['errors'],
        ]));
    }
}
