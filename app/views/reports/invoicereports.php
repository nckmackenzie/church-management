<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
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
                    <option value="balances">Invoice With Balances</option>
                    <option value="byinvoice">Payments - By Invoice</option>
                    <option value="bysupplier">Payments - By Supplier</option>
                    <option value="supplierbalances">Supplier Balances</option>
                    <option value="all">All Payments</option>
                </select>
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-3">
             <div class="form-group">
                <label for="criteria">Criteria</label>
                <select name="criteria" id="criteria" class="form-control form-control-sm" disabled></select>
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-3">
             <div class="form-group">
                <label for="sdate">Start Date</label>
                <input type="date" name="sdate" id="sdate" class="form-control form-control-sm" disabled>
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-3">
             <div class="form-group">
                <label for="edate">End Date</label>
                <input type="date" name="edate" id="edate" class="form-control form-control-sm" disabled>
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-2">
            <button type="button" class="btn btn-sm btn-primary preview">Preview</button>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div id="results"></div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/reports/invoices/invoicereports-v1.js"></script>
</body>
</html>  