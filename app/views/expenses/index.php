<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Expense</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/expenses/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Expense?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="date" id="date">
                  <input type="hidden" name="voucherno" id="voucherno">
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
<!-- Modal -->
<div class="modal fade" id="approveModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Approve Expenses</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/expenses/approve" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Approve Selected Expenses?</label>
                  <input type="hidden" name="id" id="aid">
                  <input type="hidden" name="date" id="adate">
                  <input type="hidden" name="voucherno" id="avoucherno">
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
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('expense_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/expenses/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="expensesTable">
                    <thead class="bg-navy">
                        <th>ID</th>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Expense Account</th>
                        <th>Amount</th>
                        <th>Category</th>
                        <th>Cost Centre</th>
                        <th>Status</th>
                        <th>Action</th>
                    </thead>
                    <tbody>
                        <?php foreach($data['expenses'] as $expense) :?>
                            <tr>
                                <td><?php echo $expense->ID;?></td>
                                <td><?php echo date('d-M-Y',strtotime($expense->expenseDate));?></td>
                                <td><?php echo $expense->voucherNo;?></td>
                                <td><?php echo $expense->account;?></td>
                                <td><?php echo $expense->amount;?></td>
                                <td><?php echo $expense->category;?></td>
                                <td><?php echo $expense->costcentre;?></td>
                                <?php if($expense->status == 0) : ?>
                                    <td><span class="badge bg-warning">Unapproved</span></td>
                                <?php else : ?>
                                    <td><span class="badge bg-success">Approved</span></td>
                                <?php endif; ?>    
                                <td>
                                    <div class="btn-group">
                                      <?php if((int)$_SESSION['userType'] < 3 || (int)$_SESSION['userType'] > 4) : ?>
                                          <?php if($expense->status == 0) : ?>
                                              <button type="button" class="btn btn-sm btn-dark custom-font btnapprove"><i class="fas fa-check"></i></button>
                                              <a href="<?php echo URLROOT;?>/expenses/edit/<?php echo $expense->ID;?>" class="btn btn-sm bg-olive btnedit custom-font"><i class="far fa-edit"></i></a>
                                              <button type="button" class="btn btn-sm btn-danger custom-font btndel"><i class="far fa-trash-alt"></i></button>
                                          <?php endif; ?>
                                      <?php endif; ?>  
                                          <a target="_blank" href="<?php echo URLROOT;?>/expenses/print/<?php echo $expense->ID;?>" class="btn btn-sm bg-warning btnprint custom-font"><i class="fas fa-print"></i></a>
                                        <?php if(strlen(trim($expense->fileName)) > 1) : ?>
                                          <a target="_blank" href="<?php echo URLROOT;?>/img/<?php echo $expense->fileName;?>" class="btn btn-sm bg-info btnreceipt custom-font"><i class="far fa-file"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
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
      $('#expensesTable').DataTable({
        'pageLength': 25,
        'ordering': false,
        'columnDefs' : [
            {"visible" : false, "targets": 0},
            {"width" : "10%" , "targets": 1},
            {"width" : "10%" , "targets": 2},
            {"width" : "10%" , "targets": 4},
            {"width" : "10%" , "targets": 7},
            {"width" : "15%" , "targets": 8},
          ]
      });
      
      $(".btnapprove").attr('title', 'Approve');
      $(".btnedit").attr('title', 'Edit');
      $(".btndel").attr('title', 'Delete');
      $(".btnprint").attr('title', 'Print Voucher');
      $(".btnreceipt").attr('title', 'View Receipt');

      $('#expensesTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#date').val(data[0]);
          $('#voucherno').val(data[1]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#expensesTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
      $('#expensesTable').on('click','.btnapprove',function(){
        $('#approveModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#adate').val(data[0]);
          $('#avoucherno').val(data[1]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#expensesTable').DataTable().row(currentRow).data();
          $('#aid').val(data1[0]);
      });
    });
</script>
</body>
</html>