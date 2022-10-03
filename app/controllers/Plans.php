<?php
class Plans extends Controller
{
    public function __construct()
    {
        if (!isset($_SESSION['userId']) || $_SESSION['userType'] > 2 ) {
            redirect('users');
        }else{
            $this->planModel = $this->model('Plan');
        }
    }
    public function index()
    {
        $plans = $this->planModel->index();
        $data = [
            'plans' => $plans
        ];
        $this->view('plans/index',$data);
    }
    public function add()
    {
        $years = $this->planModel->GetFiscalYears();
        $currentYear = $this->planModel->GetCurrentYear();
        $activities = $this->planModel->GetActivities();
        $accounts = $this->planModel->GetAccounts();
        $data = [
            'years' => $years,
            'current' => $currentYear,
            'activities' => $activities,
            'accounts' => $accounts,
            'level' => '',
            'year' => '',
            'planname' => '',
            'theme' => '',
            'meetingdate' => '',
            'activity' => '',
            'reason' => '',
            'costestimate' => '',
            'fromdate' => '',
            'todate' => '',
            'actualdate' => '',
            'account' => '',
            'office' => '',
            'officeother' => '',
            'collaborator' => '',
            'collaboratorName' => '',
            'results' => '',
            'actualcost' => '',
            'evidence' => '',
            'remarks' => '',
            'filename' => '',

            
            'level_err' => '',
            'theme_err' => '',
            'mdate_err' => '',
            'activity_err' => '',
            'reason_err' => '',
            'estimate_err' => '',
            'fdate_err' => '',
            'tdate_err' => '',
            'adate_err' => '',
            'account_err' => '',
            'office_err' => '',
            'other_err' => '',
            'attach_err' => ''
        ];
        $this->view('plans/add',$data);
    }
    public function checkname()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
           $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
           $activity = trim($_GET['activity']);
           return $this->planModel->CheckActivityName($activity);
        }else{
            redirect('users');
        }
    }
    public function createactivity()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $activity = trim($_POST['activity']);
            $this->planModel->CreateActivity($activity);
        }else{
            redirect('users');
        }
    }
    public function reloadactivities()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            $activities = $this->planModel->GetActivities();
            // print_r($products);
            $output = '';
            $output .='<option value="0"><strong>Add NEW</strong></option>';
            foreach ($activities as $activity ) {
                $output .= '<option value="'.$activity->ID.'" selected>'.$activity->ActivityName.'</option>';
            }
            echo $output;
        }
    }
    public function getcollaborator()
    {
        if($_SERVER['REQUEST_METHOD'] == 'GET'){
            $_GET = filter_input_array(INPUT_GET,FILTER_UNSAFE_RAW);
            $level = trim($_GET['level']);
            $output = '';
            if ($level == 1) {
                
                $congregationgroups = $this->planModel->GetCongregationAndGroups();
                foreach ($congregationgroups as $category ) {
                    $output .= '<option value="'.$category->ID.'" selected>'.$category->categoryName.'</option>';
                }
            }elseif ($level == 2){
                
                $groups = $this->planModel->GetGroups();
                foreach ($groups as $category ) {
                    $output .= '<option value="'.$category->ID.'" selected>'.$category->categoryName.'</option>';
                }
            }
            echo $output;
        }
    }
    //save/submit
    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $years = $this->planModel->GetFiscalYears();
            $currentYear = $this->planModel->GetCurrentYear();
            $activities = $this->planModel->GetActivities();
            $accounts = $this->planModel->GetAccounts();
            $data = [
                'years' => $years,
                'current' => $currentYear,
                'activities' => $activities,
                'accounts' => $accounts,
                'id' => '',
                'level' => !empty($_POST['level']) ? trim($_POST['level']) : '',
                'year' => trim($_POST['year']),
                'planname' => trim($_POST['planname']),
                'theme' => !empty($_POST['theme']) ? trim($_POST['theme']) : '',
                'meetingdate' => !empty($_POST['meetingdate']) ? trim($_POST['meetingdate']) : '',
                'activity' => !empty($_POST['activity']) ? trim($_POST['activity']) : '',
                'reason' => trim($_POST['reason']),
                'costestimate' => trim($_POST['costestimate']),
                'fromdate' => trim($_POST['fromdate']),
                'todate' => trim($_POST['todate']),
                'actualdate' => trim($_POST['actualdate']),
                'account' => !empty($_POST['account']) ? trim($_POST['account']) : '',
                'office' => !empty($_POST['office']) ? trim($_POST['office']) : '',
                'officeother' => !empty($_POST['officeother']) ? trim($_POST['officeother']) : '',
                'collaborator' => !empty($_POST['collaborator']) ? trim($_POST['collaborator']) : '',
                'collaboratorName' => !empty($_POST['collobatorName']) ? trim($_POST['collobatorName']) : '',
                'results' => !empty($_POST['results']) ? trim($_POST['results']) : '',
                'actualcost' => !empty($_POST['actualcost']) ? trim($_POST['actualcost']) : '',
                'evidence' => !empty($_POST['evidence']) ? trim($_POST['evidence']) : '',
                'remarks' => !empty($_POST['remarks']) ? trim($_POST['remarks']) : '',
                'file' => $_FILES['file'],
                'filename' => '',
                'status' => 0,


                'level_err' => '',
                'year_err' => '',
                'theme_err' => '',
                'mdate_err' => '',
                'activity_err' => '',
                'reason_err' => '',
                'estimate_err' => '',
                'fdate_err' => '',
                'tdate_err' => '',
                'adate_err' => '',
                'account_err' => '',
                'office_err' => '',
                'other_err' => '',
                'acost_err' => '',
                'evidence_err' => '',
                'attach_err' => ''
            ];
            //file
            $fileTmpName = '';
            $fileDesination = '';
            // print_r($data['file']);

            if ($data['file']['size'] > 0) {
                $fileName = $data['file']['name'];
                $fileTmpName = $data['file']['tmp_name'];
                $fileSize = $data['file']['size'];
                $fileError = $data['file']['error'];

                $fileExt = explode('.', $fileName);
                $fileActualExt = strtolower(end($fileExt));
                $allowed = array('jpg','jpeg','png','pdf');

                if (in_array($fileActualExt,$allowed)) {
                    if ($fileError === 0) {
                      if ($fileSize < 1000000) {
                        $fileNameNew = uniqid('',true).'.'.$fileActualExt;
                        $data['filename'] = $fileNameNew;
                        $des = getcwd();
                        $fileDesination = $des.'/img/'.$fileNameNew;
                      }else {
                          $data['attach_err'] = "File size is too big";
                      }
                    }else {
                        $data['attach_err']= 'An error occurred during file upload';
                    }
                  }else{
                    $data['attach_err'] = 'Invalid File Type';
                  }
            }

            //validation
            if (empty($data['level'])) {
                $data['level_err'] = 'Select Level';
            }
            if (empty($data['year'])) {
                $data['year_err'] = 'Select Year';
            }
            if (!empty($data['level']) && !empty($data['year'])) {
                if (!$this->planModel->CheckPlanExists($data)) {
                    $data['level_err'] = 'Level already created for the selected year';
                }
            }
            if (empty($data['theme'])) {
                $data['theme_err'] = 'Select Theme';
            }
            if (!empty($data['theme']) && $data['theme'] == 6 && empty($data['meetingdate'])) {
                $data['mdate_err'] = 'Select Meeting Date';
            }
            if (empty($data['activity'])) {
                $data['activity_err'] = 'Select Activity';
            }
            if (empty($data['reason'])) {
                $data['reason_err'] = 'Enter Reason of activity';
            }
            if (empty($data['costestimate'])) {
                $data['estimate_err'] = 'Enter Cost Estimate';
            }
            if (empty($data['fromdate'])) {
                $data['fdate_err'] = 'Select From Date';
            }
            if (empty($data['todate'])) {
                $data['tdate_err'] = 'Select To Date';
            }
            if (!empty($data['fromdate']) && !empty($data['todate']) && ($data['todate'] < $data['fromdate'])) {
                $data['tdate_err'] = 'To Date Cannot Be Lesser Than From Date';
            }
            if (!empty($data['fromdate']) && !empty($data['actualdate']) && ($data['actualdate'] < $data['fromdate'])) {
                $data['tdate_err'] = 'Actual Date Cannot Be Lesser Than From Date';
            }
            if (empty($data['account'])) {
                $data['account_err'] = 'Select Budget Account';
            }
            if (empty($data['office'])) {
                $data['office_err'] = 'Select Office Responsible';
            }
            if (!empty($data['office']) && $data['office'] == 5 && empty($data['officeother'])) {
                $data['other_err'] = 'Enter Other Office Responsible';
            }

            //save
            if (isset($_POST['save'])) {
             
                if (empty($data['level_err']) && empty($data['year_err']) && empty($data['theme_err']) 
                && empty($data['mdate_err']) && empty($data['activity_err']) && empty($data['reason_err'])
                && empty($data['estimate_err']) && empty($data['fdate_err']) && empty($data['tdate_err'])
                && empty($data['adate_err']) && empty($data['account_err']) && empty($data['office_err'])
                && empty($data['other_err']) && empty($data['attach_err']) && empty($data['acost_err']) 
                && empty($data['evidence_err'])) {
                    
                    if ($this->planModel->create($data)) {
                        if ($data['file']['size'] > 0) {
                            if (move_uploaded_file($fileTmpName,$fileDesination)) {
                                flash('plan_msg','Plan Saved Successfully');
                                redirect('plans');
                            }else {
                                flash('plan_msg','Something went wrong with file upload','alert custom-danger');
                                redirect('plans');
                            }
                        }else{
                            flash('plan_msg','Plan Saved Successfully');
                            redirect('plans');
                        }
                    }
                    
                }else{
                    $this->view('plans/add',$data);
                }
            }
            //subimt
            if (isset($_POST['submit'])) {
                
                if(empty($data['actualcost'])){
                    $data['acost_err'] = 'Enter Actual Cost';
                }
                if(empty($data['evidence'])){
                    $data['evidence_err'] = 'Enter evidence of activity';
                }

                $data['status'] = 1;

                if (empty($data['level_err']) && empty($data['year_err']) && empty($data['theme_err']) 
                && empty($data['mdate_err']) && empty($data['activity_err']) && empty($data['reason_err'])
                && empty($data['estimate_err']) && empty($data['fdate_err']) && empty($data['tdate_err'])
                && empty($data['adate_err']) && empty($data['account_err']) && empty($data['office_err'])
                && empty($data['other_err']) && empty($data['acost_err']) && empty($data['evidence_err'])
                && empty($data['attach_err'])) {
                    
                    if ($this->planModel->create($data)) {
                        // flash('plan_msg','Plan Updated Successfully');
                        // redirect('plans');
                        if ($data['file']['size'] > 0) {
                            if (move_uploaded_file($fileTmpName,$fileDesination)) {
                                flash('plan_msg','Plan Saved Successfully');
                                redirect('plans');
                            }else {
                                flash('plan_msg','Something went wrong with file upload','alert custom-danger');
                                redirect('plans');
                            }
                        }else{
                            flash('plan_msg','Plan Saved Successfully');
                            redirect('plans');
                        }
                    }
                    
                }else{
                    $this->view('plans/add',$data);
                }
            }
        }else{
            redirect('users');
        }
    }
    public function edit($id)
    {
        $plan = $this->planModel->GetPlan($id);
        $years = $this->planModel->GetFiscalYears();
        $currentYear = $this->planModel->GetCurrentYear();
        $activities = $this->planModel->GetActivities();
        $accounts = $this->planModel->GetAccounts();
        if ($plan->Level === 1) {
            $collaborator = $this->planModel->GetCongregationAndGroups();
        }else{
            $collaborator = $this->planModel->GetGroups();
        }
        $data = [
            'years' => $years,
            'current' => $currentYear,
            'activities' => $activities,
            'accounts' => $accounts,
            'plan' => $plan,
            'level' => '',
            'year' => '',
            'planname' => '',
            'theme' => '',
            'meetingdate' => '',
            'activity' => '',
            'reason' => '',
            'costestimate' => '',
            'fromdate' => '',
            'todate' => '',
            'actualdate' => '',
            'account' => '',
            'office' => '',
            'officeother' => '',
            'collaborators' => $collaborator,
            'collaborator' => '',
            'collaboratorName' => '',
            'results' => '',
            'actualcost' => '',
            'evidence' => '',
            'remarks' => '',
            'filename' => '',

            'level_err' => '',
            'year_err' => '',
            'theme_err' => '',
            'mdate_err' => '',
            'activity_err' => '',
            'reason_err' => '',
            'estimate_err' => '',
            'fdate_err' => '',
            'tdate_err' => '',
            'adate_err' => '',
            'account_err' => '',
            'office_err' => '',
            'other_err' => '',
            'acost_err' => '',
            'evidence_err' => '',
            'attach_err' => ''
            
        ];
        $this->view('plans/edit',$data);
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $years = $this->planModel->GetFiscalYears();
            $currentYear = $this->planModel->GetCurrentYear();
            $activities = $this->planModel->GetActivities();
            $accounts = $this->planModel->GetAccounts();
            
            $data = [
                'years' => $years,
                'current' => $currentYear,
                'activities' => $activities,
                'accounts' => $accounts,
                'id' => trim($_POST['id']),
                'level' => !empty($_POST['level']) ? trim($_POST['level']) : '',
                'year' => trim($_POST['year']),
                'planname' => trim($_POST['planname']),
                'theme' => !empty($_POST['theme']) ? trim($_POST['theme']) : '',
                'meetingdate' => !empty($_POST['meetingdate']) ? trim($_POST['meetingdate']) : '',
                'activity' => !empty($_POST['activity']) ? trim($_POST['activity']) : '',
                'reason' => trim($_POST['reason']),
                'costestimate' => trim($_POST['costestimate']),
                'fromdate' => trim($_POST['fromdate']),
                'todate' => trim($_POST['todate']),
                'actualdate' => trim($_POST['actualdate']),
                'account' => !empty($_POST['account']) ? trim($_POST['account']) : '',
                'office' => !empty($_POST['office']) ? trim($_POST['office']) : '',
                'officeother' => !empty($_POST['officeother']) ? trim($_POST['officeother']) : '',
                'collaborator' => !empty($_POST['collaborator']) ? trim($_POST['collaborator']) : '',
                'collaboratorName' => !empty($_POST['collobatorName']) ? trim($_POST['collobatorName']) : '',
                'results' => !empty($_POST['results']) ? trim($_POST['results']) : '',
                'actualcost' => !empty($_POST['actualcost']) ? trim($_POST['actualcost']) : '',
                'evidence' => !empty($_POST['evidence']) ? trim($_POST['evidence']) : '',
                'remarks' => !empty($_POST['remarks']) ? trim($_POST['remarks']) : '',
                'status' => 0,
                'plan' => '',
                'file' => $_FILES['file'],
                'filename' => '',


                'level_err' => '',
                'year_err' => '',
                'theme_err' => '',
                'mdate_err' => '',
                'activity_err' => '',
                'reason_err' => '',
                'estimate_err' => '',
                'fdate_err' => '',
                'tdate_err' => '',
                'adate_err' => '',
                'account_err' => '',
                'office_err' => '',
                'other_err' => '',
                'acost_err' => '',
                'evidence_err' => '',
                'attach_err' => ''
            ];
            $encryId = encryptId($data['id']);
            $plan = $this->planModel->GetPlan($encryId);
            $data['plan'] = $plan;

            $fileTmpName = '';
            $fileDesination = '';

            if ($data['file']['size'] > 0) {
                $fileName = $data['file']['name'];
                $fileTmpName = $data['file']['tmp_name'];
                $fileSize = $data['file']['size'];
                $fileError = $data['file']['error'];

                $fileExt = explode('.', $fileName);
                $fileActualExt = strtolower(end($fileExt));
                $allowed = array('jpg','jpeg','png','pdf');

                if (in_array($fileActualExt,$allowed)) {
                    if ($fileError === 0) {
                      if ($fileSize < 1000000) {
                        $fileNameNew = uniqid('',true).'.'.$fileActualExt;
                        $data['filename'] = $fileNameNew;
                        $des = getcwd();
                        $fileDesination = $des.'/img/'.$fileNameNew;
                      }else {
                          $data['attach_err'] = "File size is too big";
                      }
                    }else {
                        $data['attach_err']= 'An error occurred during file upload';
                    }
                  }else{
                    $data['attach_err'] = 'Invalid File Type';
                  }
            }
           
            // //validation
            if (empty($data['level'])) {
                $data['level_err'] = 'Select Level';
            }
            if (empty($data['year'])) {
                $data['year_err'] = 'Select Year';
            }
            if (empty($data['theme'])) {
                $data['theme_err'] = 'Select Theme';
            }
            if (!empty($data['theme']) && $data['theme'] == 6 && empty($data['meetingdate'])) {
                $data['mdate_err'] = 'Select Meeting Date';
            }
            if (empty($data['activity'])) {
                $data['activity_err'] = 'Select Activity';
            }
            if (empty($data['reason'])) {
                $data['reason_err'] = 'Enter Reason of activity';
            }
            if (empty($data['costestimate'])) {
                $data['estimate_err'] = 'Enter Cost Estimate';
            }
            if (empty($data['fromdate'])) {
                $data['fdate_err'] = 'Select From Date';
            }
            if (empty($data['todate'])) {
                $data['tdate_err'] = 'Select To Date';
            }
            if (!empty($data['fromdate']) && !empty($data['todate']) && ($data['todate'] < $data['fromdate'])) {
                $data['tdate_err'] = 'To Date Cannot Be Lesser Than From Date';
            }
            if (!empty($data['fromdate']) && !empty($data['actualdate']) && ($data['actualdate'] < $data['fromdate'])) {
                $data['tdate_err'] = 'Actual Date Cannot Be Lesser Than From Date';
            }
            if (empty($data['account'])) {
                $data['account_err'] = 'Select Budget Account';
            }
            if (empty($data['office'])) {
                $data['office_err'] = 'Select Office Responsible';
            }
            if (!empty($data['office']) && $data['office'] == 5 && empty($data['officeother'])) {
                $data['other_err'] = 'Enter Other Office Responsible';
            }

            //save
            if (isset($_POST['save'])) {
                //check if no errors
                if (empty($data['level_err']) && empty($data['year_err']) && empty($data['theme_err']) 
                && empty($data['mdate_err']) && empty($data['activity_err']) && empty($data['reason_err'])
                && empty($data['estimate_err']) && empty($data['fdate_err']) && empty($data['tdate_err'])
                && empty($data['adate_err']) && empty($data['account_err']) && empty($data['office_err'])
                && empty($data['other_err']) && empty($data['attach_err'])) {

                   

                    if ($this->planModel->update($data)) {
                        // flash('plan_msg','Plan Updated Successfully');
                        // redirect('plans');
                        if ($data['file']['size'] > 0) {
                            if (move_uploaded_file($fileTmpName,$fileDesination)) {
                                flash('plan_msg','Plan Updated Successfully');
                                redirect('plans');
                            }else {
                                flash('plan_msg','Something went wrong with file upload','alert custom-danger');
                                redirect('plans');
                            }
                        }
                    }
                    
                }else{
                    $this->view('plans/edit',$data);
                }
            }
            //subimt
            if (isset($_POST['submit'])) {
                
                if(empty($data['actualcost'])){
                    $data['acost_err'] = 'Enter Actual Cost';
                }
                if(empty($data['evidence'])){
                    $data['evidence_err'] = 'Enter evidence of activity';
                }

                $data['status'] = 1;

                if (empty($data['level_err']) && empty($data['year_err']) && empty($data['theme_err']) 
                && empty($data['mdate_err']) && empty($data['activity_err']) && empty($data['reason_err'])
                && empty($data['estimate_err']) && empty($data['fdate_err']) && empty($data['tdate_err'])
                && empty($data['adate_err']) && empty($data['account_err']) && empty($data['office_err'])
                && empty($data['other_err']) && empty($data['acost_err']) && empty($data['evidence_err'])
                && empty($data['attach_err'])) {
                    
                    if ($this->planModel->update($data)) {
                        // flash('plan_msg','Plan Updated Successfully');
                        // redirect('plans');
                        if ($data['file']['size'] > 0) {
                            if (move_uploaded_file($fileTmpName,$fileDesination)) {
                                flash('plan_msg','Plan Submitted Successfully');
                                redirect('plans');
                            }else {
                                flash('plan_msg','Something went wrong with file upload','alert custom-danger');
                                redirect('plans');
                            }
                        }
                    }
                    
                }else{
                    $this->view('plans/edit',$data);
                }
            }
            
        }else{
            redirect('users');
        }
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $id = trim($_POST['id']);
            if ($this->planModel->delete($id)) {
                flash('plan_msg','Deleted Successfully!');
                redirect('plans');
            }
        }else{
            redirect('users');
        }
    }
}