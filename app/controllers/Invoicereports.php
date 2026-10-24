<?php

class Invoicereports extends Controller
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'invoice reports');
        $this->reportmodel = $this->model('Invoicereport');
    }

    public function index()
    {
        $data = [];
        $this->view('reports/invoicereports',$data);
    }

    public function getinvoicereport()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $data = [
                'type' => isset($_GET['type']) && !empty(trim($_GET['type'])) ? trim($_GET['type']) : null,
                'criteria' => isset($_GET['criteria']) && !empty(trim($_GET['criteria'])) ? trim($_GET['criteria']) : null,
                'sdate' => isset($_GET['sdate']) && !empty(trim($_GET['sdate'])) ? date('Y-m-d',strtotime(trim($_GET['sdate']))) : null,
                'edate' => isset($_GET['edate']) && !empty(trim($_GET['edate'])) ? date('Y-m-d',strtotime(trim($_GET['edate']))) : null,
            ];

            if(is_null($data['type'])){
                http_response_code(400);
                echo json_encode(['message' => 'Select report type']);
                exit;
            }

            if($data['type'] === 'byinvoice' && is_null($data['criteria'])){
                http_response_code(400);
                echo json_encode(['message' => 'Select Invoice No']);
                exit;
            }

            $invoiceId = 0;
            if($data['type'] === 'byinvoice'){
                // decision 134: the criterion is a supplier invoice HEADER ID of this congregation's list
                if(!ctype_digit($data['criteria']) || strlen($data['criteria']) > 10 || !$this->reportmodel->isReportInvoice((int)$data['criteria'])){
                    http_response_code(400);
                    echo json_encode(['message' => 'Invalid invoice']);
                    exit;
                }
                $invoiceId = (int)$data['criteria'];
            }

            if($data['type'] === 'bysupplier' && (is_null($data['criteria']) || is_null($data['sdate']) || is_null($data['edate'])) ){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required field(s)']);
                exit;
            }

            if($data['type'] === 'all' && (is_null($data['sdate']) || is_null($data['edate']))){
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required fields']);
                exit;
            }

            $results = null;
            if($data['type'] === 'balances'){
                $results = $this->reportmodel->GetInvoicesWithBalance();
            }elseif($data['type'] === 'byinvoice'){
                $results = $this->reportmodel->GetPaymentPayInvoice($invoiceId);
            }elseif($data['type'] === 'bysupplier'){
                $results = $this->reportmodel->GetPaymentPaySupplier($data);
            }elseif($data['type'] === 'all'){
                $results = $this->reportmodel->GetAllPayments($data);
            }elseif ($data['type'] === 'supplierbalances') {
                $results = $this->reportmodel->GetSupplierBalances($data);
            }

            echo json_encode(['results' => $results, 'success' => true]);

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function fetchselectoptions()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $type = trim((string)($_GET['type'] ?? ''));
            $output = '';

            if($type === 'invoiceno'){
                $output = '<option value="" selected disabled>Select Invoice No</option>';
                foreach($this->reportmodel->GetInvoiceNos() as $invoiceno) {
                    $label = (string)$invoiceno->supplierName.' — '.(string)$invoiceno->invoiceNo;
                    $output .= '<option value="'.(int)$invoiceno->ID.'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</option>';
                }
            }elseif($type === 'supplier'){
                $output = '<option value="" selected disabled>Select supplier</option>';
                foreach($this->reportmodel->GetSuppliers() as $supplier) {
                    $output .= '<option value="'.(int)$supplier->ID.'">'.htmlspecialchars((string)$supplier->supplierName,ENT_QUOTES,'UTF-8').'</option>';
                }
            }
            
            echo json_encode($output);
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}