<?php $h=function($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');};require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Pledge</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/pledges/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label>Delete selected pledge and void all its ledger entries?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="pledger" id="pledger">
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
<div class="modal fade" id="reminderModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Send Reminder</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/pledges/sendreminder" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Send Reminders To All?</label>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Yes</button>
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
        <?php flash('pledge_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/pledges/add" class="btn btn-sm btn-success custom-font">Add New</a>
            <button class="btn btn-sm btn-primary custom-font btnsend">Send Reminder</button>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="pledgesTable">
                    <thead class="bg-navy">
                        <th>ID</th>
                        <th>Pledged By</th>
                        <th>Contact</th>
                        <th>Category</th>
                        <th>Amount Pledged</th>
                        <th>Amount Paid</th>
                        <th>Balance</th>
                        <th>Action</th>
                    </thead>
                    <tbody>
                        <?php foreach($data['pledges'] as $pledge) :?>
                            <tr>
                                <td><?php echo (int)$pledge->ID;?></td>
                                <td><?php echo $h($pledge->pledger);?></td>
                                <td><?php echo $h($pledge->contact);?></td>
                                <td><?php echo $h($pledge->category);?></td>
                                <td><?php echo $h($pledge->amountPledged);?></td>
                                <td><?php echo $h($pledge->amountPaid);?></td>
                                <td><?php echo $h($pledge->balance);?></td>
                                <td><div class="btn-group"><a href="<?php echo URLROOT;?>/pledges/pay/<?php echo (int)$pledge->ID;?>" class="btn btn-sm bg-olive custom-font">Pay</a><?php if((int)$_SESSION['userType'] <= 2 || (int)$_SESSION['userType'] > 4) : ?><button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button><?php endif; ?></div></td>
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
      $('#pledgesTable').DataTable({
        'pageLength': 25,
        'ordering' : false,
        'columnDefs' : [
            {"visible" : false, "targets": 0},
            {"width" : "10%" , "targets": 2},
            {"width" : "10%" , "targets": 3},
            {"width" : "15%" , "targets": 4},
            {"width" : "15%" , "targets": 5},
            {"width" : "15%" , "targets": 6},
            {"width" : "15%" , "targets": 7},
          ]
      });

      $('#pledgesTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#pledger').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#pledgesTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
      $('.btnsend').on('click',function(){
        $('#reminderModalCenter').modal('show');
      
         
      });
    });
</script>
</body>
</html>
