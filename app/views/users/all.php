<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Reset Password</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/users/reset" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Reset Password For Selected User?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="contact" id="contact">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-success">Yes</button>
              </div>
          </form>
      </div>
     
    </div>
  </div>
</div>
<div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row mt-2">
            <div class="col-4">
                <a href="<?php echo URLROOT;?>/users/register" class="btn btn-sm btn-success custom-font">Add New</a>
            </div>
        </div><!--end of row -->
        <div class="row mt-2">
            <div class="col-md-12 table-responsive">
                <table id="usersTable" class="table table-sm table-striped table-bordered">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>User ID</th>
                            <th>User Name</th>
                            <th>User Type</th>
                            <th>Status</th>
                            <th>Contact</th>
                            <th>District</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['users'] as $user) : ?>
                            <tr>
                                <td><?php echo $user->ID;?></td>
                                <td><?php echo $user->userId;?></td>
                                <td><?php echo $user->username;?></td>
                                <td><?php echo $user->usertype;?></td>
                                <td><?php echo $user->status;?></td>
                                <td><?php echo $user->contact;?></td>
                                <td><?php echo $user->district;?></td>
                                <td>
                                   <div class="btn-group">
                                        <a href="<?php echo URLROOT;?>/users/edit/<?php echo encryptId($user->ID);?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                        <button type="button" class="btn btn-secondary btn-sm reset custom-font">Password Reset</button>
                                   </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section><!--End Main Content -->
</div><!--End Of Wrapper-->
<?php require APPROOT . '/views/inc/footer.php'?>

<script>
    $(function(){
        $('#usersTable').DataTable({
            "columnDefs" : [
                {"visible" : false,"targets": 0 },
                // {"visible" : false,"targets": 4 },
                {"width" : "15%" , "targets": 3},
                {"width" : "5%" , "targets": 4},
                {"width" : "10%" , "targets": 5},
                {"width" : "15%" , "targets": 6},
                {"width" : "20%" , "targets": 7},
            ],
            "responsive": true,
        });
        $('#usersTable').on('click','.reset',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#contact').val(data[4]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#usersTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
    });
</script>
</body>
</html>    