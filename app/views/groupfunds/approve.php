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
            <a href="<?php echo URLROOT;?>/groupfunds/approvals" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
          <div class="col-sm-6"></div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <?php if(!empty($data['errmsg'])) : ?>
                    <?php echo alert($data['errmsg']); ?>
                <?php endif;?>
                <div class="card card-light">
                    <div class="card-header"><?php echo $data['title'];?></div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/groupfunds/approvefunds" autocomplete="off" method="post">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="reqno">Request No</label>
                                        <input type="text" name="reqno" id="reqno" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['reqno'];?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date">Request Date</label>
                                        <input type="text" name="date" id="date" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['reqdate'];?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="group">Group</label>
                                        <input type="text" name="group" id="group" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['group'];?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="reason">Purpose</label>
                                        <input type="text" name="reason" id="reason" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['reason'];?>"
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="availableamount">Amount Available</label>
                                        <input type="text" name="availableamount" id="availableamount" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['availableamount'];?>" readonly>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="amount">Amount Requested</label>
                                        <input type="text" name="amount" id="amount" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['amount'];?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="approved">Amount Approved</label>
                                        <input type="number" name="approved" id="approved" 
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['approved'];?>"
                                               placeholder="eg 2,000">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="balance">Balance</label>
                                        <input type="text" name="balance" id="balance" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['balance'];?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="paydate">Payment Date</label>
                                        <input type="date" name="paydate" id="paydate" 
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['paydate'];?>">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="paymethod">Pay Method</label>
                                        <select name="paymethod" id="paymethod"
                                                class="form-control form-control-sm mandatory">
                                            <?php foreach($data['paymethods'] as $paymethod) :?> 
                                                <option value="<?php echo $paymethod->ID;?>"
                                                <?php selectdCheck($data['paymethod'],$paymethod->ID)?>>
                                                    <?php echo strtoupper($paymethod->paymentMethod);?>
                                                </option>
                                            <?php endforeach; ?>     
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank"
                                                class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select bank...</option>
                                            <?php foreach($data['banks'] as $bank) :?> 
                                                <option value="<?php echo $bank->ID;?>"
                                                <?php selectdCheck($data['bank'],$bank->ID)?>>
                                                    <?php echo strtoupper($bank->Bank);?>
                                                </option>
                                            <?php endforeach; ?>     
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="reference">Payment Reference</label>
                                        <input type="text" name="reference" id="reference" 
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['reference'];?>"
                                               placeholder="chq00909">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-2 mt-2">
                                    <input type="hidden" name="id" value="<?php echo $data['id'];?>">
                                    <input type="hidden" name="groupid" value="<?php echo $data['groupid'];?>">
                                    <input type="hidden" name="dontdeduct" id="dontdeduct" value="<?php echo $data['dontdeduct'];?>">
                                    <button type="submit" class="btn btn-block btn-sm bg-navy custom-font"> Approve </button>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/groupfunds/approve-v1.js"></script>
</body>
</html>  