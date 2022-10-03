<?php
class Members extends Controller {
    public function __construct()
    {
        if (!isset($_SESSION['userId'])) {
            redirect('');
        }
        else{
            $this->memberModel = $this->model('Member');
        }
    }
    public function index()
    {
        $form = 'Members';
        if ($_SESSION['userType'] > 2 && !$this->memberModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $members = $this->memberModel->getMembers();
        $data = ['members' => $members];
        $this->view('members/index',$data);
    }
    public function add()
    {
        $form = 'Members';
        if ($_SESSION['userType'] > 2 && !$this->memberModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $marriagestatus = $this->memberModel->getMarriageStatus();
        $districts = $this->memberModel->getDistricts();
        $positions = $this->memberModel->getPositions();
        $occupations = $this->memberModel->getOccupations();
        $data = [
            'marriagestatuses' => $marriagestatus,
            'districts' => $districts,
            'positions' => $positions,
            'occupations' => $occupations,
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
            'name_err' => '',
            'contact_err' => '',
            'idno_err' =>'',
            'district_err' => '',
            'one' => '1',
            'two' => 2,
            'three' => 3,
            'four' => 4,
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
                // 'marriagestatuses' => $marriagestatus,
                // 'districts' => $districts,
                // 'positions' => $positions,
                // 'occupations' => $occupations,
                'id' => '',
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
                'district_err' => '',
                'idno_err' =>'',
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
            if (empty($data['district']) || $data['district'] == NULL) {
               $data['district_err'] = 'Select District';
            }
            if (empty($data['name_err']) && empty($data['contact_err']) && empty($data['district_err']) 
                && empty($data['idno_err'])) {
               // echo json_encode($data);
                if ($this->memberModel->create($data)) {
                    flash('member_msg','Member Added Successfully!');
                    redirect('members');
                }
                else{
                    flash('member_msg','Something Went Wrong!','alert alert-danger');
                    redirect('members');
                }
            }
            else{
                $this->view('members/add',$data);
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
        $form = 'Members';
        if ($_SESSION['userType'] > 2 && !$this->memberModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $marriagestatus = $this->memberModel->getMarriageStatus();
        $districts = $this->memberModel->getDistricts();
        $positions = $this->memberModel->getPositions();
        $occupations = $this->memberModel->getOccupations();
        $member = $this->memberModel->getMember($id);
        $data = [
            'marriagestatuses' => $marriagestatus,
            'districts' => $districts,
            'positions' => $positions,
            'occupations' => $occupations,
            'member' => $member
        ];
        if ($data['member']->congregationId != $_SESSION['congId']) {
            redirect('members');
        }
        elseif ($data['member']->congregationId == $_SESSION['congId']) {
            $this->view('members/edit',$data);
        }
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
        $form = 'Change District';
        if ($_SESSION['userType'] > 2 && !$this->memberModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
        $members = $this->memberModel->getMembers();
        $districts = $this->memberModel->getDistricts();
        $data = [
            'members' => $members,
            'districts' => $districts
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
        $form = 'Transfer Member';
        if ($_SESSION['userType'] > 2 && !$this->memberModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
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
        $form = 'Member Family';
        if ($_SESSION['userType'] > 2 && !$this->memberModel->CheckRights($form)) {
            redirect('users/deniedaccess');
            exit();
        }
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
}