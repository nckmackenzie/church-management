<?php
class Contributions extends Controller {
    private $authmodel;
    private $reusemodel;
    private $contributionModel;

    /** 'cash at hand', preselected as the receiving account of a cash receipt */
    const DEFAULT_CASH_ACCOUNT = 34;

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

    /** Number to show on the form (the real number is allocated when the receipt is saved). */
    public function getreceiptno($date,$id = null)
    {
        $yearId = $this->reusemodel->GetFiscalYear($date);
        if (!$yearId) {
            return '';
        }
        return !empty($id) && (int)$id > 0
            ? $this->contributionModel->GetReceiptNoForEdit((int)$id,$yearId)
            : $this->contributionModel->GetReceiptNo($yearId);
    }

    public function receiptno()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $date = isset($_GET['txndate']) && !empty(trim($_GET['txndate'])) ? date('Y-m-d',strtotime($_GET['txndate'])) : date('Y-m-d', strtotime($_SESSION['processdate']));
            echo json_encode($this->getreceiptno($date,$_GET['id'] ?? null));
        }
    }

    /** The cash account dropdown value: the wanted one if the congregation may use it, else 34 if listed, else none. */
    private function pickCashAccount($cashaccounts,$wanted)
    {
        $ids = array_map(function($a){ return (int)$a->ID; },$cashaccounts);
        if (!empty($wanted) && in_array((int)$wanted,$ids,true)) {
            return (int)$wanted;
        }
        return in_array(self::DEFAULT_CASH_ACCOUNT,$ids,true) ? self::DEFAULT_CASH_ACCOUNT : '';
    }

    public function add()
    {
        $accounts = $this->contributionModel->getIncomeAccounts();
        $date = date('Y-m-d', strtotime($_SESSION['processdate']));
        $paymethods = $this->reusemodel->PaymentMethods();
        $banks = $this->reusemodel->GetBanks();
        $categories = $this->contributionModel->getCategories();
        $cashaccounts = $this->contributionModel->getCashAccounts();
        $data = [
            'accounts' => $accounts,
            'categories' => $categories,
            'congregations' => $this->reusemodel->GetCongregations(),
            'banks' => $banks,
            'cashaccounts' => $cashaccounts,
            'cashaccount' => $this->pickCashAccount($cashaccounts,null),
            'paymethods' => $paymethods,
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
            'errors' => [],
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
           $cashaccounts = $this->contributionModel->getCashAccounts();
           $data = [
                'receiptno' => '',
                'id' => trim($_POST['id'] ?? ''),
                'isedit' => converttobool(trim($_POST['isedit'] ?? '')),
                'date' => !empty($_POST['date']) ? date('Y-m-d',strtotime($_POST['date'])) : date('Y-m-d'),
                'accounts' => $this->contributionModel->getIncomeAccounts(),
                'paymethods' => $this->reusemodel->PaymentMethods(),
                'paymethod' => trim($_POST['paymethod'] ?? ''),
                'banks' => $this->reusemodel->GetBanks(),
                'bank' => !empty($_POST['bank']) ? trim($_POST['bank']) : '',
                'cashaccounts' => $cashaccounts,
                'cashaccount' => !empty($_POST['cashaccount']) ? trim($_POST['cashaccount']) : '',
                'categories' => $this->contributionModel->getCategories(),
                'category' => 3,
                'reference' => trim($_POST['reference'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'table' => [],
                'accountsid' => (array)($_POST['accountsid'] ?? []),
                'accountsname' => (array)($_POST['accountsname'] ?? []),
                'amounts' => (array)($_POST['amounts'] ?? []),
                'categoriesid' => (array)($_POST['categoriesid'] ?? []),
                'categoriesname' => (array)($_POST['categoriesname'] ?? []),
                'contributorsid' => (array)($_POST['contributorsid'] ?? []),
                'contributorsname' => (array)($_POST['contributorsname'] ?? []),
                'subaccount' => (array)($_POST['subaccount'] ?? []),
                'errors' => [],
                'receipt_err' => '',
                'ref_err' => '',
                'bank_err' => '',
           ];

           // keep the lines on screen when the form comes back with errors
           for ($i=0; $i < count($data['accountsid']); $i++) {
                array_push($data['table'],[
                    'accountid' => $data['accountsid'][$i],
                    'accountname' => $data['accountsname'][$i] ?? '',
                    'amount' => $data['amounts'][$i] ?? '',
                    'categoryid' => $data['categoriesid'][$i] ?? '',
                    'categoryname' => $data['categoriesname'][$i] ?? '',
                    'contributorid' => $data['contributorsid'][$i] ?? '',
                    'contributorname' => $data['contributorsname'][$i] ?? '',
                    'subaccount' => isset($data['subaccount'][$i]) && !in_array(trim($data['subaccount'][$i]),['','0'],true) ? $data['subaccount'][$i] : null,
                ]);
            }

            // the cash account only applies to cash receipts, the bank to the other methods
            if ((int)$data['paymethod'] === 1) {
                $data['bank'] = '';
            } else {
                $data['cashaccount'] = '';
            }

            $result = $this->contributionModel->create($data);
            if ($result['success']) {
                flash('contribution_msg',($data['isedit'] ? 'Contribution Edited Successfully!' : 'Contribution Added Successfully!').' Receipt No '.htmlspecialchars($result['receiptno']).(!empty($result['renumbered']) ? '. '.htmlspecialchars($result['renumbered']) : ''));
                redirect('contributions');
                exit;
            }

            $data['errors'] = $result['errors'] ?: ['Something went wrong! The receipt was not saved. Contact admin'];
            $data['receiptno'] = $this->getreceiptno($data['date'],$data['isedit'] ? $data['id'] : null);
            $this->view('contributions/add',$data);
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
                'id' => (int)trim($_POST['id'] ?? ''),
            ];

            $result = $this->contributionModel->delete($data);
            if ($result['success']) {
                flash('contribution_msg','Deleted Successfully');
            } else {
                flash('contribution_msg',$result['errors'] ? implode('<br>',array_map('htmlspecialchars',$result['errors'])) : 'Something went wrong! Contact admin','alert custom-danger alert-dismissible fade show');
            }
            redirect('contributions');
            exit;
        }
    }
    public function edit($id)
    {
       $header = $this->contributionModel->contributionHeader(trim($id));
       if (!$header) {
         flash('contribution_msg','Contribution not found or already deleted','alert custom-danger alert-dismissible fade show');
         redirect('contributions');
         exit;
       }
       checkcenter($header->congregationId);
       if($this->reusemodel->CheckYearClosed($header->fiscalYearId)) :
         flash('contribution_msg','Cannot edit transactions for closed year','alert custom-danger alert-dismissible fade show');
         redirect('contributions');
         exit;
       endif;
       $details = $this->contributionModel->getContribution($id);
       $cashaccounts = $this->contributionModel->getCashAccounts();
       $data = [
           'receiptno' => $header->receiptNo,
           'id' => $header->ID,
           'isedit' => true,
           'date' => date('Y-m-d',strtotime($header->contributionDate)),
           'congregations' => $this->reusemodel->GetCongregations(),
           'accounts' => $this->contributionModel->getIncomeAccounts(),
           'banks' => $this->reusemodel->GetBanks(),
           'cashaccounts' => $cashaccounts,
           'cashaccount' => $this->pickCashAccount($cashaccounts,(int)$header->paymentMethodId === 1 ? $this->contributionModel->getReceivingAccount($header->ID,$header->congregationId) : null),
           'paymethods' => $this->reusemodel->PaymentMethods(),
           'categories' => $this->contributionModel->getCategories(),
           'paymethod' => $header->paymentMethodId,
           'bank' => $header->bankId,
           'reference' => strtoupper((string)$header->paymentReference),
           'description' => strtoupper((string)$header->narration),
           'category' => 3,
           'table' => [],
           'errors' => [],
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
                'subaccount' => (int)$detail->subaccount > 0 ? $detail->subaccount : null,
            ]);
        }

        $this->view('contributions/add',$data);
    }
}
