<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-10 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="type">Report Type</label>
                                <select name="type" id="type" class="form-control form-control-sm">
                                    <option value="1">All</option>
                                    <option value="2">By Expenses Acc</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="account">Expenses Acc</label>
                                <select name="account[]" id="account" class="form-control form-control-sm" multiple disabled>
                                    <?php foreach($data['accounts'] as $accounts) : ?>
                                        <option value="<?php echo (int)$accounts->ID;?>"><?php echo htmlspecialchars((string)$accounts->accountType,ENT_QUOTES,'UTF-8');?></option>
                                    <?php endforeach;?>
                                </select>
                                <span class="invalid-feedback" id="account_err"></span>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="start">Start Date</label>
                                <input type="date" name="start" id="start" class="form-control form-control-sm">
                                <span class="invalid-feedback" id="start_err"></span>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="end">End Date</label>
                                <input type="date" name="end" id="end" class="form-control form-control-sm">
                                <span class="invalid-feedback" id="end_err"></span>
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
                <div id="listapp" hidden data-endpoint="<?php echo htmlspecialchars(URLROOT . '/reports/expensesrpt',ENT_QUOTES,'UTF-8');?>" data-account-key="account" data-need-account="type2"></div>
                <div id="results">

                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('#account').selectpicker();
        $('#type').change(function(){
            var type = $(this).val();
            if(type == 2 ){
                $('#account').attr('disabled',false);
                $('.bootstrap-select').removeClass('disabled');
                $('.bootstrap-select button').removeClass('disabled');
            }else{
                $('#account').attr('disabled',true);
                $('.bootstrap-select').addClass('disabled');
                $('.bootstrap-select button').addClass('disabled');
            }
        });
    });
</script>
<script type="module" src="<?php echo htmlspecialchars(URLROOT . '/dist/js/pages/reports/filter-list-v1.js',ENT_QUOTES,'UTF-8');?>"></script>
</body>
</html>
