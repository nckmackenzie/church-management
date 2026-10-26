<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper" id="isapp" data-base="<?php echo URLROOT;?>/reports">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div id="isalert"></div>
            </div>
          <div class="col-sm-4">
             <div class="form-group">
                <label for="account">G/L Account</label>
                <select name="account" id="account" class="form-control form-control-sm mandatory">
                    <option value="" selected disabled>Select G/L account</option>
                    <?php foreach($data['accounts'] as $account) : ?>
                        <option value="<?php echo (int)$account->ID?>"><?php echo htmlspecialchars((string)$account->accountType,ENT_QUOTES,'UTF-8')?></option>
                    <?php endforeach?>
                </select>
                <span class="invalid-feedback" id="account-err"></span>
             </div>
          </div>
          <div class="col-sm-3">
             <div class="form-group">
                <label for="sdate">Start Date</label>
                <input type="date" name="sdate" id="sdate" class="form-control form-control-sm">
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-3">
             <div class="form-group">
                <label for="edate">End Date</label>
                <input type="date" name="edate" id="edate" class="form-control form-control-sm">
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-2">
             <div class="form-group">
             <label for="edate" style="color: #F4F6F9;">End Date</label>
                <div class="checkbox">    
                    <label class="custom-sm">
                        <input type="checkbox" id="subledgers" name="subledgers" checked>Include Subledgers
                    </label>       
                </div>  
             </div>
          </div>
          <div class="col-sm-2">
            <button type="button" class="btn btn-sm btn-primary preview">Preview</button>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <style>
@media print {
  body * { visibility: hidden; }
  #printarea, #printarea * { visibility: visible; }
  #printarea { position: absolute; left: 0; top: 0; width: 100%; }
  .no-print { display: none !important; }
}
</style>
    <section class="content">
        <div class="col-md-12"><div id="results"></div></div>
    </section>
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){ $('#account').select2(); });
</script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/reports/is/ledger-statement-v1.js"></script>
</body>
</html>
