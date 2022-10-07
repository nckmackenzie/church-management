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
          <a href="<?php echo URLROOT;?>/deposits" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card  bg-light">
                    <div class="card-header"><?php echo $data['title'];?></div>
                    <div class="card-body">    
                        <form action="<?php echo URLROOT;?>/deposits/createupdate" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Deposit Date</label>
                                        <input type="date" name="date" id="date"
                                            class="form-control form-control-sm mandatory 
                                            <?php echo inputvalidation($data['date'],$data['date_err'],$data['touched']);?>"
                                            value="<?php echo $data['date'];?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>   
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" class="form-control form-control-sm mandatory
                                            <?php echo inputvalidation($data['bank'],$data['bank_err'],$data['touched']);?>">
                                            <option value="" selected disabled>Select bank</option>
                                            <?php foreach($data['banks'] as $bank)  : ?>
                                                <option value="<?php echo $bank->ID;?>" <?php selectdCheck($data['bank'],$bank->ID);?>><?php echo $bank->Bank;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Deposited Amount</label>
                                        <input type="number" name="amount" id="amount"
                                            class="form-control form-control-sm mandatory 
                                            <?php echo inputvalidation($data['amount'],$data['amount_err'],$data['touched']);?>"
                                            value="<?php echo $data['amount'];?>" placeholder="eg 45,000">
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>   
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Deposit slip reference #</label>
                                        <input type="text" name="reference" id="reference"
                                        class="form-control form-control-sm"
                                        value="<?php echo $data['reference'];?>" 
                                        placeholder="enter reference no if available"
                                        autocomplete="off">   
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
</body>
</html>
  

