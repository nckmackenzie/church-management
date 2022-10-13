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
                $results = $this->reportmodel->GetPaymentPayInvoice($data['criteria']);
            }

            echo json_encode(['results' => $results, 'success' => true]);

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function fetchinvoicenos()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){

            $output = '<option value="" selected disabled>Select Invoice No</option>';
            foreach($this->reportmodel->GetInvoiceNos() as $invoiceno) {
                $output .= '<option value="'.$invoiceno->invoiceNo.'">'.$invoiceno->invoiceNo.'</option>';
            }
            echo json_encode($output);
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}