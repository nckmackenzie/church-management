<?php

class Roles extends Controller
{
    private $rolemodel;
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        if((int)$_SESSION['userType'] > 2 || (int)$_SESSION['isParish'] === 0){
            redirect('users/deniedaccess');
            exit;
        }
        $this->rolemodel = $this->model('Role');
    }

    public function index()
    {
        $data = ['roles' => $this->rolemodel->GetRoles()];
        $this->view('roles/index',$data);
    }

    public function add()
    {
        $data = [
            'roles' => $this->rolemodel->GetRoles(),
            'error' => ''
        ];
        $this->view('roles/add',$data);
    }

    public function addrole()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $data = [
                'error' => '',
                'role' => isset($_POST['name']) && !empty(trim($_POST['name'])) ? trim($_POST['name']) : null
            ];
           
            if(is_null($data['role'])){
                $data['error'] = 'Role name not provided';
                $this->view('roles/add',$data);
                exit;
            }

            if($this->rolemodel->NameExists($data['role'])){
                $data['error'] = 'Role name already exists';
                $this->view('roles/add',$data);
                exit;
            }

            $this->rolemodel->AddRole($data['role']);

            redirect('roles/add');
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function loadrights()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $id = htmlentities(trim($_GET['roleid']));
            
            echo json_encode($this->rolemodel->GetRights($id));
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function assignrights()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = json_decode(file_get_contents('php://input'));
            $data = [
                'role' => isset($fields->role) && !empty($fields->role) ? (int)$fields->role : null,
                'rights' => is_countable($fields->tableData) ? $fields->tableData : null,
            ];

            //validate
            if(is_null($data['role']) || is_null($data['rights'])){
                http_response_code(400);
                echo json_encode(['message' => 'Fill all required details']);
                exit;
            }

            //unable to save
            if(!$this->rolemodel->rights($data)){
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
}