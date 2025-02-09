<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
  <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div id="alertBox"></div>
            </div>
          <div class="col-sm-3">
             <div class="form-group">
                <label for="type">Report Type</label>
                <select name="type" id="type" class="form-control form-control-sm mandatory">
                    <option value="" selected disabled>Select report type</option>
                    <option value="summary">Summary</option>
                    <option value="detailed">Detailed</option>
                </select>
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-3">
             <div class="form-group">
                <label for="sdate">As of</label>
                <input type="date" name="asofdate" id="asofdate" class="form-control form-control-sm mandatory">
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <!-- <div class="col-sm-3">
             <div class="form-group">
                <label for="sdate">Start Date</label>
                <input type="date" name="sdate" id="sdate" class="form-control form-control-sm mandatory">
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-3">
             <div class="form-group">
                <label for="edate">End Date</label>
                <input type="date" name="edate" id="edate" class="form-control form-control-sm mandatory">
                <span class="invalid-feedback"></span>
             </div>
          </div> -->
          <div class="col-sm-6"></div>
          <div class="col-sm-2">
            <button type="button" class="btn btn-sm btn-primary preview">Preview</button>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto d-none" id="results"></div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/reports/tb/tb-v2.js"></script>
</body>
</html>  