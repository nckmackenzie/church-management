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
                    <div class="card-header">Add Bank</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/banks/create" method="post">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="bankname">Bank Name</label>
                                    <input type="text" name="bankname" id="bankname"
                                        class="form-control form-control-sm mandatory
                                        <?php echo (!empty($data['name_err'])) ? 'is-invalid' : '' ?>" 
                                        value="<?php echo $data['bankname'];?>"
                                        autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['name_err'];?></span>       
                                </div>
                                <div class="form-group">
                                    <label for="account">Account No</label>
                                    <input type="text" name="account" id="account"
                                        class="form-control form-control-sm
                                        <?php echo (!empty($data['account_err'])) ? 'is-invalid' : ''?>" 
                                        value="<?php echo $data['account'];?>"
                                        autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['account_err'];?></span>    
                                </div>
                                <div class="form-group">
                                    <label for="openingbal">Opening Balance</label>
                                    <input type="number" name="openingbal" id="openingbal"
                                        class="form-control form-control-sm" 
                                        value="<?php echo $data['openingbal'];?>">
                                </div>
                                <div class="form-group">
                                    <label for="asof">Opening Balance</label>
                                    <input type="date" name="asof" id="asof"
                                        class="form-control form-control-sm
                                        <?php echo (!empty($data['asof_err'])) ? 'is-invalid' : ''?>" 
                                        value="<?php echo $data['asof'];?>"
                                        <?php echo (empty($data['openingbal'])) ? 'disabled' : '' ?>>
                                    <span class="invalid-feedback"><?php echo $data['asof_err'];?></span>    
                                </div>
                                <button class="btn btn-sm bg-navy custom-font">Save</button>
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
    $(function(){
        $('#openingbal').on('change',function(){
            if ($(this).val()  !== '') {
                $('#asof').attr('disabled',false);
            }
            else{
                $('#asof').attr('disabled',true);
                $('#asof').val('');
            }
        });
        $('#openingbal').focusout(function(){
            if ($(this).val()  !== '') {
                $('#asof').attr('disabled',false);
                $('#asof').addClass('mandatory');
            }
            else{
                $('#asof').attr('disabled',true);
                $('#asof').val('');
                $('#asof').removeClass('mandatory');
            }
        });
    });
</script>
</body>
</html>  