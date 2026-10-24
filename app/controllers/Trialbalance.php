<?php

/** Trial balance (task 9.3): FinancialReport on the journal; As Of Date only (decision 129). */
class Trialbalance extends Controller
{
    private $authmodel;
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'trial balance');
    }

    public function index()
    {
        $this->view('reports/trialbalance',[]);
        exit;
    }

    /** JSON (or format=xlsx): asof=Y-m-d, detailed=1 for every account with postings */
    public function getreport()
    {
        $db = (new Database)->pdo();
        $cong = (int)$_SESSION['congId'];
        TrialBalanceReport::serveReport($db,[$cong],'');
    }

    /** One drill level page (account ID only in the URL) */
    public function drill()
    {
        $this->view('reports/trialbalancedrill',[]);
        exit;
    }

    public function drillrpt()
    {
        $db = (new Database)->pdo();
        $cong = (int)$_SESSION['congId'];
        TrialBalanceReport::serveDrill($db,[$cong],'');
    }

    /** Old name-based drill URLs: nothing is queried any more */
    public function report()
    {
        redirect('trialbalance');
        exit;
    }

    public function detailedreport()
    {
        IncomeStatementReport::fail('This link is no longer supported; open the trial balance again');
    }
}