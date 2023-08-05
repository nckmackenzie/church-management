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
                <a href="<?php echo URLROOT;?>/banks" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto" id="alertBox"></div>
        </div>
        <div class="row">
            <div class="col-md-4 mx-auto">
                <div class="card bg-light mt-2">
                    <div class="card-header">Add Sub account</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/banks/createsubaccount" method="post" id="subaccount">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="name">Account Name</label>
                                        <input type="text" name="name" id="name"
                                            class="form-control form-control-sm mandatory" 
                                            value="<?php echo $data['name'];?>"
                                            placeholder="Enter unique name"
                                            autocomplete="off">
                                        <span class="invalid-feedback"></span>       
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="bank">Parent Bank Account</label>
                                        <select name="bank" id="bank" class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select parent account</option>
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo $bank->ID;?>" <?php selectdCheck($data['bank'],$bank->ID);?>><?php echo $bank->Bank;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>       
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="glaccount">G/L Collection Account</label>
                                        <select name="glaccount" id="glaccount" class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select G/L account</option>
                                            <?php foreach($data['accounts'] as $account) : ?>
                                                <option value="<?php echo $account->ID;?>" <?php selectdCheck($data['account'],$account->ID);?>><?php echo $account->accountType;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>       
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="districtgroup">District/Group</label>
                                        <select name="districtgroup" id="districtgroup" class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select district or group</option>
                                            <option value="group" <?php selectdCheck($data['districtgroup'],"group");?>>Group</option>
                                            <option value="district" <?php selectdCheck($data['districtgroup'],"district");?>>District</option>
                                        </select>
                                        <span class="invalid-feedback"></span>       
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label for="param" id="paramLabel">District/Group</label>
                                        <select name="param" id="param" class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select one</option>
                                            <?php if($data['isedit']) : ?>
                                                <?php foreach($data['results'] as $result) : ?>
                                                    <option value="<?php echo $result->ID;?>" <?php selectdCheck($data['param'],$result->ID);?>><?php echo $result->ColumnName;?></option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>       
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-sm bg-navy custom-font save">Save</button>
                            <input type="hidden" name="id" id="id" value="<?php echo $data['id'];?>">
                            <input type="hidden" name="isedit" id="isedit" value="<?php echo $data['isedit'];?>">
                        </form>    
                    </div>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/bank/index.js"></script>
</body>
</html>  