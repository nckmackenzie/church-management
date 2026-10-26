<?php
class Pledges extends Controller
{
    private $authmodel;
    private $pledgeModel;
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){redirect('users');exit;}
        $this->authmodel=$this->model('Auth');checkrights($this->authmodel,'pledges');$this->pledgeModel=$this->model('Pledge');
    }
    private function lists(): array {return ['paymethods'=>$this->pledgeModel->paymentMethods(),'banks'=>$this->pledgeModel->getBanks(),'cashaccounts'=>$this->pledgeModel->getCashAccounts(),'incomeaccounts'=>$this->pledgeModel->getIncomeAccounts()];}
    private function e($v): string {return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
    public function index(){$this->view('pledges/index',['pledges'=>$this->pledgeModel->index()]);}
    public function add()
    {
        $data=array_merge($this->lists(),['category'=>'','pledger'=>'','date'=>date('Y-m-d'),'amountpledged'=>'','amountpaid'=>'','paymethod'=>'','bank'=>'','cashaccount'=>'','incomeaccount'=>'','reference'=>'','errors'=>[]]);$this->view('pledges/add',$data);
    }
    public function getpledger()
    {
        if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);echo json_encode(['message'=>'Method not allowed']);exit;}
        $raw=trim((string)($_GET['category']??''));if(!in_array($raw,['1','2','3'],true)){http_response_code(400);echo json_encode(['message'=>'Select pledger category']);exit;}
        $out='<option value="" selected disabled>Select pledger</option>';foreach($this->pledgeModel->getPledger((int)$raw) as $p){$out.='<option value="'.(int)$p->ID.'">'.$this->e($p->pledger).'</option>';}
        header('Content-Type: application/json');echo json_encode($out);exit;
    }
    public function create()
    {
        if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['message'=>'Method not allowed']);exit;}$f=json_decode(file_get_contents('php://input'),true);if(!is_array($f)){$f=$_POST;}
        $data=['category'=>$f['category']??'','pledger'=>$f['pledger']??'','date'=>trim((string)($f['date']??'')),'amountpledged'=>trim((string)($f['pledged']??'')),'amountpaid'=>trim((string)($f['paid']??'')),'incomeaccount'=>$f['incomeaccount']??'','paymethod'=>$f['paymethod']??'','cashaccount'=>$f['cashaccount']??'','bank'=>$f['bank']??'','reference'=>trim((string)($f['reference']??''))];
        $r=$this->pledgeModel->create($data);header('Content-Type: application/json');if(!$r['success']){http_response_code(422);echo json_encode(['success'=>false,'message'=>implode(' ', $r['errors']),'errors'=>$r['errors']]);exit;}echo json_encode($r);exit;
    }
    public function pay($id)
    {
        $pledge=$this->pledgeModel->getPledge($id);if(!$pledge){flash('pledge_msg','Pledge not found for this congregation','alert alert-danger');redirect('pledges');exit;}
        $data=array_merge($this->lists(),['pledge'=>$pledge,'date'=>date('Y-m-d'),'paymethod'=>'','bank'=>'','cashaccount'=>'','paid'=>'','reference'=>'','incomeaccount'=>'','errors'=>[]]);$this->view('pledges/pay',$data);
    }
    public function payment()
    {
        if($_SERVER['REQUEST_METHOD']!=='POST'){redirect('pledges');exit;}$_POST=filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW)?:$_POST;
        $data=['id'=>$_POST['id']??'','date'=>trim((string)($_POST['date']??'')),'paid'=>trim((string)($_POST['paid']??'')),'incomeaccount'=>$_POST['incomeaccount']??'','paymethod'=>$_POST['paymethod']??'','cashaccount'=>$_POST['cashaccount']??'','bank'=>$_POST['bank']??'','reference'=>trim((string)($_POST['reference']??''))];$r=$this->pledgeModel->pay($data);
        if($r['success']){flash('pledge_msg','Payment Saved Successfully');redirect('pledges');exit;}$pledge=$this->pledgeModel->getPledge($data['id']);if(!$pledge){flash('pledge_msg',implode(' ',$r['errors']),'alert alert-danger');redirect('pledges');exit;}$this->view('pledges/pay',array_merge($this->lists(),$data,['pledge'=>$pledge,'errors'=>$r['errors']]));
    }
    public function delete()
    {
        if($_SERVER['REQUEST_METHOD']!=='POST'){redirect('pledges');exit;}if(in_array((int)$_SESSION['userType'],[3,4],true)){flash('pledge_msg','You are not allowed to delete pledges','alert alert-danger');redirect('pledges');exit;}$r=$this->pledgeModel->delete(['id'=>$_POST['id']??'']);flash('pledge_msg',$r['success']?'Pledge Deleted Successfully':implode(' ',$r['errors']),$r['success']?'alert alert-success':'alert alert-danger');redirect('pledges');exit;
    }
}
