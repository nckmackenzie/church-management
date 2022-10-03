<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <div class="row mb-1 ml-1">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/contributions" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
    </div>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Edit Contribution</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/contributions/update" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="receipt">Receipt No</label>
                                        <input type="text" class="form-control form-control-sm"
                                        name="receipt" id="receipt" 
                                        value="<?php echo $data['contribution']->receiptNo;?>"
                                        readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" name="date" id="date"
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo (!empty($data['date'])) ? $data['date']
                                               : $data['contribution']->contributionDate;?>">
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account">Contribution Type</label>
                                        <select name="account" id="account"
                                                class="form-control form-control-sm select2">
                                            <?php foreach($data['accounts'] as $account) : ?>
                                                <option value="<?php echo $account->ID;?>"
                                                <?php selectdCheck($data['contribution']->contributionTypeId,$account->ID)?>>
                                                    <?php echo $account->accountType;?>
                                                </option>
                                            <?php endforeach;?>    
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['amount_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo (!empty($data['amount'])) ? $data['amount']
                                               : $data['contribution']->amount;?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>       
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paymethod">Payment Method</label>
                                        <select name="paymethod" id="paymethod"
                                                class="form-control form-control-sm">
                                            <?php foreach($data['paymethods'] as $paymethod) : ?>
                                                <option value="<?php echo $paymethod->ID;?>"
                                                <?php selectdCheck($data['contribution']->paymentMethodId,$paymethod->ID)?>>
                                                    <?php echo strtoupper($paymethod->paymentMethod);?>
                                                </option>
                                            <?php endforeach;?>    
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank"
                                                class="form-control form-control-sm" 
                                                <?php echo ($data['contribution']->paymentMethodId < 3 || empty($data['paymethod'])) ? 'disabled' : ''?>>
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo $bank->ID;?>"
                                                <?php selectdCheck($data['contribution']->bankId,$bank->ID)?>>
                                                    <?php echo strtoupper($bank->accountType);?>
                                                </option>
                                            <?php endforeach;?>     
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category">Category</label>
                                        <select name="category" id="category"
                                                class="form-control form-control-sm">
                                            <?php foreach($data['categories'] as $category) : ?>
                                                <option value="<?php echo $category->ID;?>"
                                                <?php selectdCheck($data['contribution']->category,$category->ID)?>>
                                                    <?php echo strtoupper($category->category);?>
                                                </option>
                                            <?php endforeach;?>    
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contributor">Contributor</label>
                                        <select name="contributor" id="contributor"
                                                class="form-control form-control-sm select2">
                                            <?php foreach($data['contributor'] as $contributor) : ?>
                                                <option value="<?php echo $contributor->ID;?>"
                                                <?php selectdCheck($data['contribution']->contributor,$contributor->ID)?>>
                                                    <?php echo $contributor->contributor;?>
                                                </option>
                                            <?php endforeach;?>    
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference"
                                               class="form-control form-control-sm
                                               <?php echo (!empty($data['ref_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo strtoupper($data['contribution']->paymentReference);?>"
                                               placeholder="eg MPESA Reference,cheque No etc"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['ref_err'];?></span>       
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <input type="text" name="description" id="description"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['desc_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo strtoupper($data['contribution']->narration);?>"
                                               placeholder="Brief Description" autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['desc_err'];?></span>       
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" id="id" value="<?php echo $data['contribution']->ID;?>">
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
    $(function (){
        $('.select2').select2();
        function loadContributors(){
            var category = $('#category').val();
           
            $.ajax({
                url : '<?php echo URLROOT;?>/contributions/getcontributor',
                method : 'POST',
                data : {category : category},
                success : function(html){
                    // console.log(html);
                    $('#contributor').html(html);
                }
            });
        }
        $('#category').change(function(){
            loadContributors();
        });
       
        $('#paymethod').change(function(){
            var paym = $(this).val();
            if (paym > 2) {
                $('#bank').attr('disabled',false);
                $('#bank').prop("selectedIndex", 0);
                var bank = $('#bank').find('option:selected').text().trim();
                // console.log(bank);
                $('#bankname').val(bank);
            }
            else{
                $('#bank').attr('disabled',true);
                $('#bank').val('');
            }
        });
        $('#bank').change(function(){
            var bank = $('#bank').find('option:selected').text().trim();
            $('#bankname').val(bank);
        });
       
        function checkForGroups(){
            var forgroup ='';
            var cont =$('#account').val();
            $.ajax({
                url : '<?php echo URLROOT;?>/contributions/checkforgroup',
                method : 'POST',
                data : {cont : cont},
                dataType : 'json',
                success : function(html){
                    // console.log(html);
                    $('#forgroup').val(html.forGroup);
                    $('#accountid').val(html.accountTypeId);
                    forgroup = html.forGroup;
                    if (Number(html.forGroup) === 1) {
                        $('#category').val(2);
                        $('#category').attr('disabled',true);
                    }
                    else{
                        $('#category').attr('disabled',false);
                    }
                    loadContributors();
                }
            });
        }
        function getAccountName(){
            // var account = $('#account').find('option:selected').text();
            var data=$('#account').select2('data');
            var selectedText = (data[0].text).trim();
            $('#accountname').val(selectedText);
        }
        $('#account').change(function(){
            checkForGroups();
            getAccountName();
        });
    });
</script>
</body>
</html>  