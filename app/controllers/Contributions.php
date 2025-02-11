<?php
class Contributions extends Controller {
    private $authmodel;
    private $reusemodel;
    private $contributionModel;

    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'receipts');
        $this->reusemodel = $this->model('Reusables');
        $this->contributionModel = $this->model('Contribution');
    }
    public function index()
    {
        $contributions = $this->contributionModel->getContributions();
        $data = ['contributions' => $contributions];
        $this->view('contributions/index',$data);
        exit;
    }
    
    public function getreceiptno($date)
    {
        $yearId = $this->reusemodel->GetFiscalYear($date);
        $yearName = $this->reusemodel->GetYearName($yearId);
        if(!$this->reusemodel->CheckPrefixable($yearId)){
            return $this->contributionModel->GetReceiptNo($yearId);
        }else{
            return $yearName . '/' . $this->contributionModel->GetReceiptNo($yearId);
        }
    }

    public function receiptno()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $date = isset($_GET['txndate']) && !empty(trim($_GET['txndate'])) ? date('Y-m-d',strtotime($_GET['txndate'])) : date('Y-m-d', strtotime($_SESSION['processdate']));
            echo json_encode($this->getreceiptno($date));
        }
    }

    public function add()
    {
        $accounts = $this->reusemodel->GetChildAccounts(1);
        $date = date('Y-m-d', strtotime($_SESSION['processdate']));
        $paymethods = $this->reusemodel->PaymentMethods();
        $banks = $this->reusemodel->GetBanks();
        $categories = $this->contributionModel->getCategories();
        $data = [
            'accounts' => $accounts,
            'categories' => $categories,
            'congregations' => $this->reusemodel->GetCongregations(),
            'banks' => $banks,
            'paymethods' => $paymethods,
            // 'receiptno' => $this->contributionModel->receiptNo(),
            'receiptno' => $this->getreceiptno($date),
            'id' => '',
            'isedit' => false,
            'date' => $date,
            'paymethod' => '2',
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
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $category=trim($_GET['category']);
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
        //    $receiptNo = $this->contributionModel->receiptNo();
        //    $receiptNo = $this->getreceiptno();
           $accounts = $this->reusemodel->GetChildAccounts(1);
           $paymethods = $this->reusemodel->PaymentMethods();
           $banks = $this->reusemodel->GetBanks();
           $categories = $this->contributionModel->getCategories();
           $data = [
                // 'receiptno' => $receiptNo,
                'receiptno' => '',
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
                'description' => !empty(trim($_POST['description'])) ? trim($_POST['description']) : 'receipts for '.date('d-m-Y',strtotime($_POST['date'])),
                'table' => [],
                'accountsid' => $_POST['accountsid'],
                'accountsname' => $_POST['accountsname'],
                'amounts' => $_POST['amounts'],
                'categoriesid' => $_POST['categoriesid'],
                'categoriesname' => $_POST['categoriesname'],
                'contributorsid' => $_POST['contributorsid'],
                'contributorsname' => $_POST['contributorsname'],
                'subaccount' => $_POST['subaccount'],
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
                    'subaccount' => !empty(trim($data['subaccount'][$i])) && is_null($data['subaccount'][$i]) ? $data['subaccount'][$i] : null,
                ]);
            }

            $data['receiptno'] = $this->getreceiptno($data['date']);

           //validate
           if(empty($data['receiptno'])){
             $data['receipt_err'] = 'Enter receipt number';
           }else{
                if(!$this->contributionModel->checkreceiptno($data['receiptno'],$data['id'],$data['date'])){
                    $data['receipt_err'] = 'Receipt number exists';
                }
           }

           if ($data['paymethod'] > 1 && empty($data['bank'])) {
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
        //    }
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
            ];

            if($this->contributionModel->YearIsClosed($data['id'])){
                flash('contribution_msg','Cannot delete transactions for closed year','alert custom-danger alert-dismissible fade show');
                redirect('contributions');
                exit;
            }

            if (!empty($data['id'])) {
                if ($this->contributionModel->delete($data)) {
                    flash('contribution_msg','Deleted Successfully');
                    redirect('contributions');
                    exit;
                }
            }
        }
    }
    public function edit($id)
    {
       $header = $this->contributionModel->contributionHeader(trim($id));
       $details = $this->contributionModel->getContribution($id);
       $accounts = $this->reusemodel->GetChildAccounts(1);
       $paymethods = $this->reusemodel->PaymentMethods();
       $banks = $this->reusemodel->GetBanks();
       $categories = $this->contributionModel->getCategories();
       checkcenter($header->congregationId);
       if($this->reusemodel->CheckYearClosed($header->fiscalYearId)) :
         flash('contribution_msg','Cannot edit transactions for closed year','alert custom-danger alert-dismissible fade show');
         redirect('contributions');
         exit;
       endif; 
       $data = [
           'receiptno' => $header->receiptNo,
           'id' => $header->ID,
           'isedit' => true,
           'date' => date('Y-m-d',strtotime($header->contributionDate)),
           'congregations' => $this->reusemodel->GetCongregations(),
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
                'subaccount' => $detail->subaccount,
            ]);
        }
      
       if ($header->congregationId != $_SESSION['congId']) {
           redirect('contributions');
       }
       else{
           $this->view('contributions/add',$data);
       }
    }
}