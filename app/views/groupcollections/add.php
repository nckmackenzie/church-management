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
            <a href="<?php echo URLROOT;?>/groupcollections" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
          <div class="col-sm-6"></div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto" id="alertBox"></div>
        </div>
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header"><?php echo $data['isedit'] ? 'Edit Group Collection' : 'Add Group Collection';?></div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/groupcollections/createupdate" method="post" id="form" autocomplete="off">
                            <div class="row">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label for="tdate">Date</label>
                                        <input type="date" name="tdate" id="date" 
                                               class="form-control form-control-sm mandatory" 
                                               value="<?php echo $data['tdate'];?>" required>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label for="type">Group Or District</label>
                                        <select name="type" id="type" class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select group/district</option>
                                            <option value="group" <?php selectdCheck($data['type'],'group'); ?>>Group</option>
                                            <option value="district" <?php selectdCheck($data['type'],'district'); ?>>District</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label for="groupid">Group/District</label>
                                        <select name="groupid" id="groupid" class="form-control form-control-sm mandatory">
                                            <?php if($data['isedit']) : ?>
                                                <?php foreach($data['groups'] as $group) : ?>
                                                    <option value="<?php echo $group->ID; ?>" <?php selectdCheck($data['groupid'],$group->ID);?>><?php echo strtoupper($group->ColumnName);?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="subaccount">Sub Account</label>
                                        <select name="subaccount" id="subaccount" class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select sub account</option>
                                            <?php if($data['isedit']) : ?>
                                                <?php foreach($data['subaccounts'] as $subaccount) : ?>
                                                    <option value="<?php echo $subaccount->ID; ?>" <?php selectdCheck($data['subaccount'],$subaccount->ID);?>><?php echo strtoupper($subaccount->AccountName);?></option>
                                                <?php endforeach; ?>
                                            <?php endif;?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="account">Account</label>
                                        <input type="text" name="account" id="account" class="form-control form-control-sm" value="<?php echo strtoupper($data['account']);?>" readonly>
                                        <input type="hidden" name="accountid" id="accountid" value="<?php echo $data['accountid'];?>">
                                        <input type="hidden" name="bankid" id="bankid" value="<?php echo $data['bankid'];?>">
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount" 
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['amount'];?>" placeholder="eg 2,000" required>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="narration">Description</label>
                                        <input type="text" name="narration" id="narration" 
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['narration'];?>" placeholder="enter brief description of transaction" required>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <input type="hidden" name="id" value="<?php echo $data['id'];?>" />
                                    <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>" />
                                    <button type="submit" class="btn btn-sm bg-navy custom-font" id="save">Save</button>                
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/groupcollections/index-v1.js"></script>
</body>
</html>  