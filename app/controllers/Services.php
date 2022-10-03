<?php 
class Services extends Controller{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2) {
            redirect('');
        }
        else{
            $this->serviceModel = $this->model('Service');
        }
        
    }
    public function index()
    {
        $services = $this->serviceModel->getServices();
        $data = [
            'services' => $services
        ];
        $this->view('services/index',$data);
    }
    public function add()
    {
        $data = [
            'servicename' => '',
            'servicetime'=> '',
            'name_err' => '',
            'time_err' => ''
        ];
        $this->view('services/add',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST= filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data= [
                'servicename' => trim(strtolower($_POST['servicename'])),
                'servicetime'=> trim(strtolower($_POST['servicetime'])),
                'name_err' => '',
                'time_err' => ''
            ];
            //validate
            if (empty($data['servicename'])) {
               $data['name_err'] = 'Enter Service Name';
            }
            else{
                if (!$this->serviceModel->checkExists($data['name'])) {
                    $data['name_err'] ='Service Exists';
                }
            }
            if (empty($data['servicetime'])) {
                $data['time_err'] ='Service Time Required';
            }
            //if no errors
            if (empty($data['name_err']) && empty($data['time_err'])) {
                if ($this->serviceModel->create($data)) {
                    flash('service_added','Service Added Successfully!');
                    redirect('services');
                }
            }
            else{
                $this->view('services/add',$data);
            }

        }
        else{
            $data = [
                'servicename' => '',
                'servicetime'=> '',
                'name_err' => '',
                'time_err' => ''
            ];
            $this->view('services/add',$data);
        }
    }
    public function edit($id)
    {
        $service = $this->serviceModel->getService($id);
        $data = [
            'service' =>  $service,
        ];
        if ($data['service']->congregationId != $_SESSION['congId']) {
            redirect('services');
        }
        elseif ($data['service']->congregationId == $_SESSION['congId']) {
            $this->view('services/edit',$data);
        }
        
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST= filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data= [
                'servicename' => trim(strtolower($_POST['servicename'])),
                'servicetime'=> trim(strtolower($_POST['servicetime'])),
                'id' => $_POST['id'],
                'name_err' => '',
                'time_err' => ''
            ];
            //validate
            if (empty($data['servicename'])) {
               $data['name_err'] = 'Enter Service Name';
            }
            
            if (empty($data['servicetime'])) {
                $data['time_err'] ='Service Time Required';
            }
            //if no errors
            if (empty($data['name_err']) && empty($data['time_err'])) {
                if ($this->serviceModel->update($data)) {
                    flash('service_added','Service Updated Successfully!');
                    redirect('services');
                }
            }
            else{
                $this->view('services/edit',$data);
            }

        }
        else{
            $data = [
                'servicename' => '',
                'servicetime'=> '',
                'id' => '',
                'name_err' => '',
                'time_err' => ''
            ];
            $this->view('services/edit',$data);
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST= filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data= [
                'servicename' => trim(strtolower($_POST['servicename'])),
                'id' => $_POST['id']
            ];
            //validate
            
            if (isset($data['id'])) {
                if ($this->serviceModel->delete($data)) {
                    flash('service_added','Service Deleted Successfully!');
                    redirect('services');
                }
                else{
                    flash('service_added','Service Cannot Be Deleted!','alert custom-danger alert-dismissible fade show');
                    redirect('services');
                }
            }
            else{
                $this->view('services/index',$data);
            }

        }
        else{
            $data = [
                'servicename' => '',
                'servicetime'=> '',
                'id' => '',
                'name_err' => '',
                'time_err' => ''
            ];
            $this->view('services/index',$data);
        }
    }
    public function service_info()
    {
        $serviceInfo = $this->serviceModel->getServicesInfo();
        $data =[
            'servicesinfo' => $serviceInfo
        ];
        $this->view('services/services_info_index',$data);
    }
    public function add_service_info()
    {
        $services = $this->serviceModel->getServices();
        $data = [
            'services' => $services,
            'service' => '',
            'date' =>'',
            'headedby' =>'',
            'preacher' =>'',
            'attendance' =>'',
            'envelopepledge' =>'',
            'ordinary' =>'',
            'special' =>'',
        ];
        $this->view('services/addinfo',$data);
    }
    public function createinfo()
    {
       if ($_SERVER['REQUEST_METHOD'] == 'POST') {
           $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
           $services = $this->serviceModel->getServices();
           $data = [
                'services' => $services,
                'servicename' => trim(strtolower($_POST['servicename'])),
                'service' => $_POST['service'],   
                'date' => $_POST['date'],
                'headedby' => trim(strtolower($_POST['headedby'])),
                'preacher' => trim(strtolower($_POST['preacher'])),
                'attendance' => trim(strtolower($_POST['attendance'])),
                'envelopepledge' => $_POST['envelopepledge'],
                'ordinary' => $_POST['ordinary'],
                'special' => $_POST['special'],
                'date_err' => '',
                'service_err' => ''
           ];
           //validate
           if (empty($data['service'] )) {
               $data['service_err'] = 'Select Service';
           }
           if (empty($data['date'] )) {
               $data['date_err'] = 'Select date';
           }
           if (empty($data['date_err']) && empty($data['service_err'])) {
               if ($this->serviceModel->checkInfoExists($data)) {
                  if ($this->serviceModel->createinfo($data)) {
                        flash('serviceinfo_msg','Service Information Added Successfully!');
                        redirect('services/service_info');
                   }
                   else{
                       flash('serviceinfo_msg','Something Went Wrong!');
                       redirect('services/service_info');
                   }
                }
                else{
                    flash('serviceinfo_msg','Another Service Information Exists For This Date!',
                          'alert custom-danger alert-dismissible fade show');
                    redirect('services/service_info');
                }
            }
           else{
               $this->view('services/addinfo',$data);
           }
       }else{
            $data = [
                'service' => '',
                'date' =>'',
                'headedby' =>'',
                'preacher' =>'',
                'attendance' =>'',
                'envelopepledge' =>'',
                'ordinary' =>'',
                'special' =>'',
            ];
            $this->view('services/addinfo',$data); 
       }
    }
    public function updateinfo()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $services = $this->serviceModel->getServices();
            $data = [
                 'services' => $services,
                 'id' => trim($_POST['id']),
                 'servicename' => trim(strtolower($_POST['servicename'])),
                 'service' => $_POST['service'],   
                 'date' => $_POST['date'],
                 'headedby' => trim(strtolower($_POST['headedby'])),
                 'preacher' => trim(strtolower($_POST['preacher'])),
                 'attendance' => trim(strtolower($_POST['attendance'])),
                 'envelopepledge' => $_POST['envelopepledge'],
                 'ordinary' => $_POST['ordinary'],
                 'special' => $_POST['special'],
                 'date_err' => '',
                 'service_err' => ''
            ];
            //validate
            if (empty($data['service'] )) {
                $data['service_err'] = 'Select Service';
            }
            if (empty($data['date'] )) {
                $data['date_err'] = 'Select date';
            }
            if (empty($data['date_err']) && empty($data['service_err'])) {
                if ($this->serviceModel->updateinfo($data)) {
                    flash('serviceinfo_msg','Service Information Updated Successfully!');
                    redirect('services/service_info');
                }
                else{
                    flash('serviceinfo_msg','Something Went Wrong!');
                    redirect('services/service_info');
                }
                 
             }
            else{
                $this->view('services/editinfo',$data);
            }
        }else{
             $data = [
                 'service' => '',
                 'id' => '',
                 'date' =>'',
                 'headedby' =>'',
                 'preacher' =>'',
                 'attendance' =>'',
                 'envelopepledge' =>'',
                 'ordinary' =>'',
                 'special' =>'',
             ];
             $this->view('services/editinfo',$data); 
        }
    }
    public function edit_service_info($id)
    {
        $services = $this->serviceModel->getServices();
        $serviceInfo = $this->serviceModel->getInfo($id);
        $data = [
            'services' => $services,
            'serviceinfo' => $serviceInfo
        ];
        if ($data['serviceinfo']->congregationId != $_SESSION['congId']) {
            redirect('services/service_info');
        }
        elseif ($data['serviceinfo']->congregationId == $_SESSION['congId']) {
            $this->view('services/editinfo',$data);
        }
    }
    public function deleteinfo()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST= filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data= [
                'servicename' => trim(strtolower($_POST['servicename'])),
                'id' => $_POST['id']
            ];
            //validate
            if (isset($data['id'])) {
                if ($this->serviceModel->deleteinfo($data)) {
                    flash('serviceinfo_msg','Service Deleted Successfully!');
                    redirect('services/services_info_index');
                }
            }
            else{
                $this->view('services/services_info_index',$data);
            }

        }
        else{
            $data = [
                'servicename' => '',
                'id' => '',
            ];
            $this->view('services/services_info_index',$data);
        }
    }
}