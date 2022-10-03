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
          <a href="<?php echo URLROOT;?>/services" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card card-body bg-light mt-5">
                    <h5>Edit Service</h5>
                <hr>
                <form action="<?php echo URLROOT;?>/services/update" method="post">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="servicename">Service Name:</label>
                                <input type="text" id="servicename" 
                                class="form-control form-control-sm mandatory
                                <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''?>"
                                name="servicename" value="<?php echo $data['service']->serviceName;?>"
                                placeholder="Enter Service Name" autocomplete="off">
                                <span class="invalid-feedback"><?php echo $data['name_err'];?></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="servicetime">Service Time:</label>
                                <input type="text" id="servicetime" 
                                class="form-control form-control-sm mandatory
                                <?php echo (!empty($data['time_err'])) ? 'is-invalid' : ''?>"
                                name="servicetime" value="<?php echo $data['service']->serviceTime;?>"
                                placeholder="Enter Service Time" autocomplete="off">
                                <span class="invalid-feedback"><?php echo $data['time_err'];?></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-2 mt-2">
                            <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
                            <input type="hidden" name="id" value="<?php echo $data['service']->ID;?>">
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