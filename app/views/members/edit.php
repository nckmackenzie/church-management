<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
             <a href="<?php echo URLROOT;?>/members" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-header">
                        Update Member
                    </div>
                    <div class="card-body ">
                        <form action="<?php echo URLROOT;?>/members/update" method="post">
                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label for="name">Member Name</label>
                                        <input type="text" name="name"
                                            class="form-control form-control-sm mandatory
                                            <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''?>"
                                            id="name" 
                                            value="<?php echo strtoupper($data['member']->memberName);?>"
                                            autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['name_err'];?></span>       
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="idno">ID No</label>
                                        <input type="text" name="idno"
                                            class="form-control form-control-sm"
                                            id="idno"
                                            value="<?php echo $data['member']->idNo;?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="dob">D.O.B</label>
                                        <input type="text" id="dob" name="dob"
                                            class="form-control form-control-sm"
                                            data-field="date" data-format="yyyy-MM-dd"
                                            value="<?php echo $data['member']->dob;?>"
                                            autocomplete="off" readonly>
                                        <div id="dtBox"></div>       
                                    </div>
                                </div>
                            </div><!--End Of Row--> 
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="gender">Gender</label>
                                        <select name="gender" id="gender" class="form-control form-control-sm">
                                            <option value="3" selected>Not Specified</option>
                                            <option value="1" <?php selectdCheck($data['member']->genderId, 1)?>>Male</option>
                                            <option value="2" <?php selectdCheck($data['member']->genderId,2) ?>>Female</option>
                                            
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="contact">Contact</label>
                                        <input type="text" name="contact" id="contact"
                                        class="form-control form-control-sm mandatory
                                        <?php echo (!empty($data['contact_err'])) ? 'is-invalid' : ''?>" 
                                        value="<?php echo $data['member']->contact;?>" maxlength="10" autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['contact_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="maritalstatus">Marital Status</label>
                                        <select name="maritalstatus" id="maritalstatus"
                                                class="form-control form-control-sm">
                                            <option value=""selected disabled>Select Marital Status:</option>
                                            <?php foreach($data['marriagestatuses'] as $maritalstatus) : ?>
                                                <option value="<?php echo $maritalstatus->ID;?>"
                                                <?php selectdCheck($data['member']->maritalStatusId,$maritalstatus->ID) ?>>
                                                    <?php echo $maritalstatus->maritalStatus;?>
                                                </option>
                                            <?php endforeach; ?>        
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="marriagetype">Marriage Type</label>
                                        <select name="marriagetype" id="marriagetype" class="form-control form-control-sm" disabled>
                                            <option value=""selected>Select Marriage Type:</option>
                                            <option value="1" <?php selectdCheck($data['member']->marriageType,1)?>>Christian</option>
                                            <option value="2" <?php selectdCheck($data['member']->marriageType,2)?>>Civil</option>
                                            <option value="3" <?php selectdCheck($data['member']->marriageType,3)?>>Customary</option>
                                            <option value="4" <?php selectdCheck($data['member']->marriageType,4)?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="marriagedate">Marriage Date</label>
                                        <input type="text" id="marriagedate" name="marriagedate"
                                            class="form-control form-control-sm"
                                            data-field="date" data-format="yyyy-MM-dd"
                                            value="<?php echo $data['member']->marriageDate;?>"
                                            autocomplete="off" disabled>
                                        <div id="dtBoxMarriage"></div>       
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="regdate">Registration Date</label>
                                        <input type="text" id="regdate" name="regdate"
                                            class="form-control form-control-sm"
                                            data-field="date" data-format="yyyy-MM-dd"
                                            value="<?php echo $data['member']->registrationDate;?>"
                                            autocomplete="off" readonly>
                                        <div id="dtBoxRegistration"></div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select name="status" id="status" class="form-control form-control-sm">
                                            <option value="1" <?php selectdCheck($data['member']->memberStatus,1)?>>Active</option>
                                            <option value="2" <?php selectdCheck($data['member']->memberStatus,2)?>>Dormant</option>
                                            <option value="4" <?php selectdCheck($data['member']->memberStatus,4)?>>Deceased</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="passeddate">Passed On</label>
                                        <input type="date" name="passeddate" id="passeddate"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['member']->passedOn;?>" 
                                            disabled>       
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="baptised">Baptised</label>
                                        <select name="baptised" id="baptised" class="form-control form-control-sm">
                                            <option value="" disabled selected>Select</option>
                                            <option value="1" <?php selectdCheck($data['member']->baptised,1)?>>Yes</option>
                                            <option value="2" <?php selectdCheck($data['member']->baptised,2)?>>No</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Baptised Date</label>
                                        <input type="text" data-field="date" data-format="yyyy-MM-dd" 
                                        id="baptiseddate" name="baptiseddate" class="form-control form-control-sm" 
                                        value="<?php echo $data['member']->baptisedDate;?>" disabled>
                                        <div id="dtBoxBaptised"></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="membershipstatus">Membership Status</label>
                                        <select name="membershipstatus" id="membershipstatus" 
                                                class="form-control form-control-sm">
                                            <option value="" disabled selected>Select Membership Status</option>
                                            <option value="1" <?php selectdCheck($data['member']->membershipStatus,1)?>>Full</option>
                                            <option value="2" <?php selectdCheck($data['member']->membershipStatus,2)?>>Adherent</option>
                                            <option value="3" <?php selectdCheck($data['member']->membershipStatus,3)?>>Associate</option>
                                            <option value="4" <?php selectdCheck($data['member']->membershipStatus,4)?>>Under-12</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="">Confirmed:</label>
                                        <select name="confirmed" id="confirmed"
                                                class="form-control form-control-sm" disabled>
                                            <option value="" disabled selected>Select</option>
                                            <option value="1"<?php selectdCheck($data['member']->confirmed,1)?>>Yes</option>
                                            <option value="0"<?php selectdCheck($data['member']->confirmed,0)?>>No</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Confirmed Date:</label>
                                        <input type="text" data-field="date" data-format="yyyy-MM-dd" 
                                            id="confirmeddate" name="confirmeddate"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['member']->confirmedDate;?>" disabled>
                                        <div id="dtBoxConfirmed"></div>
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="">Commissioned:</label>
                                        <select name="commissioned" id="commissioned"
                                                class="form-control form-control-sm" disabled>
                                        <option value="" disabled selected>Select</option>
                                        <option value="1" <?php selectdCheck($data['member']->commissioned,1)?>>Yes</option>
                                        <option value="0" <?php selectdCheck($data['member']->commissioned,0)?>>No</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Commissioned Date:</label>
                                        <input type="text" data-field="date" data-format="yyyy-MM-dd" 
                                            id="commissioneddate" name="commissioneddate"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['member']->commissionedDate;?>" disabled>
                                        <div id="dtBoxCommissioned"></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="district">District:</label>
                                        <select name="district" id="district"
                                                class="form-control form-control-sm">
                                            <option value="" disabled selected>Select District</option>
                                            <?php foreach($data['districts'] as $district) : ?>
                                                <option value="<?php echo $district->ID;?>"
                                                <?php selectdCheck($data['member']->districtId,$district->ID);?>>
                                                    <?php echo $district->districtName;?>
                                                </option>
                                            <?php endforeach; ?> 
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="position">Position:</label>
                                        <select name="position" id="position"
                                                class="form-control form-control-sm">
                                            <option value="" disabled selected>Select Position</option>
                                            <?php foreach($data['positions'] as $position) : ?>
                                                <option value="<?php echo $position->ID;?>"
                                                <?php selectdCheck($data['member']->positionId,$position->ID);?>>
                                                    <?php echo $position->positionName;?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="occupation">Occupation:</label>
                                        <select name="occupation" id="occupation" 
                                                class="form-control form-control-sm">
                                            <option value="" disabled selected>Select Occupation</option>
                                            <?php foreach($data['occupations'] as $occupation) : ?>
                                                <option value="<?php echo trim($occupation->industry);?>"
                                                <?php selectdCheck($data['member']->occupation,$occupation->industry);?>>
                                                    <?php echo $occupation->industry;?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="">Specify Occupation:</label>
                                        <input type="text" id="other" name="other"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['member']->other;?>"
                                            readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="email">Email:</label>
                                        <input type="email" id="email" name="email"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['member']->email;?>"
                                            autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="">Residence</label>
                                        <input type="text" id="residence" name="residence"
                                            class="form-control form-control-sm" 
                                            value="<?php echo $data['member']->residence;?>"
                                            autocomplete="off">
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo $data['member']->ID;?>">
                                </div>
                            </div>
                        </form>    
                    </div>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
     let calculateAge = function(birthday) {
        let now = new Date();
        let past = new Date(birthday);
        let nowYear = now.getFullYear();
        let pastYear = past.getFullYear();
        let age = nowYear - pastYear;

        return age;
	};

    $(function(){
        $('#occupation').select2();
        $("#dtBox").DateTimePicker();
        $("#dtBoxMarriage").DateTimePicker();
        $("#dtBoxRegistration").DateTimePicker();
        $("#dtBoxBaptised").DateTimePicker();
        $("#dtBoxConfirmed").DateTimePicker();
        $("#dtBoxCommissioned").DateTimePicker();

        $('#dob').change(function(){
            var $birthday = $('#dob').val();
            // alert('Your age is ' + calculateAge($birthday) + ' years');
            var years = calculateAge($birthday);
            if (years < 18) {
                $('#idno').attr('disabled',true);
                $('#maritalstatus').attr('disabled',true);
                $('#marriagetype').attr('disabled',true);
                $('#marriagedate').attr('disabled',true);
                $('#idno').val('');
                $('#maritalstatus').val('');
                $('#marriagetype').val('');
            }
            else{
                $('#idno').attr('disabled',false);
                $('#maritalstatus').attr('disabled',false);
            }
        });

        $('#maritalstatus').on('change', function(){
           if($(this).val() == '2'){
             $('#marriagetype').attr('disabled',false);
             $('#marriagedate').attr('disabled',false);
            
            }else{
                $('#marriagetype').attr('disabled',true);
                $('#marriagedate').attr('disabled',true);
                $("#marriagetype option:selected").prop("selected", false)
            }
        });
        $('#status').on('change',function(){
           if($(this).val() == '4'){
             $('#passeddate').attr('disabled',false);
           }
           else{
            $('#passeddate').attr('disabled',true);
           }    
        });

        $('#membershipstatus').on('change',function(){
           if($(this).val() == '1'){
               $('#confirmed').attr('disabled',false);
               $('#commissioned').attr('disabled',false);
           }
           else{
               $('#confirmed').attr('disabled',true);
               $('#commissioned').attr('disabled',true);
               $('#confirmeddate').attr('disabled',true);
               $('#commissioneddate').attr('disabled',true);
           }
        });

        $('#confirmed').on('change',function(){
            if($(this).val() == '1'){
              $('#confirmeddate').attr('disabled',false);
            }
            else{
              $('#confirmeddate').attr('disabled',true); 
            }
        });

        $('#commissioned').on('change',function(){
            if($(this).val() == '1'){
              $('#commissioneddate').attr('disabled',false);
            }
            else{
              $('#commissioneddate').attr('disabled',true); 
            }
        });

        $('#baptised').on('change',function(){
            if($(this).val() == '1'){
                $('#baptiseddate').attr('disabled',false);
            }
            else{
                $('#baptiseddate').attr('disabled',true);
            }
        });

        $('#occupation').on('change',function(){
            if ($(this).val() == 'OTHER') {
                $('#other').attr('readonly',false);
            }
            else{
                $('#other').attr('readonly',true);
            }
           
        });
    });
</script>
</body>
</html>