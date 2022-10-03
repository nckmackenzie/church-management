<?php

class Pages extends Controller {
    public function __construct()
    {
       $this->pageModel = $this->model('Page');
    }

    public function index()
    {
        $congregations = $this->pageModel->getCongregation();
        $data = ['congregations' => $congregations];
       
       $this->view('pages/index',$data);
    }

    public function about()
    {
        $this->view('pages/about');
    }
    
}