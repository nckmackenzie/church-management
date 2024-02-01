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
            <div class="col-sm-12" id="alertBox">
                <?php if(count($data['errors']) > 0) : ?>
                    <div class="alert custom-danger" >
                        <?php foreach($data['errors'] as $error) : ?>
                            <div><?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-header">
                        <?php echo $data['isedit'] ? 'Edit Member' : 'Create Member' ;?>
                    </div>
                    <div class="card-body ">
                        <form action="<?php echo URLROOT;?>/members/create" method="post">
                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label for="name">Member Name</label>
                                        <input type="text" name="name"
                                               class="form-control form-control-sm mandatory"
                                               id="name" value="<?php echo $data['name'];?>"
                                               placeholder="jane doe..."
                                               autocomplete="off">
                                        <span class="invalid-feedback"></span>       
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="idno">ID No</label>
                                        <input type="text" name="idno"
                                            class="form-control form-control-sm"
                                            id="idno" value="<?php echo $data['idno'];?>"
                                            placeholder="1234567">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="dob">D.O.B</label>
                                        <input type="text" id="dob" name="dob"
                                            class="form-control form-control-sm"
                                            data-field="date" data-format="yyyy-MM-dd"
                                            value="<?php echo $data['dob'];?>"
                                            autocomplete="off">
                                        <div id="dtBox"></div>       
                                    </div>
                                </div>
                            </div><!--End Of Row--> 
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="gender">Sex</label>
                                        <select name="gender" id="gender" class="form-control form-control-sm">
                                            <option value="3" selected>Not Specified</option>
                                            <option value="1" <?php selectdCheck($data['gender'], "1")?>>Male</option>
                                            <option value="2" <?php selectdCheck($data['gender'],"2") ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="contact">Contact</label>
                                        <input type="text" name="contact" id="contact"
                                               class="form-control form-control-sm mandatory" 
                                               value="<?php echo $data['contact'];?>" maxlength="10" autocomplete="off"
                                               placeholder="0700000000">
                                        <span class="invalid-feedback"></span>
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
                                                <?php selectdCheck($data['maritalstatus'],$maritalstatus->ID) ?>>
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
                                            <option value="1" <?php selectdCheck($data['marriagetype'],1)?>>Christian</option>
                                            <option value="2" <?php selectdCheck($data['marriagetype'],2)?>>Civil</option>
                                            <option value="3" <?php selectdCheck($data['marriagetype'],3)?>>Customary</option>
                                            <option value="4" <?php selectdCheck($data['marriagetype'],4)?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="marriagedate">Marriage Date</label>
                                        <input type="text" id="marriagedate" name="marriagedate"
                                            class="form-control form-control-sm"
                                            data-field="date" data-format="yyyy-MM-dd"
                                            value="<?php echo $data['marriagedate'];?>"
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
                                            value="<?php echo $data['regdate'];?>"
                                            autocomplete="off">
                                        <div id="dtBoxRegistration"></div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="status">Status</label>
                                        <select name="status" id="status" class="form-control form-control-sm">
                                            <option value="1" <?php selectdCheck($data['status'],1)?>>Active</option>
                                            <option value="2" <?php selectdCheck($data['status'],2)?>>Dormant</option>
                                            <option value="4" <?php selectdCheck($data['status'],4)?>>Deceased</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="passeddate">Passed On</label>
                                        <input type="date" name="passeddate" id="passeddate"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['passeddate'];?>" 
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
                                            <option value="1" <?php selectdCheck($data['baptised'],1)?>>Yes</option>
                                            <option value="2" <?php selectdCheck($data['baptised'],2)?>>No</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Baptised Date</label>
                                        <input type="text" data-field="date" data-format="yyyy-MM-dd" 
                                        id="baptiseddate" name="baptiseddate" class="form-control form-control-sm" 
                                        value="<?php echo $data['bapitiseddate'];?>" disabled>
                                        <div id="dtBoxBaptised"></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="membershipstatus">Membership Status</label>
                                        <select name="membershipstatus" id="membershipstatus" 
                                                class="form-control form-control-sm">
                                            <option value="" disabled selected>Select Membership Status</option>
                                            <option value="1" <?php selectdCheck($data['membershipstatus'],1)?>>Full</option>
                                            <option value="2" <?php selectdCheck($data['membershipstatus'],2)?>>Adherent</option>
                                            <option value="3" <?php selectdCheck($data['membershipstatus'],3)?>>Associate</option>
                                            <option value="4" <?php selectdCheck($data['membershipstatus'],4)?>>Under-12</option>
                                            <option value="5" <?php selectdCheck($data['membershipstatus'],5)?>>Child</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="">Confirmed:</label>
                                        <select name="confirmed" id="confirmed"
                                                class="form-control form-control-sm" disabled>
                                            <option value="" disabled selected>Select</option>
                                            <option value="1"<?php selectdCheck($data['confirmed'],1)?>>Yes</option>
                                            <option value="0"<?php selectdCheck($data['confirmed'],0)?>>No</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Confirmed Date:</label>
                                        <input type="text" data-field="date" data-format="yyyy-MM-dd" 
                                            id="confirmeddate" name="confirmeddate"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['confirmeddate'];?>">
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
                                        <option value="1" <?php selectdCheck($data['commissioned'],1)?>>Yes</option>
                                        <option value="0" <?php selectdCheck($data['commissioned'],0)?>>No</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Commissioned Date:</label>
                                        <input type="text" data-field="date" data-format="yyyy-MM-dd" 
                                            id="commissioneddate" name="commissioneddate"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['commissioned'];?>">
                                        <div id="dtBoxCommissioned"></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="district">District:</label>
                                        <select name="district" id="district"
                                                class="form-control form-control-sm mandatory">
                                            <option value="" disabled selected>Select District</option>
                                            <?php foreach($data['districts'] as $district) : ?>
                                                <option value="<?php echo $district->ID;?>"
                                                <?php selectdCheck($data['district'],$district->ID);?>>
                                                    <?php echo $district->districtName;?>
                                                </option>
                                            <?php endforeach; ?> 
                                        </select>
                                        <span class="invalid-feedback"></span>
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
                                                <?php selectdCheck($data['position'],$position->ID);?>>
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
                                                <?php selectdCheck($data['occupation'],$occupation->industry);?>>
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
                                            value="<?php echo $data['occupationother'];?>"
                                            readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="email">Email:</label>
                                        <input type="email" id="email" name="email"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['email'];?>"
                                            placeholder="jdoe@example.com"
                                            autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="">Residence</label>
                                        <input type="text" id="residence" name="residence"
                                            class="form-control form-control-sm" 
                                            value="<?php echo $data['residence'];?>"
                                            placeholder="Athi"
                                            autocomplete="off">
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" value="<?php echo $data['isedit']; ?>" name="isedit">
                                    <input type="hidden" value="<?php echo $data['id']; ?>" name="id">
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/members/create.js"></script>
<script>
    $(function(){
        $('#occupation').select2();
        $("#dtBox").DateTimePicker();
        $("#dtBoxMarriage").DateTimePicker();
        $("#dtBoxRegistration").DateTimePicker();
        $("#dtBoxBaptised").DateTimePicker();
        $("#dtBoxConfirmed").DateTimePicker();
        $("#dtBoxCommissioned").DateTimePicker();
    });
</script>
</body>
</html>