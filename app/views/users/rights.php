<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
         <div class="row">
             <div class="col-md-9 mx-auto mt-2">
                <form action="<?php echo URLROOT;?>/users/getusersrights" method="post">
                    <div class="card bg-light">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-sm-6">
                                    <select name="user" id="user" class="form-control form-control-sm 
                                            <?php echo (!empty($data['user_err'])) ? 'is-invalid' : '' ?>">
                                        <option value="0">Select User</option>
                                        <?php foreach($data['users'] as $user) :?>
                                            <option value="<?php echo $user->ID;?>"><?php echo $user->UserName;?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="invalid-feedback"><?php echo $data['user_err'];?></span>
                                </div>
                                <div class="col-sm-3">
                                    <button type="submit" class="btn btn-sm btn-success">Load</button>
                                </div>
                            </div>
                        </div>
                    </div><!--</Card -->
                </form>
             </div>
         </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
</body>
</html>  