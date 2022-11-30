<?php
class Pledges extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'pledges');
        $this->pledgeModel = $this->model('Pledge');
        
    }
    public function index()
    {
        $pledges = $this->pledgeModel->index();
        $data = ['pledges' => $pledges];
        $this->view('pledges/index',$data);
    }
    public function add()
    {
        // $pledgers = $this->pledgeModel->getPledger(1);
        $paymethods = $this->pledgeModel->paymentMethods();
        $banks = $this->pledgeModel->getBanks();
        $data = [
            'category' => '',
            'pledgers' => '',
            'pledger' => '',
            'date' => '',
            'date_err' => '',
            'amountpledged' => '',
            'pledged_err' => '',
            'amountpaid' => '',
            'paid_err' => '',
            'paymethods' => $paymethods,
            'paymethod' => '',
            'banks' => $banks,
            'bank' => '',
            'bank_err' => '',
            'reference' => '',
            'ref_err' => '',
            'category_err_err' => '',
        ];
        $this->view('pledges/add',$data);
    }
    public function getpledger()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_GET =filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $category = isset($_GET['category']) && !empty(trim($_GET['category'])) ? (int)trim($_GET['category']) : null;
            if(is_null($category)){
                http_response_code(400);
                echo json_encode(['message' => 'Select pledger category']);
                exit;
            }
            $output = '<option selected disabled>Select pledger</option>';
            $pledgers = $this->pledgeModel->getPledger($category);
            foreach ($pledgers as $pledger) {
                $output .= '<option value="'.$pledger->ID.'">'.$pledger->pledger.'</option>';
            }
            
            echo json_encode($output);
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = json_decode(file_get_contents('php://input'));
            
            $data = [
                'category' => isset($fields->category) && !empty(trim($fields->category)) ? (int)trim($fields->category) : null,
                'pledger' => isset($fields->pledger) && !empty(trim($fields->pledger)) ? (int)trim($fields->pledger) : null,
                'pledgername' => isset($fields->pledgername) && !empty(trim($fields->pledgername)) ? strtolower(trim($fields->pledgername)) : null,
                'date' => isset($fields->date) && !empty(trim($fields->date)) ? date('Y-m-d',strtotime(trim($fields->date))) : null,
                'amountpledged' => isset($fields->pledged) && !empty(trim($fields->pledged)) ? floatval(trim($fields->pledged)) : null,
                'amountpaid' => isset($fields->paid) && !empty(trim($fields->paid)) ? floatval(trim($fields->paid)) : 0,
                'paymethod' => isset($fields->paymethod) && !empty(trim($fields->paymethod)) ? (int)trim($fields->paymethod) : null,
                'bank' => isset($fields->bank) && !empty(trim($fields->bank)) ? (int)trim($fields->bank) : null,
                'reference' => isset($fields->reference) && !empty(trim($fields->reference)) ? strtolower(trim($fields->reference)) : null,
            ];

            //validate
            if (is_null($data['date']) || is_null($data['pledger']) || is_null($data['category']) 
                || is_null($data['amountpledged'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Provide all required fields']);
                exit;
            }
           if($data['amountpledged'] < $data['amountpaid']){
                http_response_code(400);
                echo json_encode(['message' => 'Paid more than pledged']);
                exit;
            }
            
            if($data['amountpaid'] > 0){
                if(is_null($data['paymethod']) || is_null($data['bank']) || is_null($data['bank'])){
                    http_response_code(400);
                    echo json_encode(['message' => 'Provide all required fields']);
                    exit;
                }
            }

            if (!$this->pledgeModel->create($data)) {
                http_response_code(500);
                echo json_encode(['message' => 'Something went wrong while creating pledge']);
                exit;
            }

            echo json_encode(['success' => true]);
            exit;
            
        }
        else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function pay($id)
    {
        $form = 'Pledges';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->pledgeModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $pledge = $this->pledgeModel->getPledge($id);
        $paymethods = $this->pledgeModel->paymentMethods();
        $banks = $this->pledgeModel->getBanks();
        $data = [
            'pledge' => $pledge,
            'date' => '',
            'date_err' => '',
            'paymethods' => $paymethods,
            'paymethod' => '',
            'banks' => $banks,
            'bank' => '',
            'paid' => '',
            'paid_err' => '',
            'reference' => '',
            'ref_err' => ''
        ];
        if ($data['pledge']->congregationId != $_SESSION['congId'] || $data['pledge']->deleted == 1
            || $_SESSION['userType'] == 3 || $_SESSION['userType'] == 4) {
            redirect('pledges');
        }
        else{
            $this->view('pledges/pay',$data);
        }
        $this->view('pledges/pay',$data);
    }
    public function payment()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $paymethods = $this->pledgeModel->paymentMethods();
            $banks = $this->pledgeModel->getBanks();
            $data = [
                'id' => trim($_POST['id']),
                'pledge' => '',
                'pledger' => trim(strtolower($_POST['pledger'])),
                'balance' => trim($_POST['balance']),
                'date' => trim($_POST['date']),
                'date_err' => '',
                'paymethods' => $paymethods,
                'paymethod' => trim($_POST['paymethod']),
                'banks' => $banks,
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : NULL,
                'paid' => trim($_POST['paid']),
                'paid_err' => '',
                'reference' => trim($_POST['reference']),
                'ref_err' => ''
            ];
            $pledge  = $this->pledgeModel->getPledge($data['id']);
            if (empty($data['date'])) {
                $data['date_err'] = 'Select Date';
            }
            if (empty($data['paid'])) {
                $data['paid_err'] = 'Enter Payment';
            }
            else{
                if ($data['balance'] < $data['paid']) {
                    $data['paid_err'] = 'Payment Cannot Be More Than Balance';
                }
            }
            if ($data['paymethod'] > 1 && empty($data['reference'])) {
               $data['ref_err'] = 'Enter Payment Reference';
            }
            if (empty($data['date_err']) && empty($data['paid_err']) && empty($data['ref_err'])) {
                if ($this->pledgeModel->pay($data)) {
                   flash('pledge_msg','Payment Saved Successfully');
                   redirect('pledges');
                }
            }
            else{
                $this->view('pledges/pay',$data);
            }
        }
        else {
            redirect('pledges');
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
           $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           $data = [
                'id' => trim($_POST['id']),
                'pledger' => trim(strtolower($_POST['pledger']))
           ];
           if (!empty($data['id'])) {
               if ($this->pledgeModel->delete($data)) {
                    flash('pledge_msg','Plegde Deleted Successfully');
                    redirect('pledges');
               }
           }
        }
    }
}