<?php
class Users extends Controller{
    public function __construct()
    {
        // if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2) {
        //     redirect('');
        // }
        // else{
            $this->userModel = $this->model('User');
        // }
    }
    public function index()
    {
        // if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2) {
        //         redirect('');
        // }else{
            $congregations = $this->userModel->getCongregation();
            $data = [
                'congregations' => $congregations, 
                'userid' => '',
                'password' => '',
                'congregation' => '',
                'userid_err' => '',
                'password_err' => ''
            ];
                
            $this->view('users/index',$data);
        // }
    }
    public function register()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2) {
            redirect('');
        }else{
            $districts = $this->userModel->getDistricts();
            $data = [
                'districts' => $districts,
                'userid' => '',
                'username' => '',
                'usertype' => '',
                'active' => '',
                'contact' => '',
                'district' => '',
                'userid_err' =>'',
                'username_err' =>'',
                'usertype_err' =>'',
                'contact_err' =>'',
            ];
            $this->view('users/register',$data);
        }
    }
    public function create(){
        //CHECK METHOD
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            //validate errors
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           
            $districts = $this->userModel->getDistricts();
            $data = [
                'districts' => $districts,
                'userid' => trim(strtolower($_POST['userid'])),
                'username' => trim(strtolower($_POST['username'])),
                'usertype' => $_POST['usertype'],
                'active' => $_POST['active'],
                'contact' => $_POST['contact'],
                'district' => !empty($_POST['district']) ? $_POST['district'] : NULL,
                'userid_err' =>'',
                'username_err' =>'',
                'usertype_err' =>'',
                'contact_err' =>'',
            ];
            if (empty($data['userid'])) {
                $data['userid_err'] = 'Enter User Id';
            }
            if (empty($data['username'])) {
                $data['username_err'] = 'Enter User Name';
            }
            if (empty($data['contact'])) {
                $data['contact_err'] = 'Enter Contact';
            }
            if (empty($data['userid_err']) && empty($data['username_err']) && empty($data['contact_err'])) {
                //hash password
                $random = substr(md5(mt_rand()),0,7);
                $hashed =  password_hash($random,PASSWORD_DEFAULT);
                //register
                if ($this->userModel->create($data,$hashed)) {
                    $msg = 'Hi to access the PCEA Kalimoni System,click on the link provided herein.Login Credentials are: UserID is '.$data['userid'] . ' Password is '.$random.' then select your congregation from the dropdown. https://pceakalimoniparish.or.ke/app';
                    $countryPrexix ='+254';
                    $sb = substr($data['contact'],1);
                    $full = $countryPrexix . $sb;
                    sendLink($full,$msg);
                    redirect('users/all');
                }
                else{
                    die('Something Went Wrong');
                }
                
            }
            else{
                $this->view('users/register',$data);
            }
        }    
        else{
           
            $districts = $this->userModel->getDistricts();
            $data = [
                'districts' => $districts,
                'userid' => '',
                'username' => '',
                'usertype' => '',
                'password' => '',
                'active' => '',
                'contact' => '',
                'districtid' => '',
                'userid_err' =>'',
                'username_err' =>'',
                'usertype_err' =>'',
                'district_err' =>'',
                'pwd_err' =>'',
                'contact_err' =>'',
            ];
        }
    }
    public function login()
    {
        $congregations = $this->userModel->getCongregation();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'congregations' => $congregations,
                'userid' => trim(strtolower($_POST['userid'])),
                'password' => trim($_POST['password']),
                'congregation' => $_POST['congregation'],
                'userid_err' => '',
                'password_err' => ''
            ];
            if (empty($data['userid'])) {
               $data['userid_err'] = 'Enter User ID';
            }
            else{
                if ($this->userModel->checkUserAvailability($data)) {
                    # code...
                }
                else{
                    $data['userid_err'] = 'User Doesn\'t Exists Or Is Inactive';
                }
            }
            if (empty($data['password'])) {
                $data['password_err'] = 'Enter Password';
            }
            
            //if no error
            if (empty($data['userid_err']) && empty($data['password_err'])) {
                $loggedInUser  = $this->userModel->login($data['userid'],$data['password'],
                                                         $data['congregation']);
                if ($loggedInUser) {
                   //create session
                   $this->createUserSession($loggedInUser,$data['congregation']);
                }
                else{
                    $data['password_err'] = 'Password Incorrect';
                    $this->view('users/index',$data);
                }
                
            }
            else{
                $this->view('users/index',$data);
            }
        }
        else{
            $data = [
                'congregations' => $congregations,
                'userid' => '',
                'password' => '',
                'congregation' => '',
                'userid_err' => '',
                'password_err' => ''
            ];
            $this->view('users/index',$data);
        }
    }
    public function createUserSession($user,$cong)
    {
        $_SESSION['userId'] = $user->ID;
        $_SESSION['userName'] = $user->UserName;
        $_SESSION['userType'] = $user->UsertypeId;
        // $_SESSION['isParish'] = $user->IsParish;
        if($user->UsertypeId == 1 || $user->UsertypeId == 6){
            $_SESSION['congId'] = $cong;
            $_SESSION['isParish'] = $this->userModel->checkIsParish($cong);
        }else{
            $_SESSION['congId'] = $user->CongregationId;
            $_SESSION['isParish'] = $user->IsParish;
        }
        
        $_SESSION['congName'] = $user->CongregationName;
        $_SESSION['one']= 1;
        $_SESSION['zero'] = 0;
        redirect('mains');
    }
    public function logout()
    {
        unset($_SESSION['userId']);
        unset($_SESSION['userName']);
        unset($_SESSION['userType']);
        unset($_SESSION['isParish']);
        unset($_SESSION['congId']);
        unset($_SESSION['congName']);
        unset($_SESSION['one']);
        unset($_SESSION['zero']);
        session_destroy();
        redirect('users');
    }
    public function isLoggedIn()
    {
        if (isset($_SESSION['userId'])) {
            return true;
        }else{
            return false;
        }
    }
    public function all()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2) {
            redirect('');
        }
        else{
            $users = $this->userModel->loadUsers();
            $data = [
                'users' => $users
            ];
            $this->view('users/all',$data);
        }    
    }
    public function change_password()
    {
        $data =[
            'old' => '',
            'new' => '',
            'confirm' => '',
            'old_err' => '',
            'new_err' => '',
            'confirm_err' => ''
        ];
        $this->view('users/change-password',$data);
    }
    public function password()
    {
       if ($_SERVER['REQUEST_METHOD'] == 'POST') {
           $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           $data = [
                'old' => trim($_POST['old']),
                'new' => trim($_POST['new']),
                'confirm' => trim($_POST['confirm']),
                'old_err' => '',
                'new_err' => '',
                'confirm_err' => '',
           ];
           //validate
           if (empty($data['old'])) {
               $data['old_err'] = 'Enter Old Password';
           }
           else{
               if ($this->userModel->passwordMatch($data['old'])) {
                   # code...
               }
               else{
                    $data['old_err'] = 'Old Password Incorrect';
               }
           }
           if (empty($data['new'])) {
                $data['new_err'] = 'Enter New Password';
           }
           if (empty($data['confirm'])) {
                $data['confirm_err'] = 'Confirm Password';
           }
           if ($data['new'] != $data['confirm']) {
                $data['confirm_err'] = 'Passwords Don\'t Match';
           }
           if (empty($data['old_err']) && empty($data['new_err']) && empty($data['confirm_err'])) {
               if ($this->userModel->password($data)) {
                   redirect('mains');
               }
               else{
                   die('Something Went Wrong');
               }
           }
           else{
                $this->view('users/change-password',$data); 
           }
       }
       else{
            $data =[
                'old' => '',
                'new' => '',
                'confirm' => '',
                'old_err' => '',
                'new_err' => '',
                'confirm_err' => ''
            ];
            $this->view('users/change-password',$data);
       }
    }
    public function activitylog()
    {
        $users = $this->userModel->getUsers();
        $data =[
            'users' => $users
        ];
        $this->view('users/activitylog',$data);
    }
    public function activityresult()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'user' => trim($_POST['user']),
                'start' => $_POST['start'],
                'end' => $_POST['end']
            ];
            echo $this->userModel->activityresult($data);
        }
    }
    public function forgotpassword()
    {
        $data = [
            'phone' => '',
            'phone_err' => ''
        ];
        $this->view('users/forgotpassword',$data);
    }
    public function resendpassword()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'phone' => trim($_POST['phone']),
                'phone_err' => '',
                'password' => ''
            ];
            //validate
            if (empty($data['phone'])) {
                $data['phone_err'] = 'Enter Mobile No';
            }
            else{
                if (!$this->userModel->checkUserByPhone($data['phone'])) {
                    $data['phone_err'] = 'This Phone No Is Not Registered';
                }
            }
            if (empty($data['phone_err'])) {
                $random = substr(md5(mt_rand()),0,7);
                $hashed =  password_hash($random,PASSWORD_DEFAULT);
                $data['password'] = $hashed;
                if ($this->userModel->resendCredentials($data)) {
                    $message = 'Password Reset Successful! Your New Password Is '.$random .'click on the provided link to log in. ' . URLROOT;
                    $countryPrexix ='+254';
                    $sb = substr($data['phone'],1);
                    $full = $countryPrexix . $sb;
                    sendLink($full,$message);
                    flash('user_msg','Password Reset Successful! Login In');
                    redirect('');
                }
            }else {
                $this->view('users/forgotpassword',$data); 
            }
        }else{
            $data = [
                'phone' => '',
                'phone_err' => ''
            ];
            $this->view('users/forgotpassword',$data);
        }
    }
    public function edit($id)
    {
        $districts = $this->userModel->getDistricts();
        $user = $this->userModel->getUser($id);
        $data = [
            'districts' => $districts,
            'user' => $user,
            'id' => '',
            'username' => '',
            'usertype' => '',
            'active' => '',
            'contact' => '',
            'district' => '',
            'username_err' =>'',
            'usertype_err' =>'',
            'district_err' =>'',
            'contact_err' =>'',
        ];
        $this->view('users/edit',$data);
    }
    public function update(){
        //CHECK METHOD
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            //validate errors
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            
            $districts = $this->userModel->getDistricts();
            $data = [
                'user => '
,               'id' => trim($_POST['id']),
                'districts' => $districts,
                'username' => trim(strtolower($_POST['username'])),
                'usertype' => $_POST['usertype'],
                'active' => $_POST['active'],
                'contact' => $_POST['contact'],
                'district' => !empty($_POST['district']) ? $_POST['district'] : NULL,
                'userid_err' =>'',
                'username_err' =>'',
                'usertype_err' =>'',
                'contact_err' =>'',
            ];
            $user = $this->userModel->getUser(encryptId($data['id']));
            $data['user'] = $user;
            if (empty($data['username'])) {
                $data['username_err'] = 'Enter User Name';
            }
            if (empty($data['contact'])) {
                $data['contact_err'] = 'Enter Contact';
            }
            if (empty($data['userid_err']) && empty($data['username_err']) && empty($data['contact_err'])) {
                //update
                if ($this->userModel->update($data)) {
                   redirect('users/all');
                }
                else{
                    die('Something Went Wrong');
                }
            }
            else{
                $this->view('users/edit',$data);
            }
        }    
        else{
            $districts = $this->userModel->getDistricts();
            $data = [
                'districts' => $districts,
                'userid' => '',
                'username' => '',
                'usertype' => '',
                'password' => '',
                'active' => '',
                'contact' => '',
                'districtid' => '',
                'userid_err' =>'',
                'username_err' =>'',
                'usertype_err' =>'',
                'district_err' =>'',
                'pwd_err' =>'',
                'contact_err' =>'',
            ];
            $this->view('users/edit',$data);
        }
    }
    public function reset()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'phone' => trim($_POST['contact']),
                'id' => trim($_POST['id']),
                'password' => ''
            ];
            //validate
            if (!empty($data['id']) && !empty($data['phone'])) {
                $random = substr(md5(mt_rand()),0,7);
                $hashed =  password_hash($random,PASSWORD_DEFAULT);
                $data['password'] = $hashed;
                if ($this->userModel->resetCredentials($data)) {
                    $message = 'Password Reset Successful! Your New Password Is '.$random .'click on the provided link to log in. ' . URLROOT;
                    $countryPrexix ='+254';
                    $sb = substr($data['phone'],1);
                    $full = $countryPrexix . $sb;
                    sendLink($full,$message);
                    redirect('users/all');
                }
            }else {
               redirect('users/all');
            }
        }
    }
    public function rights()
    {
        $users = $this->userModel->GetNonAdmins();
        $forms = $this->userModel->GetForms();
        $data = [
            'users' => $users,
            'user_err' => '',
            'forms' => $forms
        ];
        $this->view('users/rights',$data);
    }
    public function getusersrights()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $users = $this->userModel->GetNonAdmins();
            $data = [
                'users' => $users,
                'user' => trim($_POST['user']),
                'user_err' => '',
            ];
            //validate
            if ($data['user'] == 0) {
                $data['user_err'] = 'Select User';
            }
            if (empty($data['user_err'])) {
                redirect('users/getrights/'.encryptId($data['user']));
            }else {
                $this->view('users/rights',$data);
            }
        }else {
            redirect('users');
        }
    }
    public function assignrights()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = json_decode(file_get_contents('php://input'));
            $data = [
                'user' => isset($fields->user) && !empty($fields->user) ? (int)$fields->user : null,
                'rights' => is_countable($fields->tableData) ? $fields->tableData : null,
            ];

            //validate
            if(is_null($data['user']) || is_null($data['rights'])){
                http_response_code(400);
                echo json_encode(['message' => 'Fill all required details']);
                exit;
            }

            //unable to save
            if(!$this->userModel->rights($data)){
                http_response_code(500);
                echo json_encode(['message' => 'Unable to save! Retry or contact admin']);
                exit;
            }

            http_response_code(200);
            echo json_encode(['message' => 'Saved Successfully','success' => true]);
            exit;


        }else {
            redirect('users');
        }
    }

    public function loadrights()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $id = htmlentities(trim($_GET['userid']));
            
            echo json_encode($this->userModel->GetRights($id));
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function getrights($id)
    {
        $user = $this->userModel->getUser($id);
        $forms = $this->userModel->GetRights($id);
        $data = [
            'user' => $user,
            'forms' => $forms
        ];
        // print_r($data);
        $this->view('users/userrights',$data);
    }
    public function clonerights()
    {
        $users = $this->userModel->GetNonAdmins();
        $data = [
            'users' => $users,
            'user1' => '',
            'user2' => '',
            'user1_err' => '',
            'user2_err' => '',
        ];
        $this->view('users/clonerights',$data);
    }
    public function clonemenu()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $users = $this->userModel->GetNonAdmins();
            $data = [
                'users' => $users,
                'user1' => trim($_POST['user1']),
                'user2' => trim($_POST['user2']),
                'user1_err' => '',
                'user2_err' => '',
            ];
            //validate
            if ($data['user1'] == 0) {
                $data['user1_err'] = 'Select User 1';
            }
            if ($data['user2'] == 0) {
                $data['user2_err'] = 'Select User 2';
            }
            if ($data['user1'] != 0) {
                if (!$this->userModel->CheckRightsAssigned($data['user1'])) {
                    $data['user1_err'] = 'No rights assigned to this user';
                }
            }
            if ($data['user1'] !=0 && $data['user2'] != 0) {
                if ($data['user1'] == $data['user2']) {
                    $data['user1_err'] = 'Users cannot be same';
                    $data['user2_err'] = 'Users cannot be same';
                }
            }
            if (empty($data['user1_err']) && empty($data['user2_err'])) {
                if ($this->userModel->clonerights($data)) {
                    flash('clone_msg','User Rights Cloned Successfully!');
                    redirect('users/clonerights');
                }
            }else {
                $this->view('users/clonerights',$data);
            }
        }else {
            redirect('users');
        }
    }
    public function deniedaccess()
    {
        $data = [];
        $this->view('users/deniedaccess',$data);
    }
}