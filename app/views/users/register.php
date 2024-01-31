<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>

<div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
             <a href="<?php echo URLROOT;?>/users/all" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-sm-12" id="alertBox">
                <?php if(count($data['errors']) > 0) : ?>
                    <div class="alert custom-danger">
                        <?php foreach($data['errors'] as $error) : ?>
                            <div><?php echo $error;?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mx-auto d-none" id="success-msg">
                <div class="alert custom-success alert-dismissible fade show mt-2">
                    <span class="message">asdsad</span>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
            </div>
        </div>
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
                                    class="form-control form-control-sm mandatory"
                                    value="<?php echo $data['userid'];?>" autocomplete="off" placeholder="jdoe">
                                    <span class="invalid-feedback"></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">User Name</label>
                                    <input type="text" name="username"
                                    class="form-control form-control-sm mandatory"
                                    value="<?php echo $data['username'];?>" autocomplete="off" placeholder="Jane Doe">
                                    <span class="invalid-feedback"></span>
                                </div>
                            </div>
                        </div><!--End Of Row -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="usertype">User Type</label>
                                    <select name="usertype" id="usertype" 
                                            class="form-control form-control-sm">
                                        <option value="2" <?php selectdCheck($data['usertype'],"2") ?>>ADMINISTRATOR</option>    
                                        <option value="3" <?php selectdCheck($data['usertype'],"3") ?>>STANDARD USER</option>    
                                        <option value="4" <?php selectdCheck($data['usertype'],"4") ?>>ELDER</option>    
                                        <option value="5" <?php selectdCheck($data['usertype'],"5") ?>>ACCOUNTS ADMIN</option>    
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                <label for="district">District</label>
                                    <select name="district" id="district" 
                                            class="form-control form-control-sm" disabled>
                                            <option value="" selected disabled>Select district</option>
                                        <?php foreach($data['districts'] as $district) : ?>
                                            <option value="<?php echo $district->ID;?>"
                                            <?php selectdCheck($data['district'],$district->ID)?>>
                                                <?php echo $district->districtName;?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                            </div>
                        </div><!--End Of Row -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact">Contact</label>
                                    <input type="text" name="contact"
                                    class="form-control form-control-sm mandatory"
                                    value="<?php echo $data['contact'];?>" maxlength="10" 
                                    autocomplete="off" placeholder="0700000000">
                                    <span class="invalid-feedback"></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="active">Status</label>
                                    <select name="active" id="active" 
                                            class="form-control form-control-sm">
                                        <option value="1" <?php selectdCheck($data['active'],"1") ?>>Active</option>    
                                        <option value="0" <?php selectdCheck($data['active'],"0") ?>>Inactive</option>    
                                    </select>
                                </div>
                            </div>
                        </div><!--End Of Row -->
                        <div class="row">
                            <div class="col-sm-2">
                                <button   button type="submit" class="btn btn-block btn-sm bg-navy">Save</button>
                                <input type="hidden" id="id" name="id" value="<?php echo $data['id'];?>"  >
                                <input type="hidden" id="isedit" name="isedit" value="<?php echo $data['isedit'];?>"  >
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/users/create.js"></script>

</body>
</html>