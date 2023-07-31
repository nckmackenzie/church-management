<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Contribution</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/contributions/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Contribution?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="date" id="date">
                  <input type="hidden" name="contributor" id="contributor">
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
        <h5 class="modal-title" id="exampleModalLongTitle">Approve Contribution</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/contributions/approve" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Approve Selected Contribution?</label>
                  <input type="hidden" name="id" id="aid">
                  <input type="hidden" name="date" id="adate">
                  <input type="hidden" name="contributor" id="acontributor">
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
        <?php flash('contribution_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/contributions/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="contributionsTable">
                    <thead class="bg-navy">
                        <th>ID</th>
                        <th>Receipt No</th>
                        <th>Contribution Date</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </thead>
                    <tbody>
                        <?php foreach($data['contributions'] as $contribution) :?>
                            <tr>
                                <td><?php echo $contribution->ID;?></td>
                                <td><?php echo $contribution->receiptNo;?></td>
                                <td><?php echo date('d-M-Y',strtotime($contribution->contributionDate));?></td>
                                <td><?php echo $contribution->Total;?></td>
                                <td>
                                    <?php if((int)$_SESSION['userType'] <3 || (int)$_SESSION['userType'] > 4) : ?>
                                      <div class="btn-group">
                                          <a href="<?php echo URLROOT;?>/contributions/edit/<?php echo $contribution->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
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
      $('#contributionsTable').DataTable({
        'pageLength': 25,
        ordering : false,
        'columnDefs' : [
            {"visible" : false, "targets": 0}
          ]
      });

      $('#contributionsTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#date').val(data[0]);
          $('#contributor').val(data[4]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#contributionsTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
      $('#contributionsTable').on('click','.btnapprove',function(){
        $('#approveModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#adate').val(data[0]);
          $('#acontributor').val(data[4]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#contributionsTable').DataTable().row(currentRow).data();
          $('#aid').val(data1[0]);
      });
    });
</script>
</body>
</html>