<?php

class Products extends Controller
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        if((int)$_SESSION['userType'] > 2){
            redirect('users/deniedaccess');
            exit;
        }

        $this->productmodel = $this->model('Product');
    }

    public function index()
    {
        $data = [
            'title' => 'Add Product',
            'products' => $this->productmodel->GetProducts(),
        ];
        $this->view('products/index',$data);
        exit;
    }

    public function add()
    {
        $data = [
            'title' => 'Add Product',
            'glaccounts' => $this->productmodel->GetGlAccounts(),
            'touched' => false,
            'isedit' => false,
            'id' => '',
            'productname' => '',
            'description' => '',
            'rate' => '',
            'glaccount' => '',
            'productname_err' => '',
            'rate_err' => '',
            'glaccount_err' => '',
        ];
        $this->view('products/add',$data);
        exit;
    }

    public function createupdate()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $_POST = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW);
            $data = [
                'title' => converttobool($_POST['isedit']) ? 'Edit Product' : 'Add Product',
                'glaccounts' => $this->productmodel->GetGlAccounts(),
                'touched' => true,
                'isedit' => converttobool($_POST['isedit']),
                'id' => !empty(trim($_POST['id'])) ? trim($_POST['id']) : '',
                'productname' => !empty(trim($_POST['productname'])) ? trim($_POST['productname']) : '',
                'description' => !empty(trim($_POST['description'])) ? trim($_POST['description']) : '',
                'rate' => !empty(trim($_POST['rate'])) ? trim($_POST['rate']) : '',
                'glaccount' => converttobool($_POST['isedit']) ? '' : (!empty(trim($_POST['glaccount'])) ? trim($_POST['glaccount']) : ''),
                'productname_err' => '',
                'rate_err' => '',
                'glaccount_err' => '',
            ];
            //validation
            if(empty($data['productname'])){
                $data['productname_err'] = 'Enter product name';
            }else{
                if((int)$this->productmodel->CheckExist($data['productname'],$data['id']) > 0){
                    $data['productname_err'] = 'Product name exists';
                }
            }
            if(empty($data['rate'])){
                $data['rate_err'] = 'Enter product rate';
            }
            if(empty($data['glaccount']) && !$data['isedit']){
                $data['glaccount_err'] = 'Select G/L account';
            }

            if(!empty($data['productname_err']) || !empty($data['rate_err']) || !empty($data['glaccount_err'])){
                $this->view('products/add',$data);
                exit;
            }

            if(!$this->productmodel->CreateUpdate($data)){
                flash('product_msg','Unable to create product. Retry or contact admin!','alert custom-danger alert-dismissible fade show');
                redirect('products');
                exit;
            }

            flash('product_msg','Product saved successfully');
            redirect('products');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function edit($id)
    {
        $product = $this->productmodel->GetProduct($id);
        checkcenter($product->congregationId);
        $data = [
            'title' => 'Edit Product',
            'glaccounts' => $this->productmodel->GetGlAccounts(),
            'touched' => false,
            'isedit' => true,
            'id' => $product->ID,
            'productname' => strtoupper($product->productName),
            'description' => strtoupper($product->description),
            'rate' => $product->rate,
            'glaccount' => $product->accountId,
            'productname_err' => '',
            'rate_err' => '',
            'glaccount_err' => '',
        ];
        $this->view('products/add',$data);
        exit;
    }

    public function delete()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $id = isset($_POST['id']) ? htmlentities(trim($_POST['id'])) : '';

            if(empty($id)){
                flash('product_msg','Unable to get selected product!','alert custom-danger alert-dismissible fade show');
                redirect('products');
                exit;
            }

            if(!$this->productmodel->Delete($id)){
                flash('product_msg','Unable to get delete product!','alert custom-danger alert-dismissible fade show');
                redirect('products');
                exit;
            }

            flash('product_msg','Product deleted successfully');
            redirect('products');
            exit;

        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}