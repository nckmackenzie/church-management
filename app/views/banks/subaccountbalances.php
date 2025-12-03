<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Opening Balance</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/banks/deletebalanceentry" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Entry?</label>
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
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('subaccountbal_msg');?>
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
                            <th>Transaction Date</th>
                            <th>Sub Account Name</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['entries'] as $entry) :?>
                            <tr>
                                <td><?php echo $entry->ID;?></td>
                                <td><?php echo $entry->TransactionDate;?></td>
                                <td><?php echo $entry->AccountName;?></td>
                                <td><?php echo $entry->Amount;?></td>
                                <td>
                                    <?php if($_SESSION['userType'] <=2) : ?>
                                      <div class="btn-group">
                                          <a href="<?php echo URLROOT;?>/banks/editbalance/<?php echo $entry->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
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
      $('#banksTable').DataTable({
        'columnDefs' : [
            {"visible" : false, "targets": 0}
            ,{"width" : "15%" , "targets": 4},
         ],
         ordering: false
      });

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
    });
</script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/bank/balance.js"></script>
</body>
</html>