<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="alertModal" tabindex="-1" role="dialog" aria-labelledby="over-usage" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Account Info</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
            <div class="col-md-9">
                <label for="">Selected account has already used more than budgeted for</label>
            </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
     </div>
  </div>
</div>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/expenses" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Add Expense</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/expenses/create" id="expenseform" name="expenseform" method="post" enctype="multipart/form-data">
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
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="voucher">Voucher No</label>
                                        <input type="text" id="voucher" name="voucher"
                                               class="form-control form-control-sm" id="voucher"
                                               value="<?php echo $data['voucherno'];?>"
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Expense type</label>
                                        <select name="expensetype" id="expensetype"
                                                class="form-control form-control-sm">
                                            <option value="1" <?php selectdCheck($data['expensetype'],1)?>>Church Expense</option>
                                            <option value="2" <?php selectdCheck($data['expensetype'],2)?>>Group Expense</option>    
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account">Account</label>
                                        <select name="account" id="account"
                                                class="form-control form-control-sm">
                                            <option value="" selected disabled>Select account</option>
                                            <?php foreach($data['accounts'] as $account) :?> 
                                                <option value="<?php echo $account->ID;?>"
                                                <?php selectdCheck($data['account'],$account->ID)?>>
                                                    <?php echo $account->accountType;?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="costcentre">Cost Centre</label>
                                        <select name="costcentre" id="costcentre"
                                                class="form-control form-control-sm">
                                                <option value="">Select cost center</option>
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['amount_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['amount'];?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>       
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="paymethod">Pay Method</label>
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
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cashtype">Deduction from</label>
                                        <select name="cashtype" id="cashtype" class="form-control form-control-sm mandatory">
                                             <option value="cash at hand" <?php selectdCheck('cash at hand',$data['deductfrom']);?>>Cash At Hand</option>   
                                             <option value="petty cash" <?php selectdCheck('petty cash',$data['deductfrom']);?>>Petty Cash</option>   
                                        </select>        
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" 
                                                class="form-control form-control-sm
                                                <?php echo (!empty($data['bank_err'])) ? 'is-invalid' : ''?>"
                                                <?php echo (empty($data['paymethod']) || $data['paymethod'] < 3) ? 'disabled' : ''?>>
                                            <?php foreach($data['banks'] as $bank) :?> 
                                                <option value="<?php echo $bank->ID;?>"
                                                <?php selectdCheck($data['bank'],$bank->ID)?>>
                                                    <?php echo strtoupper($bank->Bank);?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" id="reference" name="reference"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['ref_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['reference'];?>"
                                               placeholder="eg MPESA Reference Or Cheque No"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['ref_err'];?></span>       
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <input type="text" id="description" name="description"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['desc_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['description'];?>"
                                               placeholder="Brief Description"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['desc_err'];?></span>       
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="attachment">Attach Receipt</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="attachment" name="file">
                                        <label class="custom-file-label" for="customFile">Choose file (Less than 1MB)</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4">
                                    <button id="save-btn" type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                </div>
                            </div>
                        </form>
                    </div><!--End Card-->
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
                $('#bank').attr('disabled',true);
                $('#bank').val('');
            }
        });

        $(window).on('load',function(){
            $('#bank').val('');
            // var now = new Date();
            // var day = ("0" + now.getDate()).slice(-2);
            // var month = ("0" + (now.getMonth() + 1)).slice(-2);
            // var today = now.getFullYear()+"-"+(month)+"-"+(day) ;
            // $('#date').val(today);
            getCostCentre();
        });

        $('#expensetype').change(function(){
            getCostCentre();
        });

        function getCostCentre(){
            var category = $('#expensetype').val();
            $.ajax({
                url : '<?php echo URLROOT;?>/expenses/getcostcentre',
                method : 'POST',
                data : {category : category},
                success : function(data){
                    // console.log(data);
                    $('#costcentre').html(data);
                }
            });
        }
    });
</script>
<script>
    const form = document.getElementById('expenseform');
    const saveBtn = document.getElementById('save-btn');

    form.addEventListener('submit', function(e){
        e.preventDefault();

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...'

        document.expenseform.submit();
    });

</script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/expenses/add-expense-v3.js"></script>
</body>
</html>  