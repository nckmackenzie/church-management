<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>

<div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card card-body bg-light mt-5">
                    <h5>User Account</h5>
                    <hr>
                    <form id="register" action="<?php echo URLROOT;?>/users/create" method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="userid">UserID</label>
                                    <input type="text" name="userid"
                                    class="form-control form-control-sm mandatory
                                    <?php echo (!empty($data['userid_err'])) ? 'is-invalid' : ''?>"
                                    value="<?php echo $data['userid'];?>" autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['userid_err'];?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">User Name</label>
                                    <input type="text" name="username"
                                    class="form-control form-control-sm mandatory
                                    <?php echo (!empty($data['username_err'])) ? 'is-invalid' : ''?>"
                                    value="<?php echo $data['username'];?>" autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['username_err'];?></span>
                                </div>
                            </div>
                        </div><!--End Of Row -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="usertype">User Type</label>
                                    <select name="usertype" id="usertype" 
                                            class="form-control form-control-sm">
                                        <option value="2">ADMINISTRATOR</option>    
                                        <option value="3" selected>STANDARD USER</option>    
                                        <option value="4">ELDER</option>    
                                        <option value="5">ACCOUNTS</option>    
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                <label for="district">District</label>
                                    <select name="district" id="district" 
                                            class="form-control form-control-sm" disabled>
                                        <?php foreach($data['districts'] as $district) : ?>
                                            <option value="<?php echo $district->ID;?>"
                                            <?php selectdCheck($data['district'],$district->ID)?>>
                                                <?php echo $district->districtName;?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="invalid-feedback"><?php echo $data['district_err'];?></span>
                                </div>
                            </div>
                        </div><!--End Of Row -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact">Contact</label>
                                    <input type="text" name="contact"
                                    class="form-control form-control-sm mandatory
                                    <?php echo (!empty($data['contact_err'])) ? 'is-invalid' : ''?>"
                                    value="<?php echo $data['contact'];?>" maxlength="10" autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['contact_err'];?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="active">Status</label>
                                    <select name="active" id="active" 
                                            class="form-control form-control-sm">
                                        <option value="1">Active</option>    
                                        <option value="0">Inactive</option>    
                                    </select>
                                </div>
                            </div>
                        </div><!--End Of Row -->
                        <div class="row">
                            <div class="col-2">
                            <button type="submit" class="btn btn-block btn-sm bg-navy">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $(window).on('load',function(){
            $('#district').val('');
        });

        $('#usertype').change(function() {
            if ($(this).val() == 4) {
                $('#district').attr('disabled',false);
                $('#district').prop("selectedIndex", 0);
            }else{
                $('#district').attr('disabled',true);
                $('#district').val('');
            }
        });
    });
</script>
</body>
</html>