<?php
class Congregations extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2) {
            redirect('users/deniedaccess');
        }else{
            $this->congregationModel = $this->model('Congregation');
        }
        
    }
    public function index()
    {
        if ($_SESSION['isParish'] != 1) {
            redirect('mains');
        }
        else{
            $congregations = $this->congregationModel->getCongregations();
            $data = ['congregations' => $congregations];
            $this->view('congregations/index',$data);
        }
    }
    public function add()
    {
        if ($_SESSION['isParish'] != 1) {
            redirect('mains');
        }
        else{
            $data = [
                'congregationname' => '',
                'contact' => '',
                'email' => '',
                'prefix' => '',
                'address' => '',
                'aboutus' => '',
                'inauguration' => '',
                'started' => '',
                'type' => '',
                'dedication' => '',
                'foundation' => '',
                'name_err' => '',
                'type_err' => '',
                'started_err'=> ''
            ];
            $this->view('congregations/add',$data);
        }
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'congregationname' => trim($_POST['congregationname']),
                'contact' => trim($_POST['contact']),
                'email' => trim($_POST['email']),
                'prefix' =>trim($_POST['prefix']),
                'address' => trim($_POST['address']),
                'aboutus' => trim($_POST['aboutus']),
                'inauguration' => trim($_POST['inauguration']),
                'started' => !empty($_POST['started']) ? trim($_POST['started']) : '',
                'type' => !empty($_POST['type']) ? trim($_POST['type']) : '',
                'dedication' => !empty($_POST['dedication']) ? trim($_POST['dedication']) : '',
                'foundation' => !empty($_POST['foundation']) ? trim($_POST['foundation']) : '',
                'name_err' => '',
                'type_err' => '',
                'started_err'=> ''
            ];
            if (empty($data['congregationname'])) {
                $data['name_err'] = 'Enter Congregation Name';
            }
            else{
                if (!$this->congregationModel->checkExists(strtolower($data['congregationname']))) {
                    $data['name_err'] = 'Congregation Name Exists';
                }
            }
            
            if(empty($data['type']))    {
                $data['type_err'] = 'Select sanctuary type';
            }

            if(empty($data['started']))    {
                $data['started_err'] = 'Select sanctuary type';
            }
            
            if (empty($data['name_err']) && empty($data['type_err']) && empty($data['started_err'])) {
                if ($this->congregationModel->create($data)) {
                    flash('congregation_msg','Congregation Added Successfully');
                    redirect('congregations');
                }
                else{
                    flash('congregation_msg','Something Went Wrong','alert custom-danger');
                    redirect('congregations');
                }
            }
            else{
                $this->view('congregations/add',$data);
            }
        }
        else {
            $data = [
                'congregationname' => '',
                'contact' => '',
                'email' => '',
                'address' => '',
                'aboutus' => '',
                'name_err' => ''
            ];
            $this->view('congregations/add',$data);
        }
    }
    public function edit($id)
    {
        $congregation = $this->congregationModel->getCongregation($id);
        $data =['congregation' => $congregation];
        if ($data['congregation']->ID != $_SESSION['congId'] && $_SESSION['isParish'] !=1 ) {
            redirect('mains');
        }
        else{
            $this->view('congregations/edit',$data);
        }
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST= filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);//SANITIZE
            $data = [
                'id' => $_POST['id'],
                'congregationname' => trim(strtolower($_POST['congregationname'])),
                'contact' => $_POST['contact'],
                'email' => trim($_POST['email']),
                'address' => trim(strtolower($_POST['address'])),
                'aboutus' => trim(strtolower($_POST['aboutus'])),
                'inauguration' => trim($_POST['inauguration']),
                'started' => !empty($_POST['started']) ? trim($_POST['started']) : '',
                'type' => !empty($_POST['type']) ? trim($_POST['type']) : '',
                'dedication' => !empty($_POST['dedication']) ? trim($_POST['dedication']) : '',
                'foundation' => !empty($_POST['foundation']) ? trim($_POST['foundation']) : '',
                'congregationname_err' =>'',
                'type_err' => '',
                'started_err'=> ''
            ];
            
            if (empty($data['congregationname'])) {
                $data['congregationname_err'] = 'Enter Congregation Name';
            }
            
            if(empty($data['type']))    {
                $data['type_err'] = 'Select sanctuary type';
            }

            if(empty($data['started']))    {
                $data['started_err'] = 'Select sanctuary type';
            }
            
            if (empty($data['congregationname_err']) && empty($data['type_err']) && empty($data['started_err'])) {
                if ($this->congregationModel->update($data)) {
                    redirect('mains');
                }
            }
            else{
                $this->view('congregations/edit',$data);
            }
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST= filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);//SANITIZE
            $data = [
                'id' => $_POST['id'],
                'congregationname' => trim(strtolower($_POST['congregationname'])),
            ];
            if (!empty($data['id'])) {
                if ($this->congregationModel->delete($data)) {
                    flash('congregation_msg','Congregation Deleted Successfully');
                    redirect('congregations');
                }
            }
            else{
               redirect('congregations');
            }
        }
    }
}
