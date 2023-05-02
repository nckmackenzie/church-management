<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Receipt</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/cashreceipts/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Receipt?</label>
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
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('receipt_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/cashreceipts/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="receiptsTable">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Bank</th>
                            <th>Amount</th>
                            <th>Rerefence</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['receipts'] as $receipt) :?>
                            <tr>
                                <td><?php echo $receipt->ID;?></td>
                                <td><?php echo $receipt->TransactionDate;?></td>
                                <td><?php echo $receipt->Bank;?></td>
                                <td><?php echo $receipt->Debit;?></td>
                                <td><?php echo $receipt->Reference;?></td>
                                <td>
                                    <?php if((int)$_SESSION['userType'] <=2 || (int)$_SESSION['userType'] > 4) : ?>
                                      <div class="btn-group">
                                          <a href="<?php echo URLROOT;?>/cashreceipts/edit/<?php echo $receipt->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                          <button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
                                      </div>
                                    <?php endif; ?>
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
      $('#receiptsTable').DataTable({
        'columnDefs' : [
            {"visible" : false, "targets": 0},
            {"width" : "15%" , "targets": 3},
        ]
      });

      $('#receiptsTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          
          var currentRow = $(this).closest("tr");
          var data1 = $('#receiptsTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
    });
</script>
</body>
</html>