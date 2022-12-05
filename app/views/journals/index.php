<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
 <!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <form action="" id="journal-form" autocomplete="off">
      <div class="content-header">
        <div class="container-fluid">
          <div class="row">
            <div class="col-12" id="alertBox"></div>
          </div>
          <div class="row mb-2">
            <div class="col-sm-2">
              <button type="submit" class="btn btn-sm bg-navy custom-font btn-block save">Save</button>
              <input type="hidden" name="currentJournalNo" id="currentJournalNo" value="">
              <input type="hidden" name="isedit" id="isedit" value="">
            </div>
            <div class="col-sm-4"></div>
            <div class="col-sm-6 d-flex justify-content-end mt-2-xs mt-0-md">
              <button type="button" class="btn btn-sm btn-info custom-font prev mr-1">&larr; Prev</button>
              <button type="button" class="btn btn-sm btn-info custom-font next mr-1">&rarr; Next</button>
              <button type="button" class="btn btn-sm btn-danger custom-font delete">Delete</button>
            </div>
          </div>
        </div><!-- /.container-fluid -->
      </div>
      <!-- Main content -->
      <div class="content px-3">
        <div class="spinner-container d-flex justify-content-center align-items-center"></div>
          <div class="entries">
            <div class="card">
              <div class="card-body">
                <div class="row">
                  <div class="col-sm-2">
                      <label for="journalno">Journal No</label>
                      <input type="number" name="journalno" id="journalno" class="form-control form-control-sm" readonly>
                  </div>
                  <div class="col-sm-3">
                      <label for="date">Date</label>
                      <input type="date" name="date" id="date" 
                             class="form-control form-control-sm mandatory"
                             value="<?php echo $data['date'];?>">
                      <span class="invalid-feedback"></span>
                  </div>
                  <div class="col-sm-3">
                      <label for="debits">Total Debits</label>
                      <input type="text" name="debits" id="debits" class="form-control form-control-sm" readonly>
                  </div>
                  <div class="col-sm-3">
                      <label for="credits">Total Credits</label>
                      <input type="text" name="credits" id="credits" class="form-control form-control-sm" readonly>
                  </div>
                </div>
                <hr>
                <div class="row">
                  <div class="col-md-5 mb-2">
                    <label for="account">G/L Account</label>
                    <select name="account" id="account" class="form-control form-control-sm select2 table-required">
                        <option value="" selected disabled>Select Account</option>
                        <?php foreach($data['accounts'] as $account) : ?>
                          <option value="<?php echo $account->ID;?>"><?php echo $account->accountType;?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"></span>
                  </div>
                  <div class="col-md-2 mb-2">
                    <label for="type">Debit/Credit</label>
                    <select name="type" id="type" class="form-control form-control-sm table-required">
                        <option value="" selected disabled>Select Debit/Credit</option>
                        <option value="debit">Debit</option>
                        <option value="credit">Credit</option>
                    </select>
                    <span class="invalid-feedback"></span>
                  </div>
                  <div class="col-md-2 mb-2">
                    <label for="amount">Amount</label>
                    <input type="number" class="form-control form-control-sm table-required" id="amount" name="amount" placeholder="eg 2,000">
                    <span class="invalid-feedback"></span>
                  </div>
                  <div class="col-md-3 mb-2">
                    <label for="description">Description</label>
                    <input type="text" class="form-control form-control-sm" id="description" name="description" 
                            placeholder="Brief description...">
                  </div>
                  <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-success btn-block add">Add</button>
                  </div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-12">
                <div class="table-responsive">
                  <table class="table table-sm table-bordered" id="table-entries">
                    <thead class="table-secondary">
                      <tr>
                        <th class="d-none">ID</th>
                        <th style="width: 30%;">Account</th>
                        <th style="width: 10%;">Debit</th>
                        <th style="width: 10%;">Credit</th>
                        <th>Desciption</th>
                        <th style="width: 10%;">Remove</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
      </div><!-- /.content -->
    </form>
</div><!-- /.content-wrapper -->

<?php require APPROOT . '/views/inc/footer.php'?>
<script>
  $(function(){
    $('.select2').select2();
  })
</script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/journals/index.js"></script>
</body>
</html>