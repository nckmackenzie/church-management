<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Reverse approval</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/groupfunds/reverse" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To reverse this approval?</label>
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
<div class="modal fade" id="rejectModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Reject requisition</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/groupfunds/reject" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Reason for rejection</label>
                  <input type="text" class="form-control form-control-sm mb-3" name="reason">
                  <input type="hidden" name="id" id="rid">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Reject</button>
              </div>
          </form>
      </div>
     
    </div>
  </div>
</div>
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('approval_msg');?>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="reqTable">
                    <thead class="bg-navy">
                        <th class="d-none">ID</th>
                        <th>Req No</th>
                        <th>Req Date</th>
                        <th>Group Name</th>
                        <th>Amount Requested</th>
                        <th>Amount Approved</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </thead>
                    <tbody>
                        <?php foreach($data['approvals'] as $approval) :?>
                            <tr>
                                <td class="d-none"><?php echo $approval->ID;?></td>
                                <td><?php echo $approval->ReqNo;?></td>
                                <td><?php echo date('d-M-Y',strtotime($approval->ReqDate));?></td>
                                <td><?php echo $approval->GroupName;?></td>
                                <td><?php echo $approval->AmountRequested;?></td>
                                <td><?php echo $approval->AmountApproved;?></td>
                                <td><span class="badge badge-<?php badgeclasses($approval->Status);?>"><?php echo $approval->State;?></span></td>
                                <td>
                                    <?php if($_SESSION['userType'] <=2 || $_SESSION['userType'] == 5) : ?>
                                        <div class="btn-group">
                                            <?php if((int)$approval->Status === 0) : ?>
                                                <a href="<?php echo URLROOT;?>/groupfunds/approve/<?php echo $approval->ID;?>" class="btn btn-sm bg-olive custom-font">Approve</a>
                                                <button type="button" class="btn btn-sm btn-danger custom-font btnreject">Reject</button>
                                            <?php else: ?>
                                                <?php if((int)$approval->DiffInDate <= 1) : ?>    
                                                  <button type="button" class="btn btn-sm btn-warning custom-font btndel">Reverse</button>
                                                <?php endif;?>
                                            <?php endif;?>
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
      $('#reqTable').DataTable({
          'ordering' : false,
          'columnDefs' : [
            {"width" : "10%" , "targets": 1},
            {"width" : "10%" , "targets": 2},
            {"width" : "15%" , "targets": 7},
          ]
      });

      $('#reqTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#id').val(data[0]);
      });

      $('#reqTable').on('click','.btnreject',function(){
          $('#rejectModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#rid').val(data[0]);
      });
    });
</script>
</body>
</html>