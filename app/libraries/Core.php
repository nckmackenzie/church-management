<?php
/* 
* App Core Class
* Creates URL & loads core controller
* URL FORMAT - /controller/method/params
*/

class Core {
    protected $currentController = 'Users';
    protected $currentMethod ='index';
    protected $params = [];

    public function __construct()
    {
        // print_r($this->getUrl());
        $url = $this->getUrl();
        //Look for contoller for first index
        if (file_exists('../app/controllers/'.ucwords($url[0]).'.php')) {
            #if exists set as currrentController
            $this->currentController = ucwords($url[0]);
            //unset zero index
            unset($url[0]);
        }
        //Require the controller
        require_once '../app/controllers/'.$this->currentController . '.php';
        //create new instance of controller
        $this->currentController = new $this->currentController;
        //check for second part of url --the method eg edit/delete/create etc
        if (isset($url[1])) {
            # see if method exists in controller
            if (method_exists($this->currentController,$url[1])) {
               $this->currentMethod = $url[1];
               unset($url[1]); //unset 1 index
            }
        }
        //get params
        $this->params =$url ? array_values($url) : [];
        #Call a callback with array of params
        call_user_func_array([$this->currentController,$this->currentMethod],$this->params);
    }

    public function getUrl()
    {
       if (isset($_GET['url'])) {
          $url = rtrim($_GET['url'],'/');
          $url = filter_var($url,FILTER_SANITIZE_URL);
          $url = explode('/',$url);
          return $url;
       }
    }
}