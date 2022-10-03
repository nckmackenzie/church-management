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
             <a href="<?php echo URLROOT;?>/supplierinvoices" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Invoice Payment</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/supplierinvoices/payment" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="">Supplier</label>
                                        <input type="text" class="form-control form-control-sm" 
                                               value="<?php echo $data['invoice']->supplierName;?>"
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="">Invoice #</label>
                                        <input type="text" class="form-control form-control-sm"
                                               value="<?php echo $data['invoice']->invoiceNo;?>"
                                               name="invoiceno"
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Invoice Amount</label>
                                        <input type="text" class="form-control form-control-sm" 
                                               value="<?php echo number_format($data['invoice']->inclusiveVat,2);?>"
                                               readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paydate">Payment Date</label>
                                        <input type="date" name="paydate" id="paydate" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['date_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['paydate'];?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="">Balance</label>
                                        <input type="text"
                                                class="form-control form-control-sm" 
                                                value="<?php echo number_format($data['invoice']->balance,2);?>"
                                               readonly>
                                        <input type="hidden" name="balance" 
                                               value="<?php echo $data['invoice']->balance;?>">
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['amount_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['amount'];?>"
                                               placeholder="Payment Amount"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paymethod">Payment Method</label>
                                        <select name="paymethod" id="paymethod"
                                                class="form-control form-control-sm">
                                            <?php foreach($data['paymethods'] as $paymethod) : ?>
                                                <option value="<?php echo $paymethod->ID;?>"
                                                <?php selectdCheck($data['paymethod'],$paymethod->ID)?>>
                                                    <?php echo strtoupper($paymethod->paymentMethod);?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank"
                                                class="form-control form-control-sm select2">
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo $bank->ID;?>"
                                                <?php selectdCheck($data['bank'],$bank->ID)?>>
                                                    <?php echo strtoupper($bank->accountType);?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>        
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['ref_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['reference'];?>"
                                               placeholder="Payment Reference eg Cheque No"
                                               autocomplete="off"> 
                                        <span class="invalid-feedback"><?php echo $data['ref_err'];?></span>            
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo $data['invoice']->ID;?>">
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
        $('.select2').select2();
        $('#paymethod').change(function(){
            var paym = $(this).val();
            if (paym > 2) {
                $('#bank').attr('disabled',false);
                $('#bank').prop("selectedIndex", 0);
            }
            else{
                $('#bank').val('');
                $('#bank').attr('disabled',true);
            }
        });
        $(window).on('load',function(){
            
            var now = new Date();
            var day = ("0" + now.getDate()).slice(-2);
            var month = ("0" + (now.getMonth() + 1)).slice(-2);
            var today = now.getFullYear()+"-"+(month)+"-"+(day) ;
            $('#paydate').val(today);
            getCostCentre();
        });
    });
</script>
</body>
</html>  