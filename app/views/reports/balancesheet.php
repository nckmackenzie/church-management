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
      <div id="isapp" data-mode="index" data-base="<?php echo URLROOT;?>/reports" data-isurl="<?php echo URLROOT;?>/reports/incomestatement">
        <div class="row"><div class="col-md-10 mx-auto mt-2" id="isalert"></div></div>
        <div class="row"><div class="col-md-8 mx-auto mt-2"><div class="card bg-light"><div class="card-body"><div class="row">
          <div class="col-md-4"><div class="form-group"><label for="asof">As Of Date</label><input type="date" name="asof" id="asof" class="form-control form-control-sm"></div></div>
        </div><button class="btn btn-sm btn-primary custom-font" id="preview" type="button">Preview</button></div></div></div></div>
        <div class="row"><div class="col-md-10 mx-auto"><div id="results"></div></div></div>
      </div>
    </section>
  </div>
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/reports/is/balance-sheet-v1.js"></script>
</body>
</html>
