<?php
class Districts extends Controller {
    public function __construct()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2) {
            redirect('');
        }
        else{
            $this->districtModel = $this->model('District');
        }    
    }
    public function index()
    {
        $districts = $this->districtModel->loadDistricts();
        $data= ['districts' => $districts];
        $this->view('districts/index',$data);
    }
    public function add()
    {
        $data = [
            'name' => '',
            'name_err' =>''
        ];
        $this->view('districts/add',$data);
    }
    public function edit($id)
    {
        $district = $this->districtModel->fetchDistrict($id);
        $data = ['district' =>$district];
        $this->view('districts/edit',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            //sanitize string
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'name' => trim(strtolower($_POST['districtname'])),
                'name_err' =>''
            ];
            if (empty($data['name'])) {
                $data['name_err'] ='Enter District Name';
            }
            else{
                if (!$this->districtModel->checkExists($data['name'])) {
                    $data['name_err'] ='District Exists';
                }
            }
            if (empty($data['name_err'])) {
                if ($this->districtModel->create($data)) {
                    flash('district_msg','District Added Successfully!');
                    redirect('districts');
                }
                else{
                    flash('district_msg','Something Went Wrong!','alert alert-danger');
                    redirect('districts');
                }
            }
            else{
                $this->view('districts/add',$data);
            }
        }
        else{
            $data = [
                'name' => '',
                'name_err' =>''
            ];
            $this->view('districts/add',$data);
        }
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            //sanitize string
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'name' => trim(strtolower($_POST['districtname'])),
                'id' => $_POST['id'],
                'name_err' =>''
            ];
            if (empty($data['name'])) {
                $data['name_err'] ='Enter District Name';
            }

            if (empty($data['name_err'])) {
                if ($this->districtModel->update($data)) {
                    flash('district_msg','District Updated Successfully!');
                    redirect('districts');
                }
                else{
                    flash('district_msg','Something Went Wrong!','alert alert-danger');
                    redirect('districts');
                }
            }
            else{
                $this->view('districts/edit',$data);
            }
        }
        else{
            $data = [
                'name' => '',
                'name_err' =>''
            ];
            $this->view('districts/edit',$data);
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            //sanitize string
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => $_POST['id'],
            ];
            
            if (isset($data['id'])) {
                if ($this->districtModel->delete($data['id'])) {
                    flash('district_msg','District Deleted Successfully!');
                    redirect('districts');
                }
                else{
                    flash('district_msg','Something Went Wrong!','alert alert-danger');
                    redirect('districts');
                }
            }
            else{
                $this->view('districts',$data);
            }
        }
        else{
            $data = [
                'name' => '',
                'name_err' =>''
            ];
            $this->view('districts',$data);
        }
    }
}
