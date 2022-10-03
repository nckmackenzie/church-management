<?php
class Transfers extends Controller
{
    public function __construct()
    {
        if(!isset($_SESSION['userId'])){
            redirect('users');
            exit;
        }
        if((int)$_SESSION['isParish'] === 0){
            redirect('users/deniedaccess');
            exit;
        }

        $this->transfermodel = $this->model('Transfer');
    }

    public function getvalues()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $data = [
                'type' => trim($_GET['type']),
                'cong' => isset($_GET['cong']) ? (int)trim($_GET['cong']) : null,
                'district' => isset($_GET['district']) ? (int)trim($_GET['district']) : null,
                'results' => [],
            ];
            $results='';
            if($data['type'] === 'districts'){
                $results = $this->transfermodel->GetDistricts($data['cong']);
            }elseif($data['type'] === 'members'){
                $results = $this->transfermodel->GetMembers($data['district']);
            }
            foreach($results as $district){
                array_push($data['results'],[
                    'id' => $district->ID,
                    'fieldName' => $district->fieldName,
                ]);
            }
            echo json_encode($data['results']);
        }else{
            redirect('users/deniedaccess');
            exit;
        }
    }
}