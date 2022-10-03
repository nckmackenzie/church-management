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
             <a href="<?php echo URLROOT;?>/vats" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Edit V.A.T</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/vats/update" method="post">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="vatname">V.A.T Name</label>
                                        <input type="text" name="vatname" id="vatname"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['name_err'])) ? 'is-invalid' : '';?>"
                                               value="<?php echo (!empty($data['vatname'])) ? $data['vatname'] : strtoupper($data['vat']->vatName); ?>"
                                               placeholder="eg 16% or V.A.T 16%"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['name_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="rate">V.A.T Rate</label>
                                        <input type="number" name="rate" id="rate"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['rate_err'])) ? 'is-invalid' : '';?>"
                                               value="<?php echo (!empty($data['rate'])) ? $data['rate'] : $data['vat']->rate; ?>"
                                               placeholder="eg 16 for 16% "
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['rate_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="checkbox">    
                                            <label class="custom-sm">
                                                <input type="checkbox" id="active" name="active"
                                                <?php echo ($data['vat']->active == 1) ? 'checked' : ''?>> Active
                                            </label>       
                                        </div>  
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo $data['vat']->ID;?>">
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
</body>
</html>  