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
          <a href="<?php echo URLROOT;?>/products" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto" id="alertBox"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-header"><?php echo $data['title'];?></div>
                    <div class="card-body">    
                        <form action="" method="post" id="suppliers-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="suppliername">Supplier Name</label>
                                        <input type="text" name="suppliername" id="suppliername"
                                            class="form-control form-control-sm mandatory"
                                            value="<?php echo $data['suppliername'];?>" autocomplete="off">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="contact">Contact</label>
                                        <input type="text" name="contact" id="contact"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['contact'];?>" maxlength="10" autocomplete="off">   
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="pin">PIN</label>
                                        <input type="text" name="pin" id="pin"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['pin'];?>" autocomplete="off">   
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="address">Address</label>
                                        <input type="address" name="address" id="address"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['address'];?>" autocomplete="off">   
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" name="email" id="email"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['email'];?>" autocomplete="off">   
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contactperson">Contact Person</label>
                                        <input type="text" name="contactperson" id="contactperson"
                                            class="form-control form-control-sm"
                                            value="<?php echo $data['contactperson'];?>" autocomplete="off">   
                                    </div>
                                </div>
                                <?php if(!$data['isedit']) : ?>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="openingbal">Opening Balance</label>
                                            <input type="number" name="openingbal" id="openingbal"
                                                class="form-control form-control-sm"
                                                value="<?php echo $data['openingbal'];?>" autocomplete="off">   
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="asof">As Of</label>
                                            <input type="date" name="asof" id="asof"
                                                class="form-control form-control-sm"
                                                value="<?php echo $data['asof'];?>" autocomplete="off">   
                                        </div>
                                    </div>
                                <?php endif; ?>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/suppliers/add.js"></script>
</body>
</html>
  

