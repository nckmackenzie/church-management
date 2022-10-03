<?php
class Banks extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else{
            $this->bankModel = $this->model('Bank');
        }
    }
    public function index()
    {
        $form = 'Banks';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->bankModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $banks = $this->bankModel->getBanks();
        $data = ['banks' => $banks];
        $this->view('banks/index',$data);
    }
    public function add()
    {
        $form = 'Banks';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->bankModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
       $data = [
           'bankname' => '',
           'account' => '',
           'openingbal' => '',
           'asof' => '',
           'name_err' => '',
           'asof_err' => '',
           'account_err' => '',
       ];
       $this->view('banks/add',$data);
    }
    public function create()
    {
        //check if post
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'bankname' => trim(strtolower($_POST['bankname'])),
                'account' => trim(strtolower($_POST['account'])),
                'openingbal' => trim($_POST['openingbal']),
                'asof' => !empty($_POST['asof']) ? trim($_POST['asof']) : NULL,
                'name_err' => '',
                'asof_err' => '',
                'account_err' => '',
            ];
            //validate
            if (empty($data['bankname'])) {
                $data['name_err'] = 'Enter Bank Name';
            }
            if (!empty($data['openingbal']) && empty($data['asof'])) {
                $data['asof_err'] = 'Select Opening Balance Date';
            }
            if (!empty($data['account'])) {
                if (!$this->bankModel->checkExists($data['account'],'')) {
                    $data['account_err'] = 'Account Already Exists';
                }  
            }
            if (empty($data['name_err']) && empty($data['asof_err']) && empty($data['account_err'])) {
                if ($this->bankModel->create($data)) {
                    flash('bank_msg','Bank Created Successfully!');
                    redirect('banks');
                }
                else{
                    flash('bank_msg','Something Went Wrong!','alert custom-danger');
                    redirect('banks');
                }
            }
            else{
                $this->view('banks/add',$data);
            }
        }
        else{
            $data = [
                'bankname' => '',
                'account' => '',
                'openingbal' => '',
                'asof' => '',
                'name_err' => '',
                'asof_err' => '',
                'account_err' => '',
            ];
            $this->view('banks/add',$data);
        }
    }
    public function edit($id)
    {
        $form = 'Banks';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->bankModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $bank = $this->bankModel->getbank($id);
        $data = [
            'bank' => $bank,
            'name_err' => '',
            'account_err' => ''
        ];
        //check if congregation is same
        if ($data['bank']->congregationId != $_SESSION['congId']) {
            redirect('banks');
        }
        elseif ($data['bank']->congregationId == $_SESSION['congId'] || $_SESSION['userType'] > 2) {
            $this->view('banks/edit',$data);
        }
    }
    public function update()
    {
         //check if post
         if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $bank = $this->bankModel->getbank($_POST['id']);
            $data = [
                'bank' => $bank,
                'id' => trim($_POST['id']),
                'bankname' => trim(strtolower($_POST['bankname'])),
                'account' => trim(strtolower($_POST['account'])),
                'name_err' => '',
                'account_err' => '',
            ];
        //    print_r($data['bank']);
            //validate
            if (empty($data['bankname'])) {
                $data['name_err'] = 'Enter Bank Name';
            }
            if (!empty($data['account'])) {
                if (!$this->bankModel->checkExists($data['account'],$data['id'])) {
                    $data['account_err'] = 'Account Already Exists';
                }  
            }
            if (empty($data['name_err']) && empty($data['account_err'])) {
                if ($this->bankModel->update($data)) {
                    flash('bank_msg','Bank Updated Successfully!');
                    redirect('banks');
                }
                else{
                    flash('bank_msg','Something Went Wrong!','alert custom-danger');
                    redirect('banks');
                }
            }
            else{
                $this->view('banks/edit',$data);
            }
        }
        else{
            $data = [
                'id' => '',
                'bankname' => '',
                'account' => '',
                'name_err' => '',
                'account_err' => '',
            ];
            $this->view('banks/edit',$data);
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'bankname' => trim(strtolower($_POST['bankname'])),
                'account' => trim(strtolower($_POST['account'])),
            ];
           
            if (isset($data['id'])) {
                if ($this->bankModel->delete($data)) {
                    flash('bank_msg','Bank Deleted Successfully!');
                    redirect('banks');
                }
                else{
                    flash('bank_msg','Something Went Wrong!','alert custom-danger');
                    redirect('banks');
                }
            }
            else{
                $this->view('banks/edit',$data);
            }
        }
        else{
            redirect('banks');
        }
    }
}