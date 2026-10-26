<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<style media="print">
    .main-header, .main-sidebar, .content-header, .report-controls, .report-actions, .no-print { display: none !important; }
    .content-wrapper { margin-left: 0 !important; }
</style>
<div class="content-wrapper">
    <section class="content-header">
      <div class="container-fluid"><h1>Sub Account Report</h1></div>
    </section>
    <section class="content">
        <div id="subapp" data-endpoint="<?php echo htmlspecialchars(URLROOT . '/reports/subaccountsrpt',ENT_QUOTES,'UTF-8');?>">
            <div class="card report-controls">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label for="subaccounts">Sub account</label>
                            <select name="subaccounts" id="subaccounts" class="form-control form-control-sm">
                                <option value="">Select sub account</option>
                                <option value="all">All</option>
                                <?php foreach($data['subaccounts'] as $account) : ?>
                                    <option value="<?php echo (int)$account['id'];?>"><?php echo htmlspecialchars(strtoupper((string)$account['name']),ENT_QUOTES,'UTF-8');?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="from" id="fromLabel">From</label>
                            <input type="date" name="from" id="from" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="to" id="toLabel">To</label>
                            <input type="date" name="to" id="to" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2 mb-2 d-flex align-items-end">
                            <button type="button" id="preview" class="btn btn-sm btn-primary">Preview</button>
                        </div>
                    </div>
                    <div id="report-error" class="alert alert-danger mt-2 mb-0" hidden></div>
                </div>
            </div>
            <div id="results"></div>
        </div>
    </section>
</div>
<?php require APPROOT . '/views/inc/footer.php'?>

<script type="module" src="<?php echo htmlspecialchars(URLROOT . '/dist/js/pages/reports/subaccounts/report-v2.js',ENT_QUOTES,'UTF-8');?>"></script>
</body>
</html>
