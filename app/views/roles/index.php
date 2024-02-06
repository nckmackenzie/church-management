<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Role</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/roles/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected role?</label>
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
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('role_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/roles/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto table-responsive">
                <table class="table table-striped table-bordered table-sm" id="roleTable">
                    <thead class="bg-navy">
                        <th class="d-none">ID</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </thead>
                    <tbody>
                        <?php foreach($data['roles'] as $role) :?>
                            <tr>
                                <td class="d-none"><?php echo $role->ID;?></td>
                                <td><?php echo $role->RoleName;?></td>
                                <td>
                                    <?php if($_SESSION['userType'] <=2) : ?>
                                        <div class="btn-group">
                                          <button type="button" data-id="<?php echo $role->ID;?>" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
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
      $('#roleTable').DataTable({
          'ordering' : false,
          'columnDefs' : [{"width" : "20%" , "targets": 2}]
      });

      $('#roleTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $('#id').val($(this).data('id'));
      });
    });
</script>
</body>
</html>