<?php
class Years extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'financial years');
        $this->yearModel = $this->model('Year');
    }
    public function index()
    {
        $years  = $this->yearModel->index();
        $data = ['years' => $years];
        $this->view('years/index',$data);
    }
    public function add()
    {
        $data = [
            'yearname' => '',
            'startdate' => '',
            'enddate' => '',
            'name_err' => '',
            'start_err' => '',
            'end_err' => ''
        ];
        $this->view('years/add',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => '',
                'yearname' => trim($_POST['yearname']),
                'startdate' => trim($_POST['startdate']),
                'enddate' => trim($_POST['enddate']),
                'name_err' => '',
                'start_err' => '',
                'end_err' => ''
            ];
            //validation
            if (empty($data['yearname'])) {
                $data['name_err'] = 'Enter Year Name';
            }
            else{
                if (!$this->yearModel->checkExists($data)) {
                    $data['name_err'] = 'Year Name Already Exists';
                }
            }
            if (empty($data['startdate'])) {
                $data['start_err'] = 'Select Start Date';
            }
            if (empty($data['enddate'])) {
                $data['end_err'] = 'Select End Date';
            }
            if (!empty($data['startdate']) && !empty($data['enddate'])) {
                if ($data['startdate'] > $data['enddate']) {
                    $data['start_err'] = 'Start Date Cannot Be Greater Than End Date';
                }
                else{
                    if (!$this->yearModel->checkYearConflict($data['startdate'])) {
                        $data['start_err'] = 'Defined Period Overlaps With Another Period';
                    }
                }
            }
            if (empty($data['name_err']) && empty($data['start_err']) && empty($data['end_err'])) {
                if ($this->yearModel->create($data)) {
                    flash('year_msg','Financial Year Created Successfully');
                    redirect('years');
                }
                else{
                    flash('year_msg','Something Went Wrong','alert custom-danger');
                    redirect('years');
                }
            }
            else{
                $this->view('years/add',$data);
            }
        }
        else{
            $data = [
                'yearname' => '',
                'startdate' => '',
                'enddate' => '',
                'name_err' => '',
                'start_err' => '',
                'end_err' => ''
            ];
            $this->view('years/add',$data);
        }
    }
    public function edit($id)
    {
       $year = $this->yearModel->getYear($id);
       $data = [
            'id' => '',
            'year' => $year,
            'yearname' => '',
            'name_err' => '',
       ];
       if ($_SESSION['isParish'] != 1 || $_SESSION['userType'] > 2) {
          redirect('mains');
       }
       else{
          $this->view('years/edit',$data);
       }
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            
            $data = [
                'year' => '',
                'id' => trim($_POST['id']),
                'yearname' => trim($_POST['yearname']),
                'name_err' => '',
            ];
            $year = $this->yearModel->getYear(encryptId($data['id']));
            $data['year'] = $year;
            //validation
            if (empty($data['yearname'])) {
                $data['name_err'] = 'Enter Year Name';
            }
            else{
                if (!$this->yearModel->checkExists($data)) {
                    $data['name_err'] = 'Year Name Already Exists';
                }
            }
            if (empty($data['name_err'])) {
                if ($this->yearModel->update($data)) {
                    flash('year_msg','Financial Year Updated Successfully');
                    redirect('years');
                }
                else{
                    flash('year_msg','Something Went Wrong','alert custom-danger');
                    redirect('years');
                }
            }
            else{
                $this->view('years/edit',$data);
            }
        }
        else{
            $data = [
                'yearname' => '',
                'startdate' => '',
                'enddate' => '',
                'name_err' => '',
                'start_err' => '',
                'end_err' => ''
            ];
            $this->view('years/edit',$data);
        }
    }
    public function close()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            
            $data = [
                'id' => trim($_POST['id']),
                'yearname' => trim($_POST['yearname'])
            ];
            //validation
            if (!empty($data['id'])) {
                if ($this->yearModel->close($data)) {
                    flash('year_msg','Financial Year Closed Successfully');
                    redirect('years');
                }
                else{
                    flash('year_msg','Something Went Wrong','alert custom-danger');
                    redirect('years');
                }
            }
            else{
                redirect('mains');
            }
        }
        else{
            redirect('mains');
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            
            $data = [
                'id' => trim($_POST['id']),
                'yearname' => trim($_POST['yearname'])
            ];
            //validation
            if (!empty($data['id'])) {
                if ($this->yearModel->delete($data)) {
                    flash('year_msg','Financial Year Deleted Successfully');
                    redirect('years');
                }
                else{
                    flash('year_msg','Something Went Wrong','alert custom-danger');
                    redirect('years');
                }
            }
            else{
                redirect('mains');
            }
        }
        else{
            redirect('mains');
        }
    }
}