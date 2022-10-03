<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Congregation</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/congregations/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Congregation?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="congregationname" id="congregationname">
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
        <?php flash('congregation_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/congregations/add" class="btn btn-sm btn-success custom-font">Add New</a>
            <input type="hidden" name="type" id="usertype" value="<?php echo $_SESSION['userType'];?>">
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="congregationsTable">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Congregation Name</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>email</th>
                            <?php if ($_SESSION['userType'] <=2) : ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['congregations'] as $congregation) :?>
                            <tr>
                                <td><?php echo $congregation->ID;?></td>
                                <td><?php echo strtoupper($congregation->CongregationName);?></td>
                                <td><?php echo $congregation->contact;?></td>
                                <td><?php echo strtoupper($congregation->Address);?></td>
                                <td><?php echo $congregation->email;?></td>
                                <?php if($_SESSION['userType'] <=2) : ?>
                                  <td>
                                      <div class="btn-group">
                                          <a href="<?php echo URLROOT;?>/congregations/edit/<?php echo $congregation->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
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
      $('#congregationsTable').DataTable({
        'columnDefs' : [
            {"visible" : false, "targets": 0},
            {"width" : "10%" , "targets": 2}
            <?php if($_SESSION['userType'] <=2) : ?>
            ,{"width" : "15%" , "targets": 5},
            <?php endif; ?>
        ]
      });

      $('#congregationsTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#congregationname').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#congregationsTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
    });
</script>
</body>
</html>