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
}