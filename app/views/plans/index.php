<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Plan</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/plans/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Plan?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="planname" id="planname">
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
      <?php flash('plan_msg'); ?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/plans/add" class="btn btn-success btn-sm custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table id="plansTable" class="table table-bordered table-striped table-sm">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Plan Name</th>
                            <th>Level</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['plans'] as $plan) : ?>
                            <tr>
                                <td><?php echo $plan->ID;?></td> 
                                <td><?php echo strtoupper($plan->WorkPlanName);?></td> 
                                <td><?php echo $plan->Level;?></td> 
                                <td><?php echo $plan->yearName;?></td>
                                <?php if($plan->status == 'Unsubmitted') : ?>
                                    <td><span class="badge bg-warning">Unsubmitted</span></td>
                                <?php else : ?>
                                    <td><span class="badge bg-success">Submitted</span></td>    
                                <?php endif; ?>    
                                <td>
                                    <div class="btn-group">
                                        <a href="<?php echo URLROOT;?>/plans/edit/<?php echo encryptId($plan->ID);?>" class="btn bg-olive custom-font btn-sm">Edit</a>
                                        <button class="btn btn-danger custom-font btn-sm btndel">Delete</button>
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
      var table = $('#plansTable').DataTable({
          'pageLength' : 25,
          'ordering' : false,
          "responsive": true,
          'columnDefs' : [
            { "visible" : false, "targets": 0},
            {"width" : "10%" , "targets": 4},
            {"width" : "15%" , "targets": 5},
          ]
      });

      $('#plansTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          
          $('#planname').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#plansTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
    });
</script>
</body>
</html>    