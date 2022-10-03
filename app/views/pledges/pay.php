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
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Pay Pledge</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/pledges/payment" method="post">
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group">
                                        <label for="pledger">Pledged By</label>
                                        <input type="text" name="pledger"
                                              class="form-control form-control-sm"
                                              value="<?php echo $data['pledge']->pledger;?>"
                                              readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="pledged">Amount Pledged</label>
                                        <input type="text" id="pledged" name="pledged"
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['pledge']->amountPledged;?>" 
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="totalpaid">Total Amount Paid</label>
                                        <input type="text" id="totalpaid" name="totalpaid"
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['pledge']->totalPaid;?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group">
                                        <label for="balance">Balance</label>
                                        <input type="text" id="balance" name="balance"
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['pledge']->balance;?>" 
                                               readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="date">Payment Date</label>
                                        <input type="date" name="date" id="date" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['date_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['date'];?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>       
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="paid">Current Payment</label>
                                        <input type="number" name="paid" id="paid" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['paid_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['paid'];?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['paid_err'];?></span>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="paymethod">Payment Method</label>
                                        <select name="paymethod" id="paymethod"
                                                class="form-control form-control-sm">
                                            <?php foreach($data['paymethods'] as $paymethod) :?>
                                                <option value="<?php echo $paymethod->ID;?>"
                                                <?php selectdCheck($data['paymethod'],$paymethod->ID)?>>
                                                    <?php echo strtoupper($paymethod->paymentMethod);?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank"
                                                class="form-control form-control-sm">
                                            <?php foreach($data['banks'] as $bank) :?>
                                                <option value="<?php echo $bank->ID;?>"
                                                <?php selectdCheck($data['bank'],$bank->ID)?>>
                                                    <?php echo strtoupper($bank->accountType);?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference" 
                                               class="form-control form-control-sm 
                                               <?php echo (!empty($data['ref_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['reference'];?>"
                                               placeholder="eg MPESA Reference or Cheque No"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['ref_err'];?></span>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-4">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo $data['pledge']->ID;?>">
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
        $(window).on('load',function(){
            var now = new Date();
            var day = ("0" + now.getDate()).slice(-2);
            var month = ("0" + (now.getMonth() + 1)).slice(-2);
            var today = now.getFullYear()+"-"+(month)+"-"+(day) ;
            $('#date').val(today);
            $('#bank').val('');
            $('#bank').attr('disabled',true);
        });
        $('#paymethod').change(function(){
            var paym = $(this).val();
            if (paym >= 3) {
                $('#bank').attr('disabled',false);
                $('#bank').prop("selectedIndex", 0);
                $('#reference').addClass('mandatory');
            }
            else if(paym == 2){
                $('#reference').addClass('mandatory');
                $('#bank').attr('disabled',true);
                $('#bank').val('');
            }
            else{
                $('#bank').attr('disabled',true);
                $('#bank').val('');
                $('#reference').removeClass('mandatory');
            }
        });
    });
</script>
</body>
</html>  