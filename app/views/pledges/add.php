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
                    <div class="card-header">Add Pledge</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/pledges/create" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <select name="category" id="category" class="form-control form-control-sm">
                                            <option value="1"<?php selectdCheck($data['category'],1)?>>Member</option>
                                            <option value="2"<?php selectdCheck($data['category'],2)?>>Group</option>
                                            <option value="3"<?php selectdCheck($data['category'],3)?>>District</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="pledger">Pledged By</label>
                                        <select name="pledger" id="pledger"
                                                class="select2 form-control form-control-sm">
                                            <?php foreach($data['pledgers'] as $pledger) : ?>
                                                <option value="<?php echo $pledger->ID;?>"
                                                <?php selectdCheck($data['pledger'],$pledger->ID)?>>
                                                    <?php echo $pledger->pledger;?>
                                                </option>
                                            <?php endforeach;?>    
                                        </select>
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
                                                class="form-control form-control-sm mandatory">
                                            <?php foreach($data['paymethods'] as $paymethod) : ?>
                                                <option value="<?php echo $paymethod->ID;?>"
                                                <?php selectdCheck($data['paymethod'],$paymethod->ID)?>>
                                                    <?php echo strtoupper($paymethod->paymentMethod);?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row-->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" class="form-control form-control-sm
                                        <?php echo (!empty($data['bank_err'])) ? 'is-invalid' : ''?>">
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
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
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
<script>
    $(function(){
        $('.select2').select2();

        $(window).on('load',function(){
            var now = new Date();
            var day = ("0" + now.getDate()).slice(-2);
            var month = ("0" + (now.getMonth() + 1)).slice(-2);
            var today = now.getFullYear()+"-"+(month)+"-"+(day) ;
            $('#date').val(today);
            $('#bank').val('');
            $('#bank').attr('disabled',true);
            if ($('#amountpaid').val() > 0) {
                $('#paymethod').attr('disabled',false);
                $('#reference').attr('disabled',false);
            }
            else{
                $('#paymethod').attr('disabled',true);
                $('#paymethod').val('');
                $('#reference').attr('disabled',true);
                $('#reference').val('');
            }
            getPledgerName();
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
        $('#category').change(function(){
            var category = $(this).val();
            $.ajax({
                url : '<?php echo URLROOT;?>/pledges/getpledger',
                method : 'POST',
                data : {category : category},
                success : function(data){
                    $('#pledger').html(data);
                }
            });
        });
        $('#amountpaid').focusout(function(){
            var amount = $(this).val();
            if (amount > 0) {
                $('#paymethod').attr('disabled',false);
                $('#paymethod').prop("selectedIndex", 0);
                $('#reference').attr('disabled',false);
                $('#reference').val('');
            }
            else{
                $('#bank').val('');
                $('#bank').attr('disabled',true);
                $('#paymethod').attr('disabled',true);
                $('#paymethod').val('');
                $('#reference').attr('disabled',true);
                $('#reference').val('');
            }
            getPledgerName();
        });
        $('#pledger').change(function(){
            getPledgerName();
        });
        function getPledgerName(){
            var data=$('#pledger').select2('data');
            var selectedText = (data[0].text).trim();
            $('#pledgername').val(selectedText);
        }
    });
</script>
</body>
</html>  