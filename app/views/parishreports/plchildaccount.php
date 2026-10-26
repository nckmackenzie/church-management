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
      <div id="isapp" data-mode="drill" data-base="<?php echo URLROOT;?>/parishreports" data-parish="1">
        <div class="row"><div class="col-md-10 mx-auto mt-2" id="isalert"></div></div>
        <div class="row"><div class="col-md-10 mx-auto"><div id="results"></div></div></div>
      </div>
    </section>
  </div>
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/reports/is/income-statement-v2.js"></script>
</body>
</html>
