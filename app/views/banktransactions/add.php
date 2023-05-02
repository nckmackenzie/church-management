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
          <a href="<?php echo URLROOT;?>/banktransactions" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto" id="alertBox"></div>
        </div>
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header"><?php echo $data['title'];?></div>
                    <div class="card-body">    
                        <form action="<?php echo URLROOT;?>/banktransactions/createupdate" method="post" id="txnform">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Transaction Date</label>
                                        <input type="date" name="date" id="date"
                                            class="form-control form-control-sm mandatory"
                                            value="<?php echo $data['date'];?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>   
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select bank</option>
                                            <?php foreach($data['banks'] as $bank)  : ?>
                                                <option value="<?php echo $bank->ID;?>" <?php selectdCheck($data['bank'],$bank->ID);?>><?php echo $bank->Bank;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="type">Transaction Type</label>
                                        <select name="type" id="type" class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select transaction type</option>
                                            <option value="1">Deposit</option>
                                            <option value="2">Withdrawal</option>
                                            <option value="5">Transfers</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transferto">Transfer To</label>
                                        <select name="transferto" id="transferto" class="form-control form-control-sm" disabled>
                                            <option value="" selected disabled>Select transfer account</option>
                                            <?php foreach($data['accounts'] as $account)  : ?>
                                                <option value="<?php echo $account->ID;?>" <?php selectdCheck($data['bank'],$account->ID);?>><?php echo $account->accountType;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount"
                                            class="form-control form-control-sm mandatory"
                                            value="<?php echo $data['amount'];?>" placeholder="eg 45,000" autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>   
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference No</label>
                                        <input type="text" name="reference" id="reference"
                                        class="form-control form-control-sm mandatory"
                                        value="<?php echo $data['reference'];?>" 
                                        placeholder="enter reference no"
                                        autocomplete="off">   
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <input type="text" name="description" id="description"
                                        class="form-control form-control-sm"
                                        value="<?php echo $data['description'];?>" 
                                        placeholder="description of deposit eg source of cash etc.."
                                        autocomplete="off">   
                                    </div>
                                </div>
                            </div>    
                            <div class="row">
                                <div class="col-md-2 mt-2">
                                    <input type="hidden" name="touched" value="<?php echo $data['touched'];?>">
                                    <input type="hidden" name="id" value="<?php echo $data['id'];?>">
                                    <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>">
                                    <button type="submit" class="btn btn-block btn-sm bg-navy custom-font save">Save</button>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/banktransactions/index.js"></script>
</body>
</html>
  

