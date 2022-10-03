<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Budget</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/groupbudgets/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Budget?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="year" id="budgetyear">
                  <input type="hidden" name="groupname" id="groupname">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger btn-sm">Yes</button>
              </div>
          </form>
      </div>
     
    </div>
  </div>
</div>
<!-- Modal -->
  <!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('budget_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/groupbudgets/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="budgetsTable">
                    <thead class="bg-navy">
                        <th>ID</th>
                        <th>Year</th>
                        <th>Group</th>
                        <th>Total Budgeted Amount</th>
                        <?php if($_SESSION['userType'] <=2 ) : ?>
                            <th>Action</th>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php foreach($data['budgets'] as $budget) :?>
                            <tr>
                                <td><?php echo $budget->ID;?></td>
                                <td><?php echo $budget->yearName;?></td>
                                <td><?php echo $budget->groupName;?></td>
                                <td><?php echo $budget->BudgetAmount;?></td>
                                <?php if($_SESSION['userType'] <=2) : ?>
                                    <td>
                                        <div class="btn-group">
                                        <a href="<?php echo URLROOT;?>/groupbudgets/edit/<?php echo encryptId($budget->ID);?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                            <button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
                                        </div>
                                    </td>
                                <?php endif; ?>
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
      $('#budgetsTable').DataTable({
        'pageLength': 25,
        'columnDefs' : [
            {"visible" : false, "targets": 0},
            // {"width" : "10%" , "targets": 1},
            // {"width" : "10%" , "targets": 3},
            // {"width" : "10%" , "targets": 4},
            // {"width" : "10%" , "targets": 6},
          ]
      });

      $('#budgetsTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#budgetyear').val(data[0]);
          $('#groupname').val(data[1]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#budgetsTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
    });
</script>
</body>
</html>