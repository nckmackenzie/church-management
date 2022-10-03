<?php

class Mains extends Controller {
    public function index()
    {
        $data = [];
        $this->view('mains/index',$data);
    }
}