<?php
class Vats extends Controller {
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        checkrights($this->authmodel,'vat');
        $this->vatModel = $this->model('Vat');
    }
    public function index()
    {
        $vats = $this->vatModel->index();
        $data = ['vats' => $vats];
        $this->view('vats/index',$data);
    }
    public function add()
    {
        $data = [
            'id' => '',
            'vatname' => '',
            'rate' => '',
            'active' => 1,
            'name_err' => '',
            'rate_err' => ''
        ];
        $this->view('vats/add',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => '',
                'vatname' => trim($_POST['vatname']),
                'rate' => trim($_POST['rate']),
                'active' => isset($_POST['active']) ? 1 : 0,
                'name_err' => '',
                'rate_err' => ''
            ];
            //validate
            if (empty($data['vatname'])) {
                $data['name_err'] = 'Enter V.A.T Name';
            }
            else{
                if (!$this->vatModel->checkExists($data)) {
                    $data['name_err'] = 'V.A.T Name Already Exists';
                }
            }
            if (empty($data['rate'])) {
                $data['rate_err'] = 'Enter V.A.T Rate';
            }
            if (empty($data['name_err']) && empty($data['rate_err'])) {
                if ($this->vatModel->create($data)) {
                    flash('vat_msg','V.A.T Created Successfully!');
                    redirect('vats');
                }
            }
            else{
                $this->view('vats/add',$data);
            }
        }else{
            $data = [
                'id' => '',
                'vatname' => '',
                'rate' => '',
                'active' => 1,
                'name_err' => '',
                'rate_err' => ''
            ];
            $this->view('vats/add',$data);
        }
    }
    public function edit($id)
    {
        $vat = $this->vatModel->getVat($id);
        $data = [
            'vat' => $vat,
            'id' => '',
            'vatname' => '',
            'rate' => '',
            'active' => 1,
            'name_err' => '',
            'rate_err' => ''
        ];
        $this->view('vats/edit',$data);
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'vatname' => trim($_POST['vatname']),
                'rate' => trim($_POST['rate']),
                'active' => isset($_POST['active']) ? 1 : 0,
                'name_err' => '',
                'rate_err' => ''
            ];
            //validate
            if (empty($data['vatname'])) {
                $data['name_err'] = 'Enter V.A.T Name';
            }
            else{
                if (!$this->vatModel->checkExists($data)) {
                    $data['name_err'] = 'V.A.T Name Already Exists';
                }
            }
            if (empty($data['rate'])) {
                $data['rate_err'] = 'Enter V.A.T Rate';
            }
            if (empty($data['name_err']) && empty($data['rate_err'])) {
                if ($this->vatModel->update($data)) {
                    flash('vat_msg','V.A.T Updated Successfully!');
                    redirect('vats');
                }
            }
            else{
                $this->view('vats/edit',$data);
            }
        }else{
            $data = [
                'id' => '',
                'vatname' => '',
                'rate' => '',
                'active' => 1,
                'name_err' => '',
                'rate_err' => ''
            ];
            $this->view('vats/edit',$data);
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => trim($_POST['id']),
                'vatname' => trim($_POST['vatname']),
            ];
            //validate
            if (!empty($data['id'])) {
                if ($this->vatModel->delete($data)) {
                    flash('vat_msg','V.A.T Deleted Successfully!');
                    redirect('vats');
                }
            }
        }
    }
}