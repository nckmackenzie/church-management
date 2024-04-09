<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Bank</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/banks/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Bank?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="bankname" id="bankname">
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
<div class="modal fade" id="deleteSubModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="">Delete Sub Account</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/banks/deletesubaccount" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete selected sub account?</label>
                  <input type="hidden" name="id" id="sid">
                  <input type="hidden" name="subaccount" id="subaccount">
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
<div class="modal fade" id="balanceSubModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <div class="d-flex flex-column">
          <h5 class="modal-title" id="">Set Sub Account opening balance</h5>
          <p class="block text-danger">This action cannot be undone.</p>
        </div>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/banks/openingbalance" method="post" id="openingbalance">
              <div class="row">
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="subaccount">Sub Account</label>
                    <select name="subaccount" id="subaccount" class="form-control form-control-sm mandatory">
                      <option value="" selected disabled>Select sub account</option>
                      <?php foreach($data['subaccounts'] as $subaccount) : ?>
                        <option value="<?php echo $subaccount->ID;?>"><?php echo ucwords($subaccount->AccountName);?></option>
                      <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"></span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="asof">As Of</label>
                    <input type="date" name="asof" id="asof" class="form-control form-control-sm mandatory">
                    <span class="invalid-feedback"></span>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="balance">Balance</label>
                    <input type="number" name="balance" id="balance" class="form-control form-control-sm mandatory">
                    <span class="invalid-feedback"></span>
                  </div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Save</button>
              </div>
          </form>
      </div>
     
    </div>
  </div>
</div>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('bank_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/banks/add" class="btn btn-sm btn-success custom-font">Add New</a>
            <a href="<?php echo URLROOT;?>/banks/subaccount" class="btn btn-sm btn-info custom-font">Add Sub Account</a>
            <button class="btn btn-sm btn-secondary custom-font">Set Sub Account Opening Balance</button>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="banksTable">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Bank Name</th>
                            <th>Account No</th>
                            <?php if ($_SESSION['userType'] <=2) : ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['banks'] as $bank) :?>
                            <tr>
                                <td><?php echo $bank->ID;?></td>
                                <td><?php echo $bank->accountType;?></td>
                                <td><?php echo $bank->accountNo;?></td>
                                <?php if($_SESSION['userType'] <=2) : ?>
                                  <td>
                                    <div class="btn-group">
                                        <a href="<?php echo URLROOT;?>/banks/edit/<?php echo $bank->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                        <button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
                                    </div>
                                  </td>     
                                <?php endif; ?>
                            </tr>
                            <?php
                                $con=new PDO('mysql:host=localhost;dbname='.DB_NAME.'',DB_USER,DB_PASS);
                                $con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                                $sql = 'SELECT s.ID,UCASE(s.AccountName) as AccountName,
                                               UCASE(a.accountType) AS Account
                                        FROM   tblbanksubaccounts s join tblaccounttypes a on s.AccountId = a.ID
                                        WHERE  (s.BankId=?) AND (s.Deleted=0)';
                                $stmt = $con->prepare($sql);
                                $stmt->execute([$bank->ID]);
                                $hasChildren = $stmt->rowCount() > 0 ? true : false;
                            ?>
                            <?php if($hasChildren) : ?>
                                <?php foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $child) : ?>
                                  <tr>
                                      <td><?php echo $child->ID;?></td>
                                      <td class="sub-level-3"><?php echo $child->AccountName;?></td>
                                      <td><?php echo $child->Account;?></td>
                                      <?php if($_SESSION['userType'] <=2) : ?>
                                        <td>
                                            <a href="<?php echo URLROOT;?>/banks/editsubaccount/<?php echo $child->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                            <button type="button" class="btn btn-sm btn-danger custom-font btndelsub">Delete</button>
                                        </td>
                                      <?php endif; ?>
                                  </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>    
        </div>        
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
      $('#banksTable').DataTable({
        'columnDefs' : [
            {"visible" : false, "targets": 0}
            ,{"width" : "15%" , "targets": 3},
         ],
         ordering: false
      });

      $('.btn-secondary').click(function(){
        $('#balanceSubModalCenter').modal('show')
      })

      $('#banksTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#bankname').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#banksTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });

      $('#banksTable').on('click','.btndelsub',function(){
          $('#deleteSubModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#subaccount').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#banksTable').DataTable().row(currentRow).data();
          $('#sid').val(data1[0]);
      });
    });
</script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/bank/balance.js"></script>
</body>
</html>