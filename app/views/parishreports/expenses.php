<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="congregation">Congregation</label>
                                    <select name="congregation[]" id="congregation" class="form-control form-control-sm multi" multiple data-actions-box="true">
                                        <?php foreach($data['congregations'] as $congregation) : ?>
                                            <option value="<?php echo (int)$congregation->ID;?>"><?php echo htmlspecialchars((string)$congregation->CongregationName,ENT_QUOTES,'UTF-8');?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback" id="congregation_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="account">Account</label>
                                    <select name="account[]" id="account" class="form-control form-control-sm multi" multiple data-actions-box="true">
                                        <?php foreach($data['accounts'] as $accounts) : ?>
                                            <option value="<?php echo (int)$accounts->ID;?>"><?php echo htmlspecialchars((string)$accounts->accountType,ENT_QUOTES,'UTF-8');?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback" id="account_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start">Start Date</label>
                                    <input type="date" name="start" id="start" class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="start_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="end">End Date</label>
                                    <input type="date" name="end" id="end" class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="end_err"></span>      
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-2">
                                <button class="btn btn-sm btn-primary custom-font" id="preview">Preview</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- data -->
        <div class="row">
            <div class="col-md-12">
                <div id="listapp" hidden data-endpoint="<?php echo htmlspecialchars(URLROOT . '/parishreports/expensesrpt',ENT_QUOTES,'UTF-8');?>" data-account-key="accounts" data-cong-key="congregations" data-need-account="always"></div>
                <div id="results">

                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('.multi').selectpicker();
    });
</script>
<script type="module" src="<?php echo htmlspecialchars(URLROOT . '/dist/js/pages/reports/filter-list-v1.js',ENT_QUOTES,'UTF-8');?>"></script>
</body>
</html>
