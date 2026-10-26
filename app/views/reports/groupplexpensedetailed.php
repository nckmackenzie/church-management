<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<style media="print">
    .main-header, .main-sidebar, .content-header, .report-controls, .report-actions, .no-print { display: none !important; }
    .content-wrapper { margin-left: 0 !important; }
</style>
<div class="content-wrapper">
    <section class="content">
        <div id="gpldetail" data-endpoint="<?php echo htmlspecialchars(URLROOT . '/reports/groupplexpensedetailedrpt',ENT_QUOTES,'UTF-8');?>">
            <div id="report-error" class="alert alert-danger mt-2" hidden></div>
            <div id="results" class="mt-2"></div>
        </div>
    </section>
</div>
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo htmlspecialchars(URLROOT . '/dist/js/pages/reports/pl/grouppl-detail-v2.js',ENT_QUOTES,'UTF-8');?>"></script>
</body>
</html>
