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
            <a href="<?php echo URLROOT;?>/cashreceipts" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
          <div class="col-sm-6"></div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header"><?php echo $data['isedit'] ? 'Edit ' : 'Add '; ?> Petty Cash</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/cashreceipts/createupdate" method="post" autocomplete="off">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Receipt Date</label>
                                        <input type="date" name="date" id="date" 
                                               class="form-control form-control-sm mandatory 
                                               <?php echo (!empty($data['date_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['date']; ?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['amount_err'])) ? 'is-invalid' : '';?>"
                                                value="<?php echo $data['amount'];?>"
                                                placeholder="eg 10000">
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['bank_err'])) ? 'is-invalid' : '';?>">
                                            <option value="" selected disabled>Select Bank</option>
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo $bank->ID;?>" <?php selectdCheck($data['bank'],$bank->ID);?>><?php echo $bank->BankName;?></option>
                                            <?php endforeach;?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['reference_err'])) ? 'is-invalid' : '';?>"
                                                value="<?php echo $data['reference'];?>"
                                                placeholder="enter cheque number...">
                                        <span class="invalid-feedback"><?php echo $data['reference_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                  <div class="form-group">
                                        <label for="description">Description</label>
                                        <input type="text" name="description" id="description" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['description'];?>"
                                               placeholder="eg petty cash for 12th...">
                                  </div>
                                </div>
                            </div><!-- /.row -->
                            <div class="row">
                              <div class="col-md-2 mt-2">
                                  <input type="hidden" name="id" value="<?php echo $data['id'];?>">
                                  <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>">
                                  <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
                              </div>               
                            </div><!-- /.row -->
                        </form>
                    </div><!-- /.card-body -->
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
</body>
</html>  