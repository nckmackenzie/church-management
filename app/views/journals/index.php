<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
 <!-- Content Wrapper. Contains page content -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Journal</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/journals/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Journal?</label>
                  <input type="hidden" name="id" id="id">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Yes</button>
              </div>
          </form>
      </div>
     
    </div>
  </div>
</div>
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
              <input type="hidden" name="firstJournalNo" id="firstJournalNo" value="">
              <input type="hidden" name="isedit" id="isedit" value="">
              <input type="hidden" name="usertype" id="usertype" value="<?php echo $_SESSION['userType'];?>">
            </div>
            <div class="col-sm-2">
              <?php if((int)$_SESSION['userType'] < 3) : ?>
                <button type="button" class="btn btn-sm btn-danger custom-font delete d-none">Delete</button>
              <?php endif; ?>
              <button type="button" class="btn btn-sm btn-info custom-font reset d-none">Reset</button>
            </div>
            <div class="col-sm-2"></div>
            <div class="col-sm-4 d-flex justify-content-end mt-2-xs ml-auto mt-0-md">
              <input type="search" name="search" id="search" class="form-control form-control-sm mr-1" placeholder="Search by Journal No...">
              <button type="button" class="btn btn-sm btn-info custom-font search">Search</button>
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