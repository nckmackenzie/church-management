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
    <div class="modal fade" id="groupcollectionSubModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="">Group/District Collection</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="collectiontype">Group Or District</label>
                                <select name="collectiontype" id="collectiontype" class="form-control form-control-sm mandatory">
                                    <option value="" selected disabled>Select group/district</option>
                                    <option value="group">Group</option>
                                    <option value="district">District</option>
                                </select>
                                <span class="invalid-feedback"></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="collectiongroupid">Group/District</label>
                                <select name="collectiongroupid" id="collectiongroupid" class="form-control form-control-sm mandatory"></select>
                                <span class="invalid-feedback"></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="collectionsubaccount">Sub Account</label>
                                <select name="collectionsubaccount" id="collectionsubaccount" class="form-control form-control-sm mandatory">
                                    <option value="" selected disabled>Select sub account</option>
                                </select>
                                <span class="invalid-feedback"></span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="collectionaccount">Account</label>
                                <input type="text" name="collectionaccount" id="collectionaccount" class="form-control form-control-sm" readonly>
                                <input type="hidden" name="collectionaccountid" id="collectionaccountid" >
                                <input type="hidden" name="collectionbankid" id="collectionbankid" >
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label for="collectionamount">Amount</label>
                                <input type="number" name="collectionamount" id="collectionamount" 
                                       class="form-control form-control-sm mandatory"
                                       placeholder="eg 2,000" required>
                                <span class="invalid-feedback"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-success btnaddcollection">Add</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 mx-auto">
                <div class="card bg-light">
                    <div class="card-header"><?php echo $data['isedit'] ? 'Edit Contribution' : 'Add Contribution';?></div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/contributions/create" method="post" name="form">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="receipt">Receipt No</label>
                                        <input type="text" class="form-control form-control-sm 
                                               <?php echo (!empty($data['receipt_err'])) ? 'is-invalid' : ''?>"
                                        name="receipt" id="receipt" value="<?php echo $data['receiptno'];?>" readonly>
                                        <span class="invalid-feedback"><?php echo $data['receipt_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" name="date" id="date"
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['date'];?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="paymethod">Payment Method</label>
                                        <select name="paymethod" id="paymethod"
                                                class="form-control form-control-sm">
                                            <?php foreach($data['paymethods'] as $paymethod) : ?>
                                                <option value="<?php echo $paymethod->ID;?>"
                                                <?php selectdCheck($data['paymethod'],$paymethod->ID)?>>
                                                    <?php echo strtoupper($paymethod->paymentMethod);?>
                                                </option>
                                            <?php endforeach;?>    
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank"
                                                class="form-control form-control-sm" 
                                                <?php echo ($data['paymethod'] < 3 || empty($data['paymethod'])) ? 'disabled' : ''?>>
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo $bank->ID;?>"
                                                <?php selectdCheck($data['bank'],$bank->ID)?>>
                                                    <?php echo strtoupper($bank->Bank);?>
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
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference"
                                               class="form-control form-control-sm
                                               <?php echo (!empty($data['ref_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['reference'];?>"
                                               placeholder="eg MPESA Reference,cheque No etc"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['ref_err'];?></span>       
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <input type="text" name="description" id="description"
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['description'];?>"
                                               placeholder="Brief Description" autocomplete="off">
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="card mt-1">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="account">Contribution Type</label>
                                                <select name="account" id="account"
                                                        class="form-control form-control-sm select2">
                                                    <option value="" selected disabled>Select G/L account...</option>
                                                    <?php foreach($data['accounts'] as $account) : ?>
                                                        <option value="<?php echo $account->ID;?>">
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
                                                    class="form-control form-control-sm"
                                                    autocomplete="off">
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
                                                        <option value="<?php echo $category->ID;?>" <?php selectdCheck($data['category'],$category->ID)?>>
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
                                                </select>
                                            </div>
                                        </div>
                                    </div><!--End Of Row -->
                                    <div class="row mb-2">
                                        <div class="col-12">
                                            <button type="button" class="btn btn-sm btn-success btnadd">Add</button>
                                            <button type="button" class="btn btn-sm btn-info btngroupcontribution">Add Group/District Collection</button>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-12">
                                            <table class="table table-sm" id="contributions-table">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th class="d-none">AccountId</th>
                                                        <th>Account</th>
                                                        <th>Amount</th>
                                                        <th class="d-none">Categoryid</th>
                                                        <th>Category</th>
                                                        <th class="d-none">ContributorId</th>
                                                        <th>Contributor</th>
                                                        <th class="d-none">Sub Account</th>
                                                        <th>Remove</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($data['table'] as $table) : ?>
                                                        <tr>
                                                            <td class="d-none"><input type="text" name="accountsid[]" value="<?php echo $table['accountid'];?>"></td>
                                                            <td><input type="text" class="table-input" name="accountsname[]" value="<?php echo $table['accountname'];?>" readonly></td>
                                                            <td><input type="text" class="table-input" name="amounts[]" value="<?php echo $table['amount'];?>" readonly></td>
                                                            <td class="d-none"><input type="text" class="table-input" name="categoriesid[]" value="<?php echo $table['categoryid'];?>"></td>
                                                            <td><input type="text" class="table-input" name="categoriesname[]" value="<?php echo $table['categoryname'];?>" readonly></td>
                                                            <td class="d-none"><input type="text" class="table-input" name="contributorsid[]" value="<?php echo $table['contributorid'];?>"></td>
                                                            <td><input type="text" class="table-input" name="contributorsname[]" value="<?php echo $table['contributorname'];?>" readonly></td>
                                                            <td class="d-none"><input type="text" class="table-input" name="subaccount[]" value="<?php echo $table['subaccount'];?>" readonly></td>
                                                            <td><button type="button" class="action-icon btn btn-sm text-danger fs-5 btndel">Remove</button></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div><!--End Of Card-body-->
                            </div><!--End of Card -->
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo $data['id'];?>">
                                    <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>">
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
                method : 'GET',
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
        $(window).on('load', function() {
            loadContributors();
            // var now = new Date();
            // var day = ("0" + now.getDate()).slice(-2);
            // var month = ("0" + (now.getMonth() + 1)).slice(-2);
            // var today = now.getFullYear()+"-"+(month)+"-"+(day) ;
            // $('#date').val(today);
            // $('#bank').val('');
        });
        $('#paymethod').change(function(){
            var paym = $(this).val();
            if (paym > 1) {
                $('#bank').attr('disabled',false);
                $('#bank').prop("selectedIndex", 0);
            }
            else{
                $('#bank').attr('disabled',true);
                $('#bank').val('');
            }
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
        loadContributors();

        $('.btngroupcontribution').on('click',function(){
            $('#groupcollectionSubModalCenter').modal('show');
        })
    });
</script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/contributions-v1.js"></script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/contributions-collection.js"></script>
</body>
</html>  