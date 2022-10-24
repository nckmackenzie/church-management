<?php
    class Customers extends Controller {
        public function __construct()
        {
            if (!isset($_SESSION['userId'])) {
                redirect('users');
                exit;
            }
            $this->authmodel = $this->model('Auth');
            checkrights($this->authmodel,'customers');
            $this->customerModel = $this->model('Customer');
        }
        public function index()
        {
            $customers = $this->customerModel->index();
            $data = [
                'customers' => $customers
            ];
            $this->view('customers/index',$data);
        }
        public function add()
        {
            $data = [
                'customername' => '',
                'contact' => '',
                'address' => '',
                'pin' => '',
                'email' => '',
                'contactperson' => '',
                'customername_err' => '',
                'contactperson_err' => '',
            ];
            $this->view('customers/add',$data);
        }
        public function create()
        {
            //check method is post
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                //SANTIZE STRING
                $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
                $data = [
                    'customername' => trim(strtolower($_POST['customername'])),
                    'contact' => trim($_POST['contact']),
                    'address' => trim(strtolower($_POST['address'])),
                    'pin' => trim(strtolower($_POST['pin'])),
                    'email' => trim($_POST['email']),
                    'contactperson' => trim(strtolower($_POST['contactperson'])),
                    'customername_err' => '',
                    'contactperson_err' => '',
                ];
                //validate
                if (empty($data['customername'])) {
                    $data['customername_err'] = 'Enter Customer Name';
                }
                else{
                    if (!$this->customerModel->checkExists($data['customername'])) {
                        $data['name_err'] ='District Exists';
                    }
                }
                if (empty($data['contactperson'])) {
                    $data['contactperson_err'] = 'Enter Contact Person';
                }
                if (empty($data['customername_err']) && empty($data['contactperson_err'])) {
                    if ($this->customerModel->create($data)) {
                        flash('customer_msg','Customer Added Successfully!');
                        redirect('customers');
                    }
                    else{
                        flash('customer_msg','Something Went Wrong!','alert alert-danger');
                        redirect('customers');
                    }
                }
                else{
                    $this->view('customers/add',$data);
                }
            }
        }
        public function edit($id)
        {
            $customer = $this->customerModel->getCustomer($id);
            $data = ['customer' => $customer];
            //check if congregation is same
            if ($data['customer']->congregationId != $_SESSION['congId']) {
                redirect('customers');
            }
            elseif ($data['customer']->congregationId == $_SESSION['congId'] || $_SESSION['userType'] > 2) {
                $this->view('customers/edit',$data);
            }
        }
        public function update()
        {
            //check method is post
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                //SANTIZE STRING
                $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
                $data = [
                    'id' => $_POST['id'],
                    'customername' => trim(strtolower($_POST['customername'])),
                    'contact' => trim($_POST['contact']),
                    'address' => trim(strtolower($_POST['address'])),
                    'pin' => trim(strtolower($_POST['pin'])),
                    'email' => trim($_POST['email']),
                    'contactperson' => trim(strtolower($_POST['contactperson'])),
                    'customername_err' => '',
                    'contactperson_err' => '',
                ];
                //validate
                if (empty($data['customername'])) {
                    $data['customername_err'] = 'Enter Customer Name';
                }
               
                if (empty($data['contactperson'])) {
                    $data['contactperson_err'] = 'Enter Contact Person';
                }
                if (empty($data['customername_err']) && empty($data['contactperson_err'])) {
                    if ($this->customerModel->update($data)) {
                        flash('customer_msg','Customer Updated Successfully!');
                        redirect('customers');
                    }
                    else{
                        flash('customer_msg','Something Went Wrong!','alert alert-danger');
                        redirect('customers');
                    }
                }
                else{
                    $this->view('customers/edit',$data);
                }
            }
            else{
                $data = [
                    'id' => $_POST['id'],
                    'customername' => trim(strtolower($_POST['customername'])),
                    'contact' => trim($_POST['contact']),
                    'address' => trim(strtolower($_POST['address'])),
                    'pin' => trim(strtolower($_POST['pin'])),
                    'email' => trim($_POST['email']),
                    'contactperson' => trim(strtolower($_POST['contactperson'])),
                    'customername_err' => '',
                    'contactperson_err' => '',
                ];
                $this->view('customers/edit',$data);
            }
        }
        public function delete()
        {
            //check method is post
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                //SANTIZE STRING
                $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
                $data = [
                    'id' => $_POST['id'],
                    'customername' => trim(strtolower($_POST['customername'])),
                ];
                //validate
                if (isset($data['id'])) {
                    if ($this->customerModel->delete($data)) {
                        flash('customer_msg','Customer Deleted Successfully!');
                        redirect('customers');
                    }
                    else{
                        flash('customer_msg','Cannot Delete Selected Customer!',
                              'alert custom-danger alert-dismissible fade show');
                        redirect('customers');
                    }
                }
                else{
                    $this->view('customers/index',$data);
                }
            }
            else{
                $data = [
                    'id' => $_POST['id'],
                    'customername' => trim(strtolower($_POST['customername'])),
                ];
                $this->view('customers/index',$data);
            }
        }
    }