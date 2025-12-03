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
                <a href="<?php echo URLROOT;?>/banks/subaccountbalances" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light mt-2">
                    <div class="card-header">Edit Opening Balance Bank</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/banks/updatebalance" method="post">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="subaccount">Sub Account</label>
                                        <select disabled name="subaccount" id="subaccount" class="form-control form-control-sm mandatory <?php echo (!empty($data['subaccount_err'])) ? 'is-invalid' : '' ?>">
                                            <option value="" selected disabled>Select sub account</option>
                                            <?php foreach($data['subaccounts'] as $subaccount) : ?>
                                                <option value="<?php echo $subaccount->ID;?>" <?php selectdCheck($data['subaccount'],$subaccount->ID); ?>><?php echo ucwords($subaccount->AccountName);?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['asof_err'];?></span> 
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="asof">As Of</label>
                                        <input type="date" name="asof" id="asof"
                                               class="form-control form-control-sm mandatory
                                                <?php echo (!empty($data['asof_err'])) ? 'is-invalid' : '' ?>" 
                                                value="<?php echo (!empty($data['asof'])) ? $data['asof'] : '';?>"
                                                autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['asof_err'];?></span>  
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="balance">Balance</label>
                                        <input type="text" name="balance" id="balance"
                                               class="form-control form-control-sm mandatory
                                                <?php echo (!empty($data['balance_err'])) ? 'is-invalid' : '' ?>" 
                                                value="<?php echo (!empty($data['balance'])) ? $data['balance'] : '';?>"
                                                autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['balance_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-2 mt-2">
                                    <input type="hidden" name="id" value="<?php echo $data['id'];?>">
                                    <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>">
                                    <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
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