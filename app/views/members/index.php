<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Member</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/members/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Member?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="membername" id="membername">
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
<div class="modal fade" id="resendModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Resend Update Link</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/members/resend" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Resend Link To Selected Member?</label>
                  <input type="hidden" name="id" id="rid">
                  <input type="hidden" name="contact" id="contact">
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
      <?php flash('member_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/members/add" class="btn btn-success btn-sm custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table id="membersTable" class="table table-bordered table-striped table-sm">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Member Name</th>
                            <th>Contact</th>
                            <th>Gender</th>
                            <th>District</th>
                            <th>Position</th>
                        <?php if ($_SESSION['userType'] <=2 ) : ?>
                            <th>Actions</th>
                        <?php endif; ?>    
                        </tr>
                    </thead>
                    <tbody>
                    
                            <?php foreach($data['members'] as $member) : ?>
                                <tr>
                                    <td><?php echo $member->ID;?></td> 
                                    <td><?php echo $member->memberName;?></td> 
                                    <td><?php echo $member->contact;?></td> 
                                    <td><?php echo $member->gender;?></td> 
                                    <td><?php echo $member->district;?></td> 
                                    <td><?php echo $member->position;?></td>
                                    <?php if ($_SESSION['userType'] <=2 ) : ?>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm bg-gray-dark custom-font resend">Resend Update Link</button>
                                                <a href="<?php echo URLROOT;?>/members/edit/<?php echo $member->ID;?>" class="btn bg-olive custom-font btn-sm">Edit</a>
                                                <button class="btn btn-danger custom-font btn-sm btndel">Delete</button>
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
      var table = $('#membersTable').DataTable({
          'pageLength' : 25,
          'ordering' : false,
          "responsive": true,
          'columnDefs' : [
            { "visible" : false, "targets": 0},
            {"width" : "5%" , "targets": 2},
            {"width" : "10%" , "targets": 3}
            <?php if ($_SESSION['userType'] <=2) : ?>
            ,{"width" : "25%" , "targets": 6},
            <?php endif;?>
          ]
      });

      $('#membersTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          
          $('#membername').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#membersTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
      $('#membersTable').on('click','.resend',function(){
          $('#resendModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          
          $('#contact').val(data[1]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#membersTable').DataTable().row(currentRow).data();
          $('#rid').val(data1[0]);
      });

    });
</script>
</body>
</html>    