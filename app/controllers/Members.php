<?php
class Members extends Controller {
    private $authmodel;
    private $memberModel;
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('users');
            exit;
        }
        $this->authmodel = $this->model('Auth');
        $this->memberModel = $this->model('Member');
    }
    public function index()
    {
        checkrights($this->authmodel,'members');
        $members = $this->memberModel->getMembers();
        $data = ['members' => $members];
        $this->view('members/index',$data);
    }
    public function add()
    {
        checkrights($this->authmodel,'members');
        $marriagestatus = $this->memberModel->getMarriageStatus();
        $districts = $this->memberModel->getDistricts();
        $positions = $this->memberModel->getPositions();
        $occupations = $this->memberModel->getOccupations();
        $data = [
            'marriagestatuses' => $marriagestatus,
            'districts' => $districts,
            'positions' => $positions,
            'occupations' => $occupations,
            'id' => '',
            'isedit' => false,
            'name' => '',
            'idno' => '',
            'dob' => '',
            'gender' => '',
            'contact' => '',
            'maritalstatus' => '',
            'marriagetype' => '',
            'marriagedate' => '',
            'regdate' => '',
            'status' => '',
            'passeddate' => '',
            'baptised' => '',
            'bapitiseddate' => '',
            'membershipstatus' => '',
            'confirmed' => '',
            'confirmeddate' => '',
            'commissioned' => '',
            'commissioneddate' => '',
            'district'  => '',
            'position'  => '',
            'occupation'  => '',
            'occupationother' => '',
            'residence' => '',
            'email' => '',
            'errors' => []
        ];
        $this->view('members/add',$data);
    }
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $marriagestatus = $this->memberModel->getMarriageStatus();
            $districts = $this->memberModel->getDistricts();
            $positions = $this->memberModel->getPositions();
            $occupations = $this->memberModel->getOccupations();
            $data = [
                'marriagestatuses' => $marriagestatus,
                'districts' => $districts,
                'positions' => $positions,
                'occupations' => $occupations,
                'isedit' => isset($_POST['isedit']) ? converttobool($_POST['isedit']) : false,
                'id' =>  isset($_POST['id']) && !empty(trim($_POST['id'])) ? trim($_POST['id']) : null,
                'name' => isset($_POST['name']) && !empty(trim($_POST['name'])) ? trim(strtolower($_POST['name'])) : null,
                'idno' => isset($_POST['idno']) && !empty(trim($_POST['idno']))  ? trim($_POST['idno']) : null,
                'dob' => isset($_POST['dob']) && !empty(trim($_POST['dob'])) ? date('Y-m-d',strtotime(trim($_POST['dob']))) : null,
                'gender' => isset($_POST['gender']) && !empty(trim($_POST['gender'])) ? trim($_POST['gender']) : null,
                'contact' => isset($_POST['contact']) && !empty(trim($_POST['contact'])) ? trim($_POST['contact']) : null,
                'maritalstatus' =>  isset($_POST['maritalstatus']) && !empty(trim($_POST['maritalstatus']))  ? trim($_POST['maritalstatus']) : null,
                'marriagetype' =>  isset($_POST['marriagetype']) && !empty(trim($_POST['marriagetype']))  ? $_POST['marriagetype'] : null,
                'marriagedate' =>  isset($_POST['marriagedate']) && !empty(trim($_POST['marriagedate']))  ? date('Y-m-d',strtotime(trim($_POST['marriagedate']))) : null,
                'regdate' => isset($_POST['regdate']) && !empty(trim($_POST['regdate'])) ? date('Y-m-d',strtotime(trim($_POST['regdate']))) : null,
                'status' =>  isset($_POST['status']) && !empty(trim($_POST['status'])) ? trim($_POST['status']) : 1,
                'passeddate' => isset($_POST['passeddate']) && !(empty($_POST['passeddate'])) ? date('Y-m-d',strtotime(trim($_POST['passeddate']))) : null,
                'baptised' => isset($_POST['baptised']) && !empty($_POST['baptised']) ? trim($_POST['baptised']) : null,
                'bapitiseddate' => isset($_POST['baptiseddate']) && !empty($_POST['baptiseddate']) ? date('Y-m-d',strtotime(trim($_POST['baptiseddate']))) : null,
                'membershipstatus' => isset($_POST['membershipstatus']) && !empty($_POST['membershipstatus']) ? trim($_POST['membershipstatus']) : null,
                'confirmed' => isset($_POST['confirmed']) && !empty($_POST['confirmed']) ? trim($_POST['confirmed']) : null,
                'confirmeddate' => isset($_POST['confirmeddate']) && !empty($_POST['confirmeddate']) ? date('Y-m-d',strtotime(trim($_POST['confirmeddate']))) : null,
                'commissioned' => isset($_POST['commissioned']) && !empty($_POST['commissioned']) ? trim($_POST['commissioned']) : null,
                'commissioneddate' => isset($_POST['commissioneddate']) &&  !empty($_POST['commissioneddate']) ? date('Y-m-d',strtotime(trim($_POST['commissioneddate']))) : null,
                'district'  => isset($_POST['district']) && !empty($_POST['district']) ? trim($_POST['district']) : null ,
                'position'  => isset($_POST['position']) && !empty($_POST['position']) ? trim($_POST['position']) : null,
                'occupation'  => isset($_POST['occupation']) && !empty($_POST['occupation']) ? trim($_POST['occupation']) : null,
                'occupationother' => isset($_POST['occupationother']) && !empty($_POST['occupationother']) ? trim($_POST['occupationother']) : null,
                'residence' => isset($_POST['residence']) && !empty($_POST['residence']) ? trim(strtolower($_POST['residence'])) : null,
                'email' => isset($_POST['email']) && !empty($_POST['email']) ? trim($_POST['email']) : null,
                'errors' => []
            ];
            //validate
            if (is_null($data['name'])) {
                array_push($data['errors'],'Enter Member Name.');
            }
            if (is_null($data['contact'])) {
                array_push($data['errors'],'Enter member contact.');
            }
            if (!is_null($data['idno'])) {
                if (!$this->memberModel->checkIDExists($data)) {
                   array_push($data['errors'],'ID No already exists.');
                }
            }
            if (is_null($data['district'])) {
                array_push($data['errors'],'Select district.');
            }
            if(!is_null($data['dob']) && !date_is_valid($data['dob'])){
                array_push($data['errors'],'Invalid date set as Date of birth.');
            }
            if(!is_null($data['marriagedate']) && !date_is_valid($data['marriagedate'])){
                array_push($data['errors'],'Invalid date set as date of marriage.');
            }
            if(!is_null($data['regdate']) && !date_is_valid($data['regdate'])){
                array_push($data['errors'],'Invalid date set as date of registration.');
            }
            if(!is_null($data['passeddate']) && !date_is_valid($data['passeddate'])){
                array_push($data['errors'],'Invalid date set as date of passing.');
            }
            if(!is_null($data['bapitiseddate']) && !date_is_valid($data['bapitiseddate'])){
                array_push($data['errors'],'Invalid date set as date of baptism.');
            }
            if(!is_null($data['confirmeddate']) && !date_is_valid($data['confirmeddate'])){
                array_push($data['errors'],'Invalid date set as date of confirmation.');
            }
            if(!is_null($data['commissioneddate']) && !date_is_valid($data['commissioneddate'])){
                array_push($data['errors'],'Invalid date set as date of commissioning.');
            }

            if(count($data['errors'])){
                $this->view('members/add',$data);
                exit;
            }

            if (!$this->memberModel->CreateUpdate($data)) {
                array_push($data['errors'],'Unable to save member. Try again later.');
                $this->view('members/add',$data);
                exit;
            }
               
            flash('member_msg', $data['isedit'] ? 'Edited successfully!' : 'Saved successfully');
            if(!$data['isedit']){
                redirect('members');
            }else{
                redirect('members?redirect=true');
            }
          }
    }
    public function resend()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => $_POST['id'],
                'contact' => $_POST['contact']
            ];
            $contact = $data['contact'];
            $countryPrexix ='+254';
            $sb = substr($contact,1);
            $new = $countryPrexix . $sb;
            $encoded = encrypt($data['id']);
            sendSms($new,strtoupper($data['name']),$encoded);
        }
    }
    public function edit($id)
    {
        checkrights($this->authmodel,'members');
        $marriagestatus = $this->memberModel->getMarriageStatus();
        $districts = $this->memberModel->getDistricts();
        $positions = $this->memberModel->getPositions();
        $occupations = $this->memberModel->getOccupations();
        $member = $this->memberModel->getMember($id);
        checkcenter($member->congregationId);
        $data = [
            'marriagestatuses' => $marriagestatus,
            'districts' => $districts,
            'positions' => $positions,
            'occupations' => $occupations,
            'member' => $member,
            'id' => $member->ID,
            'isedit' => true,
            'name' => strtoupper($member->memberName),
            'idno' => $member->idNo ?? '',
            'dob' =>  $member->dob ?? '',
            'gender' => $member->genderId ?? '',
            'contact' => $member->contact,
            'maritalstatus' => $member->maritalStatusId ?? '',
            'marriagetype' => $member->marriageType ?? '',
            'marriagedate' => $member->marriageDate ?? '',
            'regdate' => $member->registartionDate ??  '',
            'status' => $member->memberStatus ?? '',
            'passeddate' => $member->passedOn ?? '',
            'baptised' => $member->baptised ?? '',
            'bapitiseddate' => $member->baptisedDate ?? '',
            'membershipstatus' => $member->membershipStatus ?? '',
            'confirmed' => $member->confirmed ?? '',
            'confirmeddate' => $member->confirmedDate ?? '',
            'commissioned' => $member->commissioned ?? '',
            'commissioneddate' => $member->commissionedDate ?? '',
            'district'  => $member->districtId ?? '',
            'position'  => $member->positionId ?? '',
            'occupation'  => $member->occupation ?? '',
            'occupationother' => $member->other ?? '',
            'residence' => $member->residence ?? '',
            'email' => $member->email ?? '',
            'errors' => []
        ];
        $this->view('members/add',$data);
    }
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'id' => $_POST['id'],
                'name' => $_POST['name']
            ];
            if (isset($data['id'])) {
                if ($this->memberModel->delete($data)) {
                    flash('member_msg','Member Deleted Successfully!');
                    redirect('members');
                }
            }
        }
    }
    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $marriagestatus = $this->memberModel->getMarriageStatus();
            $districts = $this->memberModel->getDistricts();
            $positions = $this->memberModel->getPositions();
            $occupations = $this->memberModel->getOccupations();
            $data = [
                'id' => $_POST['id'],
                'name' => trim(strtolower($_POST['name'])),
                'idno' => !empty($_POST['idno']) ? trim($_POST['idno']) : NULL,
                'dob' => !empty($_POST['dob']) ? trim($_POST['dob']) : NULL,
                'gender' => trim($_POST['gender']),
                'contact' => trim($_POST['contact']),
                'maritalstatus' => !empty($_POST['maritalstatus']) ? trim($_POST['maritalstatus']) : NULL,
                'marriagetype' => !empty($_POST['marriagetype']) ? $_POST['marriagetype'] : NULL,
                'marriagedate' => !empty($_POST['marriagedate']) ? $_POST['marriagedate'] : NULL,
                'regdate' => !empty($_POST['regdate']) ? trim($_POST['regdate']) : NULL,
                'status' => trim($_POST['status']),
                'passeddate' => !(empty($_POST['passeddate'])) ? trim($_POST['passeddate']) : NULL,
                'baptised' => !empty($_POST['baptised']) ? trim($_POST['baptised']) : NULL,
                'bapitiseddate' => !empty($_POST['baptiseddate']) ? trim($_POST['baptiseddate']) : NULL,
                'membershipstatus' => !empty($_POST['membershipstatus']) ? 
                                        trim($_POST['membershipstatus']) : NULL,
                'confirmed' => !empty($_POST['confirmed']) ? trim($_POST['confirmed']) : NULL,
                'confirmeddate' => !empty($_POST['confirmeddate']) ? trim($_POST['confirmeddate']) : NULL,
                'commissioned' => !empty($_POST['commissioned']) ? trim($_POST['commissioned']) : NULL,
                'commissioneddate' => !empty($_POST['commissioneddate']) ?
                                        trim($_POST['commissioneddate']) : NULL,
                'district'  => !empty($_POST['district']) ? trim($_POST['district']) : NULL ,
                'position'  => !empty($_POST['position']) ? trim($_POST['position']) : NULL,
                'occupation'  => !empty($_POST['occupation']) ? trim($_POST['occupation']) : NULL,
                'occupationother' => !empty($_POST['occupationother']) ?
                                        trim($_POST['occupationother']) : NULL,
                'residence' => !empty($_POST['residence']) ? trim(strtolower($_POST['residence'])) : NULL,
                'email' => !empty($_POST['email']) ? trim($_POST['email']) : NULL,
                'name_err' => '',
                'contact_err' => '',
                'idno_err' => '',
                'one' => 1,
                'two' => 2,
                'three' => 3,
                'four' => 4,
            ];
            //validate
            if (empty($data['name'])) {
                $data['name_err'] ='Enter Member Name';
            }
            if (empty($data['contact'])) {
                $data['contact_err'] ='Enter Member Contact';
            }
            if (!empty($data['idno'])) {
                if (!$this->memberModel->checkIDExists($data)) {
                   $data['idno_err'] = 'ID No Already Entered';
                }
            }
            if (empty($data['name_err']) && empty($data['contact_err']) && empty($data['idno_err'])) {
                if ($this->memberModel->update($data)) {
                    flash('member_msg','Member Updated Successfully!');
                    redirect('members');
                }
                else{
                    flash('member_msg','Something Went Wrong!','alert alert-danger');
                    redirect('members');
                }
            }
            else{
                $this->view('members/edit',$data);
            }
        }
    }
    public function change_district()
    {
        checkrights($this->authmodel,'change district');
        $members = $this->memberModel->getMembers();
        $districts = $this->memberModel->getDistricts();
        $data = [
            'members' => $members,
            'districts' => $districts,
            'date' => date('Y-m-d')
        ];
        $this->view('members/change_district',$data);
    }
    public function districtchange()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data= [
                'member' => $_POST['member'],
                'district' => ''
            ];
            if (isset($data['member'])) {
                $data['district'] = $this->memberModel->getMemberDistrict($data);
                // echo $data['district']->ID;
                echo '<option value="'.$data['district']->ID.'">'.$data['district']->districtName.'</option>';
            }
        }
    }
    public function updatedistrict()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'member' => $_POST['member'],
                'old' => $_POST['old'],
                'new' => $_POST['newd'],
                'oldname' => trim(strtolower($_POST['oldname'])),
                'newname' => trim(strtolower($_POST['newname'])),
                'name' => trim(strtolower($_POST['name'])),
                'date' => isset($_POST['date']) && !empty($_POST['date']) ? date('Y-m-d',strtotime(trim($_POST['date']))) : date('Y-m-d'),
                'err' => '',
                'success' => ''
            ];
            //validate
            if (empty($data['member'])) {
                $data['err'] = 'Select Member';
            }
            if (empty($data['old'])) {
                $data['err'] = 'Select Current District';
            }
            if (empty($data['new'])) {
                $data['err'] = 'Select New District';
            }
            if (!empty($data['old']) && !empty($data['new'])) {
                if ($data['old'] == $data['new']) {
                    $data['err'] = 'Current & New District Cannot Be Same';
                }
            }
            if (!empty($data['err'])) {
                echo '
                    <div class="alert custom-danger">'.$data['err'].'</div>
                ';
            }
            if (empty($data['err'])) {
                if ($this->memberModel->changedistrict($data)) {
                    echo '
                        <div class="alert custom-success">District Changed Successfully</div>
                    ';
                }
                else{
                   die('Something went wrong');
                }
            }
        }
    }
    public function transfer()
    {
        // checkrights($this->authmodel,'cash deposits');
        $congregations = $this->memberModel->getCongregations();
        $data = [
            'congregations' => $congregations,
            'congregationfrom' => '',
            'members' => '',
            'member' => '',
            'district' => '',
            'newcongregation' => '',
            'districts' => '',
            'newdistrict' => '',
            'date' => '',
            'reason' => '',
            'newcong_err' => '',
            'newdist_err' => '',
            'date_err' => '',
            'reason_err' => ''
        ];
        $this->view('members/transfer',$data);
    }
    public function getmemberbycong()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST =filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $congregation = trim($_POST['congregation']);
            $members = $this->memberModel->getMembersByCongregation($congregation);
            // print_r($members);
            foreach ($members as $member ) {
                echo '<option value="'.$member->ID.'">'.$member->memberName.'</option>';
            }
        }
    }
    public function getdistrictbycong()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST =filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $congregation = trim($_POST['cong']);
            $districts = $this->memberModel->getDistrictsByCongregation($congregation);
            // print_r($districts);
            foreach ($districts as $district ) {
                echo '<option value="'.$district->ID.'">'.$district->districtName.'</option>';
            }
        }
    }
    public function transfermember()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST =filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $congregations = $this->memberModel->getCongregations();
            $data = [
                'congregations' => $congregations,
                'congregationfrom' => trim($_POST['congregationfrom']),
                'members' => '',
                'member' => trim($_POST['member']),
                'district' => !empty($data['district']) ? trim($_POST['district']) : NULL,
                'newcongregation' => trim($_POST['newcongregation']),
                'districts' => '',
                'newdistrict' => !empty($_POST['newdistrict']) ? trim($_POST['newdistrict']) : NULL,
                'date' => trim($_POST['date']),
                'reason' => trim($_POST['reason']),
                'membername' => trim($_POST['membername']),
                'currentname' => trim($_POST['currentname']),
                'newname' => trim($_POST['newname']),
                'newcong_err' => '',
                'newdist_err' => '',
                'date_err' => '',
                'reason_err' => ''
            ];
            $data['members'] = $this->memberModel->getMembersByCongregation($data['congregationfrom']);
            $data['districts'] = $this->memberModel->getDistrictsByCongregation($data['newcongregation']);
            //validate
            if ($data['newcongregation'] == $data['congregationfrom']) {
                $data['newcong_err'] = 'Current And New Congregations Cannot Be Same';
            }
            if (empty($data['newdistrict'])) {
                $data['newdist_err'] = 'Select New District';
            }
            if (empty($data['date'])) {
                $data['date_err'] = 'Select Transfer Date';
            }
            if (empty($data['reason'])) {
                $data['reason_err'] = 'Enter Transfer Reason';
            }
            if (empty($data['newcong_err']) && empty($data['newdist_err']) && empty($data['date_err'])
                && empty($data['reason_err'])) {
                if ($this->memberModel->memberTransfer($data)) {
                    flash('transfer_msg','Member Transfered Successfully');
                    redirect('members/transfer');
                } 
                else{
                    flash('transfer_msg','Something Went Wrong');
                    redirect('members/transfer');
                }
            }
            else{
                $this->view('members/transfer',$data);
            }
        }
        else{
            $congregations = $this->memberModel->getCongregations();
            $data = [
                'congregations' => $congregations,
                'congregationfrom' => '',
                'member' => '',
                'district' => '',
                'newcongregation' => '',
                'newdistrict' => '',
                'date' => '',
                'reason' => '',
                'newcong_err' => '',
                'newdist_err' => '',
                'date_err' => '',
                'reason_err' => ''
            ];
            $this->view('members/transfer',$data);
        }
    }
    public function family()
    {
        checkrights($this->authmodel,'member family');
        $members = $this->memberModel->getMembersByCongregation($_SESSION['congId']);
        $relations = $this->memberModel->getRelationships();
        $data = [
            'members' => $members,
            'relations' => $relations
        ];
        $this->view('members/family',$data);
    }
    public function createfamily()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $data = [
                'membername' => trim($_POST['membername']),
                'member' => trim($_POST['member']),
                'details' => $_POST['table_data']
            ];
            $this->memberModel->createfamily($data);
        }
    }
    public function checkfamily()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $_POST = filter_input_array(INPUT_POST,FILTER_UNSAFE_RAW);
            $member = trim($_POST['memberid']);
            echo $this->memberModel->checkfamily($member);
        }
    }
    public function sendmessage()
    {
        $data = ['members' => $this->memberModel->getmembersbydistrict()];
        $this->view('members/sendmessage',$data);
        exit;
    }
    public function sendmessageaction()
    {
        if($_SERVER['REQUEST_METHOD'] === 'POST')
        {
            $fields = json_decode(file_get_contents('php://input'));
            $data = [
                'members' => $fields->members,
                'message' => isset($fields->message) && !empty(trim($fields->message)) ? trim(htmlentities($fields->message)) : null,
            ];
            //validate
            if(is_null($data['message']) || empty($data['members'])){
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Provide all required fields']);
                exit;
            }

            $contacts = $this->memberModel->getcontacts($data['members']);
            $results = sendgeneral($contacts,$data['message']);
            echo json_encode(['result' => $results,'success' => true]);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }

    public function getmembers()
    {
        if($_SERVER['REQUEST_METHOD'] === 'GET')
        {
            $type = filter_input(INPUT_GET,'type',FILTER_SANITIZE_SPECIAL_CHARS);
            $value = filter_input(INPUT_GET,'value',FILTER_SANITIZE_SPECIAL_CHARS);
            $data = [
                'members' => [],
                'type' => !empty($type) ? $type : null,
                'value' => !empty($value) ? $value : null
            ];

            //validate
            if(is_null($data['type']) || is_null($data['value'])){
                http_response_code(400);
                echo json_encode(['success' => false,'message' => 'Provide all required fields']);
                exit;
            }

            $members = $this->memberModel->get_members_by_criteria($type,$value);
            foreach($members as $member){
                array_push($data['members'],[
                    'id' => $member->id,
                    'label' => strtoupper($member->memberName)
                ]);
            }

            echo json_encode(['data' => $data['members'],'success' => true]);
            exit;
        }
        else
        {
            redirect('users/deniedaccess');
            exit;
        }
    }
}