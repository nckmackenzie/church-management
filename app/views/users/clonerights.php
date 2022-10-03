<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto mt-2">
                <?php flash('clone_msg');?>
                <div class="card bg-light">
                    <div class="card-header">Clone User Rights</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/users/clonemenu" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="user1">User To Clone From</label>
                                        <select name="user1" id="user1" 
                                                class="form-control form-control-sm 
                                                <?php echo (!empty($data['user1_err'])) ? 'is-invalid' : ''?>">
                                            <option value="0">Select User 1 </option>
                                            <?php foreach($data['users'] as $user) : ?>
                                                <option value="<?php echo $user->ID;?>" <?php selectdCheck($data['user1'],$user->ID)?>><?php echo $user->UserName;?></option>
                                            <?php endforeach;?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['user1_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="user2">User To Clone To</label>
                                        <select name="user2" id="user2" 
                                                class="form-control form-control-sm 
                                                <?php echo (!empty($data['user2_err'])) ? 'is-invalid' : '' ?>">
                                            <option value="0">Select User 2 </option>
                                            <?php foreach($data['users'] as $user) : ?>
                                                <option value="<?php echo $user->ID;?>" <?php selectdCheck($data['user2'],$user->ID)?>><?php echo $user->UserName;?></option>
                                            <?php endforeach;?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['user2_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-2">
                                    <button type="submit" class="btn btn-block btn-sm bg-navy">Save</button>
                                </div>
                            </div>
                        </form>
                    </div><!--Card-body -->
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
</body>
</html>  