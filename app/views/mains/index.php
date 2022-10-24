<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12 mt-2">
          <?php flash('main_msg');?>
        </div>
        <div class="col-8 mx-auto">
           <?php if(ENVIRONMENT === 'testing') : ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
              <strong>WARNING!!!</strong> STRICTLY FOR TEST PURPOSES ONLY.
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
           <?php endif; ?>
        </div>
      </div>
      <?php if((int)$_SESSION['userType'] === 1 || (int)$_SESSION['userType'] === 6) : ?> 
        <div class="row">
          <div class="col-md-4 mt-4">
            <form action="<?php echo URLROOT;?>/mains/changecongregation" method="post">
                <div class="form-group">
                  <select name="congregation" id="congregation" class="form-control form-control-sm">
                      <option value="" selected disabled>Select congregation to change to...</option>
                      <?php foreach($data['congregations'] as $congregation) : ?>
                        <option value="<?php echo $congregation->ID;?>"><?php echo strtoupper($congregation->CongregationName);?></option>
                      <?php endforeach; ?>
                  </select>
                </div>
                <button class="btn btn-sm bg-navy">Change</button>
            </form>
          </div>
        </div>
      <?php endif; ?>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
</body>
</html>