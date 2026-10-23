<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<?php $h = function ($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }; ?>
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
                    <div class="card-header">Add Account</div>
                    <div class="card-body">
                        <?php if(!empty($data['errors']['general'])) : ?>
                            <div class="alert alert-danger"><?php echo $h($data['errors']['general']);?></div>
                        <?php endif;?>
                        <form action="<?php echo URLROOT;?>/accounts/create" method="post">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="accountname">Account Name</label>
                                        <input type="text" name="accountname" id="accountname"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['errors']['name'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $h($data['accountname']);?>"
                                               autocomplete="off" maxlength="100"
                                               placeholder="eg Offering,Tithe,Water Bill etc">
                                        <span class="invalid-feedback"><?php echo $h($data['errors']['name'] ?? '');?></span>
                                    </div>
                                </div>
                            </div>
                            <?php if($data['mayshare']) : ?>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="congregation">Congregation</label>
                                            <select name="congregation" id="congregation"
                                                    class="form-control form-control-sm">
                                                <option value="0" <?php selectdCheck((string)$data['congregation'],'0')?>>All (shared)</option>
                                                <option value="<?php echo (int)$_SESSION['congId'];?>"
                                                    <?php selectdCheck((int)$data['congregation'],(int)$_SESSION['congId'])?>>
                                                        <?php echo $h($data['congregationName']);?>
                                                </option>
                                            </select>
                                            <small class="text-muted">Cannot be changed after the account is created.</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif;?>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="root">Account Type (narrows the list below)</label>
                                        <select name="root" id="root" class="form-control form-control-sm">
                                            <option value="">All</option>
                                            <?php foreach($data['accounttypes'] as $accounttype) : ?>
                                                <option value="<?php echo (int)$accounttype->ID;?>"
                                                <?php selectdCheck($data['root'],$accounttype->ID)?>>
                                                    <?php echo $h($accounttype->accountType);?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="parent">Sub Category Of</label>
                                        <select name="parent" id="parent"
                                                 class="form-control form-control-sm
                                                 <?php echo (!empty($data['errors']['parent'])) ? 'is-invalid' : ''?>">
                                            <option value="">Select the parent account</option>
                                            <?php foreach($data['parents'] as $p) : ?>
                                                <option value="<?php echo (int)$p['ID'];?>" data-forgroup="<?php echo (int)$p['forGroup'];?>"
                                                    <?php selectdCheck((string)$data['parent'],(string)$p['ID'])?>>
                                                    <?php echo $h(strtoupper($p['path']));?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $h($data['errors']['parent'] ?? '');?></span>
                                        <small class="text-muted">Accounts with postings, banks and 'cash at bank' are not listed. Whether this account
                                            posts or only groups its sub accounts is worked out automatically.</small>
                                    </div>
                                </div>
                            </div>
                            <?php if(!empty($data['errors']['congregation'])) : ?>
                                <div class="alert alert-danger"><?php echo $h($data['errors']['congregation']);?></div>
                            <?php endif;?>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                         <label for="description">Description</label>
                                         <input type="text" name="description" id="description"
                                                class="form-control form-control-sm" maxlength="255"
                                                value="<?php echo $h($data['description']);?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label class="custom-sm">
                                                <input type="checkbox" id="forgroup" name="forgroup"
                                                <?php echo ($data['forgroup']) ? 'checked' : ''?>> For Group
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
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
        // Rebuild the parent list from the server (type filter / congregation changed). Options are built with the DOM, never as HTML strings.
        function loadParents(){
            var keep = $('#parent').val();
            $.ajax({
                url  : '<?php echo URLROOT;?>/accounts/getparents',
                method : 'POST',
                dataType : 'json',
                data : {root : $('#root').val(), congregation : $('#congregation').length ? $('#congregation').val() : '<?php echo (int)$_SESSION['congId'];?>'},
                success : function(list){
                    var $p = $('#parent').empty().append($('<option>').val('').text('Select the parent account'));
                    list.forEach(function(a){
                        $p.append($('<option>').val(a.id).text(a.label).attr('data-forgroup', a.forgroup));
                    });
                    $p.val(keep);
                }
            });
        }
        $('#root').change(loadParents);
        $('#congregation').change(loadParents);
        // For Group is pre-ticked from the parent's value when the parent is chosen; the user can still change it (nothing is forced on the server)
        $('#parent').change(function(){
            var fg = $(this).find('option:selected').attr('data-forgroup');
            if (typeof fg !== 'undefined') { $('#forgroup').prop('checked', fg === '1'); }
        });
    });
</script>
</body>
</html>
