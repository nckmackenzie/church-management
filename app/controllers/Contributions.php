<?php
class Contributions extends Controller {
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else{
            $this->contributionModel = $this->model('Contribution');
        }
    }
    public function index()
    {
        $form = 'Contributions';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->contributionModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $contributions = $this->contributionModel->getContributions();
        $data = ['contributions' => $contributions];
        $this->view('contributions/index',$data);
    }
    public function add()
    {
        $form = 'Contributions';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->contributionModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        
        $accounts = $this->contributionModel->getAccounts();
        $paymethods = $this->contributionModel->paymentMethods();
        $banks = $this->contributionModel->getBanks();
        $categories = $this->contributionModel->getCategories();
        $data = [
            'accounts' => $accounts,
            'categories' => $categories,
            'banks' => $banks,
            'paymethods' => $paymethods,
            'receiptno' => '',
            'id' => '',
            'isedit' => false,
            'date' => date('Y-m-d'),
            'paymethod' => '',
            'bank' => '',
            'category' => 3,
            'reference' =>'',
            'description' => '',
            'forgroup' => '',
            'table' => [],
            'receipt_err' => '',
            'ref_err' => '',
            'bank_err' => ''
        ];
        $this->view('contributions/add',$data);
    }
    public function getcontributor()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $category=trim($_POST['category']);
            $data = [
               'contributor' => ''
            ];
           
            if (!empty($category)) {
                $data['contributor'] = $this->contributionModel->getContributor($category);
                foreach ($data['contributor'] as $contributor) {
                    echo '<option value="'.$contributor->ID.'">'.$contributor->contributor.'</option>';
                }
            }
        }
    }
    public function create()
    {
       if ($_SERVER['REQUEST_METHOD'] == 'POST') {
           $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           $receiptNo = $this->contributionModel->receiptNo();
           $accounts = $this->contributionModel->getAccounts();
           $paymethods = $this->contributionModel->paymentMethods();
           $banks = $this->contributionModel->getBanks();
           $categories = $this->contributionModel->getCategories();
           $data = [
                'receiptno' => trim($_POST['receipt']),
                'id' => trim($_POST['id']),
                'isedit' => converttobool(trim($_POST['isedit'])),
                'date' => !empty($_POST['date']) ? date('Y-m-d',strtotime($_POST['date'])) : date('Y-m-d'),
                'accounts' => $accounts,
                'paymethods' => $paymethods,
                'paymethod' => trim($_POST['paymethod']),
                'banks' => $banks,
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'categories' => $categories,
                'category' => 3,
                'reference' => trim($_POST['reference']),
                'description' => trim($_POST['description']),
                'table' => [],
                'accountsid' => $_POST['accountsid'],
                'accountsname' => $_POST['accountsname'],
                'amounts' => $_POST['amounts'],
                'categoriesid' => $_POST['categoriesid'],
                'categoriesname' => $_POST['categoriesname'],
                'contributorsid' => $_POST['contributorsid'],
                'contributorsname' => $_POST['contributorsname'],
                'totalamount' => 0,
                'ref_err' => '',
                'bank_err' => '',
                'receipt_err' => '',
           ];

           if(count($data['accountsid']) == 0){
              exit();
           }

           for ($i=0; $i < count($data['accountsid']); $i++) { 
                $data['totalamount'] += $data['amounts'][$i];
                array_push($data['table'],[
                    'accountid' => $data['accountsid'][$i],
                    'accountname' => $data['accountsname'][$i],
                    'amount' => $data['amounts'][$i],
                    'categoryid' => $data['categoriesid'][$i],
                    'categoryname' => $data['categoriesname'][$i],
                    'contributorid' => $data['contributorsid'][$i],
                    'contributorname' => $data['contributorsname'][$i],
                ]);
            }

           //validate
           if(empty($data['receiptno'])){
             $data['receipt_err'] = 'Enter receipt number';
           }else{
                if(!$this->contributionModel->checkreceiptno($data['receiptno'],$data['id'],$data['date'])){
                    $data['receipt_err'] = 'Receipt number exists';
                }
           }

           if ($data['paymethod'] > 2 && empty($data['bank'])) {
               $data['bank_err'] = 'Select Bank';
           }
           if ($data['paymethod'] > 1 && empty($data['reference'])) {
               $data['ref_err'] = 'Enter Payment Reference';
           }
           if (empty($data['ref_err']) && empty($data['receipt_err'])) {
               if ($this->contributionModel->create($data)) {
                   flash('contribution_msg',$data['isedit'] ? 'Contribution Edited Successfully!' : 'Contribution Added Successfully!');
                   redirect('contributions');
               }
               else{
                   flash('contribution_msg','Something went wrong!','alert custom-danger');
                   redirect('contributions');
               }
           }
           else{
              $this->view('contributions/add',$data);
           }
       }
    }
    public function checkforgroup()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST =filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $account = trim($_POST['cont']);
            echo $this->contributionModel->getforgroup($account);
        }
    }
    public function approve()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'date' => trim($_POST['date']),
                'contributor' => trim($_POST['contributor'])
            ];
            if (!empty($data['id'])) {
                if ($this->contributionModel->approve($data)) {
                    flash('contribution_msg','Approved Successfully');
                    redirect('contributions');
                }
            }
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'date' => trim($_POST['date']),
                'contributor' => trim($_POST['contributor'])
            ];
            if (!empty($data['id'])) {
                if ($this->contributionModel->delete($data)) {
                    flash('contribution_msg','Deleted Successfully');
                    redirect('contributions');
                }
            }
        }
    }
    public function edit($id)
    {
       $header = $this->contributionModel->contributionHeader(trim($id));
       $details = $this->contributionModel->getContribution($id);
       $accounts = $this->contributionModel->getAccounts();
       $paymethods = $this->contributionModel->paymentMethods();
       $banks = $this->contributionModel->getBanks();
       $categories = $this->contributionModel->getCategories();
      
       $data = [
           'receiptno' => $header->receiptNo,
           'id' => $header->ID,
           'isedit' => true,
           'date' => date('Y-m-d',strtotime($header->contributionDate)),
           'accounts' => $accounts,
           'banks' => $banks,
           'paymethods' => $paymethods,
           'categories' => $categories,
           'paymethod' => $header->paymentMethodId,
           'bank' => $header->bankId,
           'reference' => strtoupper($header->paymentReference),
           'description' => strtoupper($header->narration),
           'category' => 3,
           'table' => [],
           'amount_err' => '',
           'desc_err' => '',
           'ref_err' => '',
           'bank_err' => '',
           'receipt_err' => '',
       ];

        foreach($details as $detail) {
            array_push($data['table'],[
                'accountid' => $detail->contributionTypeId,
                'accountname' => $detail->accountType,
                'amount' => $detail->amount,
                'categoryid' => $detail->category,
                'categoryname' => $detail->categoryname,
                'contributorid' => $detail->contributorid,
                'contributorname' => $detail->contributor,
            ]);
        }
      
       if ($header->congregationId != $_SESSION['congId'] || $_SESSION['userType'] > 2) {
           redirect('contributions');
       }
       else{
           $this->view('contributions/add',$data);
       }
    }
}