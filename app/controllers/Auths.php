<?php

class Auths extends Controller
{
    public function __construct()
    {
       $this->authmodel = $this->model('Auth');
    }
}