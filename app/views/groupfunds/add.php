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
            <a href="<?php echo URLROOT;?>/groupfunds" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
          <div class="col-sm-6"></div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <?php if(!empty($data['errmsg'])) : ?>
                    <?php echo alert($data['errmsg']); ?>
                <?php endif;?>
                <div class="card card-light">
                    <div class="card-header"><?php echo $data['title'];?></div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/groupfunds/createupdate" autocomplete="off" method="post">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="reqno">Request No</label>
                                        <input type="text" name="reqno" id="reqno" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['reqno'];?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="date">Request Date</label>
                                        <input type="date" name="date" id="date" 
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['reqdate'];?>">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="group">Group</label>
                                        <select name="group" id="group" class="form-control form-control-sm mandatory">
                                            <option value="">Select group</option>
                                            <?php foreach($data['groups'] as $group) : ?>
                                                <option value="<?php echo $group->ID;?>" <?php selectdCheck($data['group'],$group->ID);?>><?php echo $group->groupName;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="availableamount">Amount Available</label>
                                        <input type="number" name="availableamount" id="availableamount" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['availableamount'];?>" readonly>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount Requesting</label>
                                        <input type="number" name="amount" id="amount" 
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['amount'];?>"
                                               placeholder="20,000">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="reason">Purpose</label>
                                        <input type="text" name="reason" id="reason" 
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo $data['reason'];?>"
                                               placeholder="Reason for requesting funds">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="dontdeduct" name="dontdeduct" <?php echo $data['dontdeduct'] ? 'checked' : '';?>>
                                        <label class="custom-control-label" for="dontdeduct">Don't deduct from Group Funds</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-2 mt-2">
                                    <input type="hidden" name="id" value="<?php echo $data['id'];?>">
                                    <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>">
                                    <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/groupfunds/add-v1.js"></script>
</body>
</html>  