<?php
class Pledges extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else{
            $this->pledgeModel = $this->model('Pledge');
        }
    }
    public function index()
    {
        $form = 'Pledges';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->pledgeModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $pledges = $this->pledgeModel->index();
        $data = ['pledges' => $pledges];
        $this->view('pledges/index',$data);
    }
    public function add()
    {
        $form = 'Pledges';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->pledgeModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $pledgers = $this->pledgeModel->getPledger(1);
        $paymethods = $this->pledgeModel->paymentMethods();
        $banks = $this->pledgeModel->getBanks();
        $data = [
            'category' => '',
            'pledgers' => $pledgers,
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
            'ref_err' => ''
        ];
        $this->view('pledges/add',$data);
    }
    public function getpledger()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST =filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $category = trim($_POST['category']);
            $data = [
                'pledgers' => ''
            ];
            if (!empty($category)) {
                $data['pledgers'] = $this->pledgeModel->getPledger($category);
                foreach ($data['pledgers'] as $pledger) {
                    echo '<option value="'.$pledger->ID.'">'.$pledger->pledger.'</option>';
                }
            }
        }
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            
            $paymethods = $this->pledgeModel->paymentMethods();
            $banks = $this->pledgeModel->getBanks();
            $data = [
                'category' => trim($_POST['category']),
                'pledgers' => '',
                'pledger' => trim($_POST['pledger']),
                'pledgername' => trim($_POST['pledgername']),
                'date' => trim($_POST['date']),
                'date_err' => '',
                'amountpledged' => trim($_POST['amountpledged']),
                'pledged_err' => '',
                'amountpaid' => trim($_POST['amountpaid']),
                'paid_err' => '',
                'paymethods' => $paymethods,
                'paymethod' => !empty($_POST['paymethod']) ? trim($_POST['paymethod']) : NULL,
                'banks' => $banks,
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : NULL,
                'bank_err' => '',
                'reference' => !empty($_POST['reference']) ? trim($_POST['reference']) : NULL,
                'ref_err' => ''
            ];
            $pledgers = $this->pledgeModel->getPledger($data['category']);
            $data['pledgers'] = $pledgers;
            //validate
            if (empty($data['date'])) {
                $data['date_err'] = 'Select Date';
            }
            if (empty($data['amountpledged'])) {
                $data['pledged_err'] = 'Enter Amount Pledged';
            }
            if (!empty($data['amountpaid'])) {
                if ($data['amountpaid'] > $data['amountpledged']) {
                   $data['paid_err'] = 'Cannot Pay More Than Pledged';
                }
            }
            if ($data['paymethod'] > 2 && (empty($data['bank']) || $data['bank'] == NULL)) {
                $data['bank_err'] = 'Select Bank';
            }
            if ($data['paymethod'] > 1 && empty($data['reference'])) {
                $data['ref_err'] = 'Enter Payment Reference';
            }
            if (empty($data['date_err']) && empty($data['pledged_err']) && empty($data['paid_err'])
                && empty($data['bank_err']) && empty($data['ref_err'])) {
               if ($this->pledgeModel->create($data)) {
                   flash('pledge_msg','Pledge Added Successfully!');
                   redirect('pledges');
               }
            }
            else{
                $this->view('pledges/add',$data);
            }
        }
        else{
            redirect('pledges');
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