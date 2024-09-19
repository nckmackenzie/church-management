<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12" id="alertBox"></div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bank">Bank</label>
                                    <select name="bank" id="bank" class="form-control form-control-sm mandatory">
                                        <option value="">Select Bank</option>
                                        <?php foreach($data['banks'] as $bank) : ?>
                                            <option value="<?php echo $bank->ID;?>"><?php echo $bank->Bank;?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="invalid-feedback" id="bank_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="from">From</label>
                                    <input type="date" name="from" id="from" class="form-control form-control-sm mandatory">
                                    <span class="invalid-feedback" id="from_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="to">To</label>
                                    <input type="date" name="to" id="to" class="form-control form-control-sm mandatory">
                                    <span class="invalid-feedback" id="to_err"></span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="" style="color: #F8F9FA;">button</label>
                                <button type="button" class="btn btn-sm btn-info form-control form-control-sm fetch">Fetch</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="spinner-container d-flex justify-content-center"></div>
            <div class="col-md-12 table-responsive d-none">
                <form action="<?php echo URLROOT;?>/clearbankings/clear" id="clear-form">
                    <button type="submit" id="save" class="btn btn-sm bg-navy custom-font mb-3">Modify Selected</button>
                    <div id="results">
                        <div class="row">
                            <div class="col-12">
                                <div class="table-responsive">
                                    <table class="table-sm table-bordered table" id="clear-banking">
                                         <thead class="bg-nay">
                                            <tr>
                                                <th class="d-none">ID</th>
                                                <th>Select</th>
                                                <th>Txn Date</th>
                                                <th>Clear Date</th>
                                                <th>Amount</th>
                                                <th>Reference</th>
                                            </tr>
                                         </thead>
                                         <tbody></tbody>   
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>    
            </div>
        </div><!--End of row -->
    </section><!-- /.content -->
    <!-- </form> -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/bankings/modify-cleared-v1.js"></script>
</body>
</html> 