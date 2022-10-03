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
                <a href="<?php echo URLROOT;?>/banks" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light mt-2">
                    <div class="card-header">Edit Bank</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/banks/update" method="post">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bankname">Bank Name</label>
                                    <input type="text" name="bankname" id="bankname"
                                        class="form-control form-control-sm mandatory
                                        <?php echo (!empty($data['name_err'])) ? 'is-invalid' : '' ?>" 
                                        value="<?php echo (!empty($data['bankname'])) ? $data['bankname'] : strtoupper($data['bank']->accountType);?>"
                                        autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['name_err'];?></span>       
                                </div>
                                <div class="form-group">
                                    <label for="account">Account No</label>
                                    <input type="text" name="account" id="account"
                                        class="form-control form-control-sm
                                        <?php echo (!empty($data['account_err'])) ? 'is-invalid' : ''?>" 
                                        value="<?php echo (!empty($data['account'])) ? $data['account'] : strtoupper($data['bank']->accountNo);?>"
                                        autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['account_err'];?></span>    
                                </div>
                                <button class="btn btn-sm bg-navy custom-font">Save</button>
                                <input type="hidden" name="id" value="<?php echo $data['bank']->ID;?>">
                            </div>
                        </form>    
                    </div>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
</body>
</html>  