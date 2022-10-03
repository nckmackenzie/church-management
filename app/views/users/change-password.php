<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="content-wrapper">
   
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card card-body bg-light mt-5">
                    <h5>Change Password</h5>
                <hr>
                <form action="<?php echo URLROOT;?>/users/password" method="post">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="old">Old Password:</label>
                                <input type="password" id="old" 
                                class="form-control form-control-sm mandatory
                                <?php echo (!empty($data['old_err'])) ? 'is-invalid' : ''?>"
                                name="old" value="<?php echo $data['old'];?>"
                                placeholder="Old Password" autocomplete="off">
                                <span class="invalid-feedback"><?php echo $data['old_err'];?></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="new">New Password:</label>
                                <input type="password" id="new" 
                                class="form-control form-control-sm mandatory
                                <?php echo (!empty($data['new_err'])) ? 'is-invalid' : ''?>"
                                name="new" value="<?php echo $data['new'];?>"
                                placeholder="New Password" autocomplete="off">
                                <span class="invalid-feedback"><?php echo $data['new_err'];?></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="confirm">confirm Password:</label>
                                <input type="password" id="confirm" 
                                class="form-control form-control-sm mandatory
                                <?php echo (!empty($data['confirm_err'])) ? 'is-invalid' : ''?>"
                                name="confirm" value="<?php echo $data['confirm'];?>"
                                placeholder="Confirm Password" autocomplete="off">
                                <span class="invalid-feedback"><?php echo $data['confirm_err'];?></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2 mt-2">
                            <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php require APPROOT . '/views/inc/footer.php'?>