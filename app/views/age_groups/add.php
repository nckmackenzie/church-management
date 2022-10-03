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
                <a href="<?php echo URLROOT;?>/age_groups" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Add Age Group</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/age_groups/create" method="post">
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group">
                                        <label for="name">Age Group Name</label>
                                        <input type="text"  id="name" name="name"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['name'];?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['name_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group">
                                        <label for="from">From Age</label>
                                        <input type="number"  id="from" name="from"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['from_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['from'];?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['from_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-10">
                                    <div class="form-group">
                                        <label for="to">To Age</label>
                                        <input type="number"  id="to" name="to"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['to_err'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $data['to'];?>"
                                               autocomplete="off">
                                        <span class="invalid-feedback"><?php echo $data['to_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm custom-font bg-navy">Save</button>
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