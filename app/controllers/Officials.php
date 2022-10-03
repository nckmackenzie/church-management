<?php
class Officials extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else{
            $this->officialModel = $this->model('Official');
        }
    }
    public function index()
    {
        $form = 'Group Officials';
        if ($_SESSION['userType'] > 2 && !$this->officialModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $officials = $this->officialModel->getOfficials();
        $data = [
            'officials' => $officials
        ];
        $this->view('officials/index',$data);
    }
    public function add()
    {
        $form = 'Group Officials';
        if ($_SESSION['userType'] > 2 && !$this->officialModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $members = $this->officialModel->getMembers();
        $groups = $this->officialModel->getGroups();
        $years = $this->officialModel->getYears();
        $data = [
            'members' => $members,
            'groups' => $groups,
            'years' => $years,
            'year' => '',
            'group' => '',
            'chairman' => '',
            'vchairman' => '',
            'treasurer' => '',
            'vtreasurer' => '',
            'secretary' => '',
            'vsecretary' => '',
            'patron' => '',
            'groupname' => '',
            'yearname' => '',
            'err' => ''
        ];
        $this->view('officials/add',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
           $members = $this->officialModel->getMembers();
           $groups = $this->officialModel->getGroups();
           $years = $this->officialModel->getYears();
           $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           $data = [
               'members' => $members,
               'groups' => $groups,
               'years' => $years,
               'year' => trim($_POST['year']),
               'group' => trim($_POST['group']),
               'chairman' => trim($_POST['chairman']),
               'vchairman' => trim($_POST['vchairman']),
               'treasurer' => trim($_POST['treasurer']),
               'vtreasurer' => trim($_POST['vtreasurer']),
               'secretary' => trim($_POST['secretary']),
               'vsecretary' => trim($_POST['vsecretary']),
               'patron' => trim($_POST['patron']),
               'groupname' => trim(strtolower($_POST['groupname'])),
               'yearname' => trim(strtolower($_POST['yearname'])),
               'err' => ''
           ];
           //validate
           if ($this->officialModel->checkExists($data)) {
               if ($this->officialModel->create($data)) {
                   flash('official_msg','Group Officials Saved Successfully!');
                   redirect('officials');
               }
               else{
                    flash('official_msg','Something Went Wrong!','alert custom-danger');
                    redirect('officials');
               }
           }
           else{
               $data['err'] = 'Officials For Selected Group For Selected Year Already Entered';
               $this->view('officials/add',$data);
           }
        }
    }
    public function edit($id)
    {
        $form = 'Group Officials';
        if ($_SESSION['userType'] > 2 && !$this->officialModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $members = $this->officialModel->getMembers();
        $groups = $this->officialModel->getGroups();
        $years = $this->officialModel->getYears();
        $officials = $this->officialModel->getGroupOfficials($id);
        $data = [
            'members' => $members,
            'groups' => $groups,
            'years' => $years,
            'officials' => $officials,
            'err' => ''
        ];
        if ($data['officials']->congregationId != $_SESSION['congId']) {
            redirect('officials');
        }
        else{
            $this->view('officials/edit',$data);
        }
        
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $members = $this->officialModel->getMembers();
            $groups = $this->officialModel->getGroups();
            $years = $this->officialModel->getYears();
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'members' => $members,
                'groups' => $groups,
                'years' => $years,
                'id' => $_POST['id'],
                'chairman' => trim($_POST['chairman']),
                'vchairman' => trim($_POST['vchairman']),
                'treasurer' => trim($_POST['treasurer']),
                'vtreasurer' => trim($_POST['vtreasurer']),
                'secretary' => trim($_POST['secretary']),
                'vsecretary' => trim($_POST['vsecretary']),
                'patron' => trim($_POST['patron']),
                'groupname' => trim(strtolower($_POST['groupname'])),
                'yearname' => trim(strtolower($_POST['yearname'])),
                'err' => ''
            ];
           
            if ($this->officialModel->update($data)) {
                flash('official_msg','Group Officials Updated Successfully!');
                redirect('officials');
            }
            else{
                    flash('official_msg','Something Went Wrong!','alert custom-danger');
                    redirect('officials');
            }
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => $_POST['id'],
                'groupname' => trim(strtolower($_POST['groupname'])),
                'yearname' => trim(strtolower($_POST['yearname'])),
            ];
           
            if ($this->officialModel->delete($data)) {
                flash('official_msg','Group Officials Deleted Successfully!');
                redirect('officials');
            }
            else{
                    flash('official_msg','Something Went Wrong!','alert custom-danger');
                    redirect('officials');
            }
        }
    }
}