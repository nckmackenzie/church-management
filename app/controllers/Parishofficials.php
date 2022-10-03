<?php

class Parishofficials extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['isParish'] != 1) {
            redirect('');
        }
        else{
            $this->parishofficialsModel = $this->model('Parishofficial');
        }
    }
    public function index()
    {
        $form = 'Parish Officials';
        if ($_SESSION['userType'] > 2  && !$this->parishofficialsModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $data = [];
        $this->view('parishofficials/index',$data);
    }
    public function add()
    {
        $form = 'Parish Officials';
        if ($_SESSION['userType'] > 2  && !$this->parishofficialsModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $years = $this->parishofficialsModel->getYears();
        $members = $this->parishofficialsModel->getMembers();
        $data = [
            'years' => $years,
            'members' => $members,
            'start' => '',
            'end' => '',
            'pminister' => '',
            'sclerk' => '',
            'fchair' => '',
            'treasurer' => '',
            'pelder' => '',
            'start_err' => '',
            'end_err' => '',
            'pminister_err' => '',
            'sclerk_err' => '',
            'fchair_err' => '',
            'treasurer_err' => '',
            'pelder_err' => '',
        ];
        $this->view('parishofficials/add',$data);
    }
    public function create()
    {
        $years = $this->parishofficialsModel->getYears();
        $members = $this->parishofficialsModel->getMembers();
        $officialsId = $this->parishofficialsModel->generateOfficialsId();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_SANITIZE_STRING);
            $data = [
                'years' => $years,
                'members' => $members,
                'ofid' => $officialsId,
                'start' => trim($_POST['start']),
                'end' => trim($_POST['end']),
                'pminister' => !empty($_POST['pminister']) ? $_POST['pminister'] : '',
                'sclerk' => !empty($_POST['sclerk']) ? trim($_POST['sclerk']) : '',
                'fchair' => !empty($_POST['fchair']) ? trim($_POST['fchair']) : '',
                'treasurer' => !empty($_POST['treasurer']) ? trim($_POST['treasurer']) : '',
                'pelder' => !empty($_POST['pelder']) ? trim($_POST['pelder']) : '',
                'start_err' => '',
                'end_err' => '',
                'pminister_err' => '',
                'sclerk_err' => '',
                'fchair_err' => '',
                'treasurer_err' => '',
                'pelder_err' => '',
            ];

            // print_r($data);

            // validation
            if(empty($data['start'])){
                $data['start_err'] = 'Select start date';
            }
            if(empty($data['end'])){
                $data['end_err'] = 'Select end date';
            }
            if(!empty($data['start']) && !empty($data['end']) && ($data['start'] > $data['end'])){
                $data['start_err'] = 'Start date cannot be greater than end date';
            }
            if(empty($data['pminister'])){
                $data['pminister_err'] = 'Select Parish Minister';
            }
            if(empty($data['sclerk'])){
                $data['sclerk_err'] = 'Select session clerk';
            }
            if(empty($data['fchair'])){
                $data['fchair_err'] = 'Select finance chair';
            }
            if(empty($data['treasurer'])){
                $data['treasurer_err'] = 'Select treasurer';
            }
            if(empty($data['pelder'])){
                $data['pelder_err'] = 'Select Pairing elder';
            }

            if (empty($data['start_err']) && empty($data['end_err']) && empty($data['pminister_err'])
                && empty($data['sclerk_err']) && empty($data['fchair_err']) && empty($data['treasurer_err'])
                && empty($data['pelder_err'])) {
                
                if($this->parishofficialsModel->create($data)){
                    flash('parishofficial_msg','Officials Created Successfully!');
                    redirect('parishofficials');
                }
                    
            }else{
                $this->view('parishofficials/add',$data);
            }

        }else {
            redirect('users/deniedaccess');
        }
    }
}