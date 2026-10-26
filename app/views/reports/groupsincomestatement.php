<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<style media="print">
    .main-header, .main-sidebar, .content-header, .report-controls, .report-actions, .no-print { display: none !important; }
    .content-wrapper { margin-left: 0 !important; }
</style>
<div class="content-wrapper">
    <section class="content">
        <div id="gpl" data-base="<?php echo htmlspecialchars(URLROOT . '/reports',ENT_QUOTES,'UTF-8');?>">
            <div class="row">
                <div class="col-md-9 mx-auto mt-2">
                    <div class="card bg-light report-controls">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="group">Group</label>
                                        <select name="group" id="group" class="form-control form-control-sm">
                                            <option value="" selected>Select group</option>
                                            <?php if(!empty($data['groups'])) : ?>
                                            <optgroup label="Groups">
                                                <?php foreach($data['groups'] as $group) : ?>
                                                    <option value="group:<?php echo (int)$group['ID'];?>"><?php echo htmlspecialchars((string)$group['name'],ENT_QUOTES,'UTF-8');?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>
                                            <?php if(!empty($data['districts'])) : ?>
                                            <optgroup label="Districts">
                                                <?php foreach($data['districts'] as $district) : ?>
                                                    <option value="district:<?php echo (int)$district['ID'];?>"><?php echo htmlspecialchars((string)$district['name'],ENT_QUOTES,'UTF-8');?></option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="start">Start Date</label>
                                        <input type="date" name="start" id="start" class="form-control form-control-sm">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="end">End Date</label>
                                        <input type="date" name="end" id="end" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-4">
                                    <button type="button" class="btn btn-sm btn-primary custom-font" id="preview">Preview</button>
                                </div>
                            </div>
                            <div id="report-error" class="alert alert-danger mt-2 mb-0" hidden></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-9 mx-auto">
                    <div id="results"></div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo htmlspecialchars(URLROOT . '/dist/js/pages/reports/pl/grouppl-v2.js',ENT_QUOTES,'UTF-8');?>"></script>
</body>
</html>
