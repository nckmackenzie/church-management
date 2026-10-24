<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<style>
@media print {
  body * { visibility: hidden; }
  #printarea, #printarea * { visibility: visible; }
  #printarea { position: absolute; left: 0; top: 0; width: 100%; }
  .no-print { display: none !important; }
}
</style>
  <div class="content-wrapper">
    <section class="content">
      <div id="isapp" data-mode="index" data-base="<?php echo URLROOT;?>/reports" data-parish="0">
        <div class="row"><div class="col-md-10 mx-auto mt-2" id="isalert"></div></div>
        <div class="row">
          <div class="col-md-8 mx-auto mt-2">
            <div class="card bg-light"><div class="card-body">
              <div class="row">
                <div class="col-md-6"><div class="form-group"><label for="start">Start Date</label><input type="date" name="start" id="start" class="form-control form-control-sm"></div></div>
                <div class="col-md-6"><div class="form-group"><label for="end">End Date</label><input type="date" name="end" id="end" class="form-control form-control-sm"></div></div>
              </div>
              <button class="btn btn-sm btn-primary custom-font" id="preview" type="button">Preview</button>
            </div></div>
          </div>
        </div>
        <div class="row"><div class="col-md-10 mx-auto"><div id="results"></div></div></div>
      </div>
    </section>
  </div>
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/reports/is/income-statement-v2.js"></script>
</body>
</html>
