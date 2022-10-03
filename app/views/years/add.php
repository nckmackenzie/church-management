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
            <a href="<?php echo URLROOT;?>/years" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Add Year</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/years/create" method="post">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="yearname">Year Name</label>
                                        <input type="text" name="yearname" id="yearname"
                                        class="form-control form-control-sm mandatory
                                        <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''?>"
                                        value="<?php echo $data['yearname'];?>"
                                        placeholder="eg 2020-2021 or 2020/2021"
                                        autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['name_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="startdate">Start Date</label>
                                        <input type="date" name="startdate" id="startdate"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['start_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['startdate'];?>">
                                        <span class="invalid-feedback"><?php echo $data['start_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="enddate">End Date</label>
                                        <input type="date" name="enddate" id="enddate"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['end_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['enddate'];?>">
                                        <span class="invalid-feedback"><?php echo $data['end_err'];?></span>
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
</body>
</html>  