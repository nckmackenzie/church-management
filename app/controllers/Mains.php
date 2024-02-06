<?php

class Mains extends Controller {
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->reusemodel = $this->model('Reusables');
        $this->congregationmodel = $this->model('Congregation');
    }
    public function index()
    {
        $data = [
            'congregations' => $this->reusemodel->GetCongregations(),
        ];
        $this->view('mains/index',$data);
    }

    public function changecongregation()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $congid = isset($_POST['congregation']) && !empty(trim($_POST['congregation'])) ? trim($_POST['congregation']) : null;
            $congregation = $this->congregationmodel->getCongregation($congid);
            //unset congregation session vars
            unset($_SESSION['isParish']);
            unset($_SESSION['congId']);
            unset($_SESSION['congName']);
            //reset congregation session vars
            $_SESSION['isParish'] = converttobool($congregation->IsParish);
            $_SESSION['congId'] = $congid;
            $_SESSION['congName'] = !is_null($congregation->CongregationName) ? strtoupper($congregation->CongregationName) : "" ;
            flash('main_msg','Successfully changed to '.ucwords($_SESSION['congName']));
            redirect('mains');
        }
        else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}