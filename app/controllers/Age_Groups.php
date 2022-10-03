<?php 
class Age_Groups extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else{
            $this->agegroupModel = $this->model('Age_Group');
        }
    }
    public function index()
    {
        $form = 'Age Groups';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->agegroupModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $groups = $this->agegroupModel->index();
        $data = ['groups' => $groups];
        $this->view('age_groups/index',$data);
    }
    public function add()
    {
        $form = 'Age Groups';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->agegroupModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $data = [
            'name' => '',
            'from' => '',
            'to' => '',
            'name_err' => '',
            'from_err' => '',
            'to_err' => ''
        ];
        $this->view('age_groups/add',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'name' => trim($_POST['name']),
                'from' => trim($_POST['from']),
                'to' => trim($_POST['to']),
                'name_err' => '',
                'from_err' => '',
                'to_err' => ''
            ];
            if (empty($data['name'])) {
                $data['name_err'] = 'Enter Age Group Name';
            }
            else{
                if (!$this->agegroupModel->checkExists(strtolower($data['name']))) {
                    $data['name_err'] = 'Age Group Name Exists';
                }
            }
            if (empty($data['from'])) {
                $data['from_err'] = 'Enter Starting Age';
            }
            if (empty($data['to'])) {
                $data['to_err'] = 'Enter Ending Age';
            }
            if (empty($data['name_err']) && empty($data['from_err']) && empty($data['to_err'])) {
                if ($this->agegroupModel->create($data)) {
                    flash('agegroup_msg','Age Group Created Successfully!');
                    redirect('age_groups');
                }else {
                    flash('agegroup_msg','Something Went Wrong!','alert custom-danger');
                    redirect('age_groups');
                }
            }
            else{
                $this->view('age_groups/add',$data);
            }

        }
        else {
            $data = [
                'name' => '',
                'from' => '',
                'to' => '',
                'name_err' => '',
                'from_err' => '',
                'to_err' => ''
            ];
            $this->view('age_groups/add',$data);
        }
    }
    public function edit($id)
    {
        $form = 'Age Groups';
        if ($_SESSION['userType'] > 2 && $_SESSION['userType'] != 6  && !$this->agegroupModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $ageGroup = $this->agegroupModel->getAgeGroup($id);
        $data = [
            'agegroup' => $ageGroup,
            'name' => '',
            'from' => '',
            'to' => '',
            'name_err' => '',
            'from_err' => '',
            'to_err' => ''
        ];
        if ($_SESSION['isParish'] !=1 || $_SESSION['userType'] == 5) {
            redirect('mains');
        }
        else{
            $this->view('age_groups/edit',$data);
        }
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'name' => trim($_POST['name']),
                'from' => trim($_POST['from']),
                'to' => trim($_POST['to']),
                'name_err' => '',
                'from_err' => '',
                'to_err' => ''
            ];
            if (empty($data['name'])) {
                $data['name_err'] = 'Enter Age Group Name';
            }

            if (empty($data['from'])) {
                $data['from_err'] = 'Enter Starting Age';
            }
            if (empty($data['to'])) {
                $data['to_err'] = 'Enter Ending Age';
            }
            if (empty($data['name_err']) && empty($data['from_err']) && empty($data['to_err'])) {
                if ($this->agegroupModel->update($data)) {
                    flash('agegroup_msg','Age Group Updated Successfully!');
                    redirect('age_groups');
                }else {
                    flash('agegroup_msg','Something Went Wrong!','alert custom-danger');
                    redirect('age_groups');
                }
            }
            else{
                $this->view('age_groups/edit',$data);
            }

        }
        else {
            $data = [
                'name' => '',
                'from' => '',
                'to' => '',
                'name_err' => '',
                'from_err' => '',
                'to_err' => ''
            ];
            $this->view('age_groups/edit',$data);
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'name' => trim($_POST['groupname'])
            ];
            
            if (!empty($data['id'])) {
                if ($this->agegroupModel->delete($data)) {
                    flash('agegroup_msg','Age Group Deleted Successfully!');
                    redirect('age_groups');
                }else {
                    flash('agegroup_msg','Something Went Wrong!','alert custom-danger');
                    redirect('age_groups');
                }
            }
            else{
                redirect('age_groups');
            }
        }
    }
}