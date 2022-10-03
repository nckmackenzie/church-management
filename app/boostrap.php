<?php
//load config 
require_once 'config/config.php';
//load Helpers
require_once 'helpers/url_helper.php';
require_once 'helpers/session_handler.php';
require_once 'helpers/sms_helper.php';

//AUTOLOAD CORE LIBRARIES
spl_autoload_register(function($className){
    require_once 'libraries/' . $className . '.php';
});