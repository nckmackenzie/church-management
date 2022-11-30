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
            <a href="<?php echo URLROOT;?>/pledges" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12" id="alertBox"></div>
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Add Pledge</div>
                    <div class="card-body">
                        <form id="pledge-form" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <select name="category" id="category" 
                                                class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select category</option>
                                            <option value="1"<?php selectdCheck($data['category'],1)?>>Member</option>
                                            <option value="2"<?php selectdCheck($data['category'],2)?>>Group</option>
                                            <option value="3"<?php selectdCheck($data['category'],3)?>>District</option>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['category_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="pledger">Pledged By</label>
                                        <select name="pledger" id="pledger"
                                                class="select2 form-control form-control-sm mandatory"></select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" name="date" id="date" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['date_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['date'];?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>       
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amountpledged">Amount Pledged</label>
                                        <input type="number" name="amountpledged" id="amountpledged"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['pledged_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['amountpledged'];?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['pledged_err'];?></span>       
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amountpaid">Amount Paid</label>
                                        <input type="number" name="amountpaid" id="amountpaid"
                                               class="form-control form-control-sm
                                               <?php echo (!empty($data['paid_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['amountpaid'];?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['paid_err'];?></span>       
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paymethod">Payment Method</label>
                                        <select name="paymethod" id="paymethod" 
                                                class="form-control form-control-sm">
                                            <option value="" selected disabled>Select pay method</option>
                                            <?php foreach($data['paymethods'] as $paymethod) : ?>
                                                <option value="<?php echo $paymethod->ID;?>"
                                                <?php selectdCheck($data['paymethod'],$paymethod->ID)?>>
                                                    <?php echo strtoupper($paymethod->paymentMethod);?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" class="form-control form-control-sm
                                        <?php echo (!empty($data['bank_err'])) ? 'is-invalid' : ''?>">
                                            <option value="" selected disabled>Select bank</option>
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo $bank->ID;?>"
                                                    <?php selectdCheck($data['bank'],$bank->ID)?>>
                                                    <?php echo strtoupper($bank->accountType);?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference"
                                        class="form-control form-control-sm 
                                        <?php echo (!empty($data['ref_err'])) ? 'is-invalid' : ''?>"
                                        value="<?php echo $data['reference'];?>"
                                        placeholder="eg MPESA Reference or Chq No"
                                        autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['ref_err'];?></span>
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-4">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font save">Save</button>
                                    <input type="hidden" name="pledgername" id="pledgername">
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/pledges/add.js"></script>
<script>
    $(function(){
        $('.select2').select2();
    });
</script>
</body>
</html>  