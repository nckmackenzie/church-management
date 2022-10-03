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
            <a href="<?php echo URLROOT;?>/bankpostings" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Add Bank Posting</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/bankpostings/create" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" name="date" id="date" 
                                               class="form-control form-control-sm mandatory 
                                               <?php echo (!empty($data['date_err'])) ? 'is-invalid' : '' ;?>"
                                               value="<?php echo $data['date'];?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['bank_err'])) ? 'is-invalid' : '' ;?>">
                                            <option value="" selected disabled>Select Bank</option>
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo $bank->ID;?>" <?php selectdCheck($bank->ID,$data['bank']) ?>><?php echo $bank->Bank;?></option>
                                            <?php endforeach;?>    
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="type">Transaction Type</label>
                                        <select name="type" id="type" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['type_err'])) ? 'is-invalid' : '' ;?>">
                                            <option value="" selected disabled>Select Transaction Type</option>
                                            <?php foreach($data['methods'] as $method) : ?>
                                                <option value="<?php echo $method->ID;?>" <?php selectdCheck($method->ID,$data['type']) ?>><?php echo $method->methodName;?></option>
                                            <?php endforeach;?>    
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['type_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount" 
                                               class="form-control form-control-sm mandatory 
                                               <?php echo (!empty($data['amount_err'])) ? 'is-invalid' : '' ;?>"
                                               value="<?php echo $data['amount'];?>">
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account">G/L Account</label>
                                        <select name="account" id="account" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['account_err'])) ? 'is-invalid' : '' ;?>">
                                            <option value="" selected disabled>Select Account</option>
                                            <?php foreach($data['accounts'] as $account) : ?>
                                                <option value="<?php echo $account->ID;?>" <?php selectdCheck($account->ID,$data['account']) ?>><?php echo $account->accountType;?></option>
                                            <?php endforeach;?>    
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['account_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference" 
                                               class="form-control form-control-sm mandatory 
                                               <?php echo (!empty($data['reference_err'])) ? 'is-invalid' : '' ;?>"
                                               value="<?php echo $data['reference'];?>"
                                               placeholder="eg Cheque No or Transaction Reference"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['reference_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="narration">Description</label>
                                        <input type="text" name="narration" id="narration" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['narration'];?>"
                                               placeholder="eg Description on bank entry"
                                               autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
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