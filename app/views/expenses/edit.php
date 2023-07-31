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
                    <div class="card-header">Edit Expense</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/expenses/update" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" name="date" id="date"
                                            class="form-control form-control-sm mandatory
                                            <?php echo (!empty($data['date_err'])) ? 'is-invalid' : ''?>"
                                            value="<?php echo (!empty($data['date'])) ? $data['date'] : $data['expense']->expenseDate;?>">
                                        <span class="invalid-feedback"><?php echo $data['date_err'];?></span>    
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="voucher">Voucher No</label>
                                        <input type="text" id="voucher" name="voucher"
                                               class="form-control form-control-sm" id="voucher"
                                               value="<?php echo $data['expense']->voucherNo;?>"
                                               readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="">Expense type</label>
                                        <select name="expensetype" id="expensetype"
                                                class="form-control form-control-sm">
                                            <option value="1" <?php selectdCheckEdit($data['expensetype'],$data['expense']->expenseType,1)?>>Church Expense</option>
                                            <option value="2" <?php selectdCheckEdit($data['expensetype'],$data['expense']->expenseType,2)?>>Group Expense</option>    
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account">Account</label>
                                        <select name="account" id="account"
                                                class="form-control form-control-sm select2">
                                            <?php foreach($data['accounts'] as $account) :?> 
                                                <option value="<?php echo $account->ID;?>"
                                                <?php selectdCheckEdit($data['account'],$data['expense']->accountId,$account->ID)?>>
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
                                                class="form-control form-control-sm select2">
                                            <?php if($data['expense']->expenseType == 1) : ?>
                                                <option value="0">Church Expense</option>
                                            <?php else : ?>
                                                <?php foreach($data['groups'] as $group) : ?>
                                                    <option value="<?php echo $group->ID;?>"
                                                    <?php selectdCheck($data['expense']->groupId,$group->ID)?>>
                                                        <?php echo $group->groupName;?>
                                                    </option>
                                                <?php endforeach; ?>    
                                            <?php endif; ?>   
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
                                               value="<?php echo (!empty($data['amount'])) ? $data['amount'] : $data['expense']->amount;?>"
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
                                                <?php selectdCheckEdit($data['paymethod'],$data['expense']->paymethodId,$paymethod->ID)?>>
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
                                             <option value="cash at hand" <?php selectdCheckEdit($data['deductfrom'],$data['expense']->deductfrom,'cash at hand');?>>Cash At Hand</option>   
                                             <option value="petty cash" <?php selectdCheckEdit($data['deductfrom'],$data['expense']->deductfrom,'petty cash');?>>Petty Cash</option>   
                                        </select>        
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" 
                                                class="form-control form-control-sm
                                                <?php echo (!empty($data['bank_err'])) ? 'is-invalid' : ''?>"
                                                <?php echo (empty($data['paymethod']) || $data['expense']->paymethodId < 3) ? 'disabled' : ''?>>
                                            
                                                <?php foreach($data['banks'] as $bank) :?> 
                                                    <option value="<?php echo $bank->ID;?>"
                                                    <?php selectdCheckEdit($data['bank'],$data['expense']->bankId,$bank->ID)?>>
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
                                               value="<?php echo (!empty($data['reference'])) ? $data['reference'] : strtoupper($data['expense']->paymentReference);?>"
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
                                               value="<?php echo (!empty($data['description'])) ? $data['description'] : strtoupper($data['expense']->narration);?>"
                                               placeholder="Brief Description"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['desc_err'];?></span>       
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-4">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo $data['expense']->ID;?>">
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

        $('#expensetype').change(function(){
            getCostCentre();
        });

        $(window).on('load',function(){
            var test = $('#paymethod').val();
            if (test < 3) {
                $('#bank').val('');
                $('#bank').attr('disabled',true);
            }
            else{
                $('#bank').attr('disabled',false);
            }
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
    const paymethodSelect = document.getElementById('paymethod');
    const cashtypeSelect = document.getElementById('cashtype');

    paymethodSelect.addEventListener('change', function(e){
        if(Number(e.target.value) === 1){
            cashtypeSelect.value = 'petty cash';
            cashtypeSelect.disabled = false;
            cashtypeSelect.classList.add('mandatory');
        }else{
            cashtypeSelect.value = '';
            cashtypeSelect.disabled = true;
            cashtypeSelect.classList.remove('mandatory');
        }
    });
</script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/expenses/add-expense-v2.js"></script>
</body>
</html>  