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
          <form action="<?php echo URLROOT;?>/members/family_delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Member family?</label>
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
      <?php flash('member_family_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/members/family_add" class="btn btn-success btn-sm custom-font">Add New</a>
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
                            <th>Members Added</th>
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
                                    <td><?php echo $member->familyCount;?></td> 
                                    <?php if ($_SESSION['userType'] <=2 ) : ?>
                                        <td>
                                            <div class="btn-group">
                                                <a href="<?php echo URLROOT;?>/members/family_edit/<?php echo $member->ID;?>" class="btn bg-olive custom-font btn-sm">Edit</a>
                                                <button class="btn btn-danger custom-font btn-sm btndel" data-id="<?php echo $member->ID;?>">Delete</button>
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
            {"width" : "25%" , "targets": 3},
          ],
      });
      
      $('#membersTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $('#id').val($(this).data('id'));
      });

    });
</script>
</body>
</html>    