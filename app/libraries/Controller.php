<?php
/*
 * Base Controller
 * Loads the model and views
 */
class Controller {
    public function model($model)
    {
        //require model file
        require_once '../app/models/' . $model . '.php';

        #new instance of model
        return new $model;
    }

    //load view
    public function view($view, $data = [])
    {
        //check for view file
        if (file_exists('../app/views/' . $view . '.php')) {
            require_once '../app/views/' . $view . '.php';
        }
        else{
            //view doesn't exists
            die('View Doesn\'t Exist');
        }
    }
}