<?php require APPROOT . '/views/inc/header.php'; ?>
<?php require APPROOT . '/views/inc/topNav.php'; ?>
<?php require APPROOT . '/views/inc/sideNav.php'; ?>
<style media="print">
    .main-header, .main-sidebar, .content-header, .report-controls, .report-actions, .pagination { display: none !important; }
    .content-wrapper { margin-left: 0 !important; }
</style>
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid"><h1>Petty Cash Book</h1></div>
    </section>
    <section class="content">
        <div id="pcapp" data-base="<?php echo htmlspecialchars(URLROOT . '/reports', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="card report-controls">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label for="account">Petty cash account</label>
                            <select id="account" class="form-control form-control-sm">
                                <?php foreach ($data['accounts'] as $account) : ?>
                                    <option value="<?php echo (int) $account['id']; ?>"><?php echo htmlspecialchars((string) $account['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="start">From</label>
                            <input type="date" id="start" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 mb-2">
                            <label for="end">To</label>
                            <input type="date" id="end" class="form-control form-control-sm">
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
<?php require APPROOT . '/views/inc/footer.php'; ?>
<script type="module" src="<?php echo htmlspecialchars(URLROOT . '/dist/js/pages/reports/petty-cash-v1.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
