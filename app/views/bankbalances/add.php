<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
                <a href="<?php echo URLROOT;?>/bankbalances" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-header">Add Bank Balance</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/bankbalances/create" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" name="date" id="date" 
                                               class="form-control form-control-sm 
                                               <?php echo (!empty($data['date_err'])) ? 'is-invalid' : '' ?>"
                                               value="<?php echo $data['date'];?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" 
                                                class="form-control form-control-sm 
                                                <?php echo (!empty($data['bank_err'])) ? 'is-invalid' : '';?>">
                                            <option value="" selected disabled>Select Bank</option>
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo $bank->ID;?>" <?php selectdCheck($data['bank'],$bank->ID)?>><?php echo $bank->Bank;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount" 
                                               class="form-control form-control-sm 
                                                      <?php echo (!empty($data['amount_err'])) ? 'is-invalid' : '' ?>"
                                               value="<?php echo $data['amount'];?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-sm custom-font bg-navy">Save</button>
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
</body>
</html>  