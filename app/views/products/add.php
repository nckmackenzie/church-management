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
            <div class="col-md-6 mx-auto">
                <div class="card card-body bg-light mt-5">
                    <h5><?php echo $data['title'];?></h5>
                    <hr>
                    <form action="<?php echo URLROOT;?>/products/createupdate" method="post">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="productname">Product Name</label>
                                    <input type="text" name="productname" id="productname"
                                    class="form-control form-control-sm mandatory
                                    <?php echo inputvalidation($data['productname'],$data['productname_err'],$data['touched']);?>"
                                    value="<?php echo $data['productname'];?>" autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['productname_err'];?></span>
                                </div>
                            </div>
                        </div>    
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <input type="text" name="description" id="description"
                                    class="form-control form-control-sm"
                                    value="<?php echo $data['description'];?>" autocomplete="off">   
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="rate">Selling Price/Rate</label>
                                    <input type="number" name="rate" id="rate"
                                    class="form-control form-control-sm 
                                    <?php echo inputvalidation($data['rate'],$data['rate_err'],$data['touched']) ;?>"
                                    value="<?php echo $data['rate'];?>"
                                    autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['rate_err'];?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="glaccount">Associated G/L</label>
                                    <select name="glaccount" id="glaccount" class="form-control form-control-sm mandatory">
                                        <option value="">Select G/L Account</option>
                                        <?php foreach($data['glaccounts'] as $glaccount)  : ?>
                                            <option value="<?php echo $glaccount->ID;?>" <?php selectdCheck($data['glaccount'],$glaccount->ID);?>><?php echo $glaccount->accountType;?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="invalid-feedback"><?php echo $data['glaccount_err'];?></span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2 mt-2">
                                <input type="hidden" name="touched" value="<?php echo $data['touched'];?>">
                                <input type="hidden" name="id" value="<?php echo $data['id'];?>">
                                <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>">
                                <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
                            </div>
                        </div>   
                    </form>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
</body>
</html>
  

