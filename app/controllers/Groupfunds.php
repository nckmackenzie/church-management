<?php
class Groupfunds extends Controller
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        $this->fundmodel = $this->model('Groupfund');
    }

    public function index()
    {
        checkrights($this->fundmodel,'group fund requisition');
        $data = [
            'requests' => $this->fundmodel->GetRequests(),
        ];
        $this->view('groupfunds/index',$data);
        exit;
    }
}