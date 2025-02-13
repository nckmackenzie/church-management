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
             <a href="<?php echo URLROOT;?>/accounts" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Edit Account</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/accounts/update" method="post">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="accountname">Account Name</label>
                                        <input type="text" name="accountname" id="accountname" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['accountname'];?>"
                                               autocomplete="off"
                                               placeholder="eg Offering,Tithe,Water Bill etc">
                                        <span class="invalid-feedback"><?php echo $data['name_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="accounttype">Account Type</label>
                                        <select name="accounttype" id="accounttype"
                                                class="form-control form-control-sm">
                                            <?php foreach($data['accounttypes'] as $accounttype) : ?>
                                                <option value="<?php echo $accounttype->ID;?>"
                                                <?php selectdCheck($data['accounttype'],$accounttype->ID)?>>
                                                    <?php echo $accounttype->accountType;?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="checkbox">    
                                            <label class="custom-sm">
                                                <input type="checkbox" id="check" name="check"
                                                <?php echo ($data['check'] == 1 || converttobool($data['issub'])) ? 'checked' : ''?>> Sub Category Of
                                            </label>       
                                        </div>  
                                        <select name="subcategory" id="subcategory" 
                                                 class="form-control form-control-sm
                                                 <?php echo (!empty($data['account_err'])) ? 'is-invalid' : ''?>" 
                                                 <?php echo (!converttobool($data['issub'])) ? 'disabled' : ''?>>
                                            <?php if(!empty($data['accounts'])) : ?>
                                                <?php foreach($data['accounts'] as $account) : ?>
                                                    <option value="<?php echo $account->ID;?>"
                                                    <?php selectdCheck($data['subcategory'],$account->ID)?>>
                                                        <?php echo $account->accountType;?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif;?>    
                                         </select>   
                                         <span class="invalid-feedback"><?php echo $data['account_err'];?></span>   
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                         <label for="description">Description</label>       
                                         <input type="text" name="description" id="description"
                                                class="form-control form-control-sm"
                                                value="<?php echo $data['description'];?>">
                                    </div>
                                </div>
                            </div>
                            <?php if(converttobool($_SESSION['isParish'])) : ?>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="congregation">Congregation</label>
                                            <select name="congregation" id="congregation"
                                                    class="form-control form-control-sm">
                                                <option value="0">All</option>
                                                <option value="<?php echo $_SESSION['congId'];?>"
                                                    <?php selectdCheck($data['congregation'],$_SESSION['congId'])?>>
                                                        <?php echo $data['congregationName'];?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php endif;?>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="checkbox">    
                                            <label class="custom-sm">
                                                <input type="checkbox" id="forgroup" name="forgroup"
                                                <?php echo ($data['forgroup'] == 1) ? 'checked' : ''?>>For Group
                                            </label>       
                                        </div>        
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="checkbox">    
                                            <label class="custom-sm">
                                                <input type="checkbox" id="active" name="active"
                                                <?php echo ($data['active'] == 1) ? 'checked' : ''?>>Active
                                            </label>       
                                        </div>        
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo $data['id'];?>">
                                    <input type="hidden" name="initialname" value="<?php echo strtolower($data['accountname']);?>">
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
        function loadSubCategories(main){
            $.ajax({
                url  : '<?php echo URLROOT;?>/accounts/getsubcategory',
                method : 'POST',
                data : {main : main},
                success : function(html){
                    $('#subcategory').html(html);
                }
            });
        }
        // $(window).on('load',function(){
        //     var main = $('#accounttype').val();
        //     $('#subcategory').val('');
        // });
        $('#accounttype').change(function(){
            var main = $(this).val();
            if($('#check').prop('checked') == true) {
                loadSubCategories($(this).val());
            }
            else{
                $('#subcategory').prop('disabled',true);
                $('#subcategory').val('');
            }
            
        });
        $('#check').click(function(){
            if ($(this).prop('checked') == true) {
                $('#subcategory').prop('disabled',false);
                $('#forgroup').prop('disabled',true);
                var parentId = $('#accounttype').val();
                loadSubCategories(parentId);
            }
            else{
                $('#subcategory').prop('disabled',true);
                $('#forgroup').prop('disabled',false);
                $('#subcategory').val('');
            }
        });
    });
</script>
</body>
</html>  