<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Year</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/years/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Year?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="yearname" id="yearname">
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
<div class="modal fade" id="closeModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Close Year</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/years/close" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Close Selected Year?</label>
                  <input type="hidden" name="id" id="cid">
                  <input type="hidden" name="yearname" id="cyearname">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-dark">Yes</button>
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
      <?php flash('year_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/years/add" class="btn btn-success btn-sm custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table id="yearsTable" class="table table-bordered table-striped table-sm">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Year Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Open</th>
                        <?php if ($_SESSION['userType'] <=2 ) : ?>
                            <th>Actions</th>
                        <?php endif; ?>    
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['years'] as $year) : ?>
                            <tr>
                                <td><?php echo $year->ID;?></td> 
                                <td><?php echo strtoupper($year->yearName);?></td> 
                                <td><?php echo $year->startDate;?></td> 
                                <td><?php echo $year->endDate;?></td>
                                <?php if($year->closed == 0) : ?>
                                    <td><span class="badge bg-success">Yes</span></td>
                                <?php else : ?>
                                    <td><span class="badge bg-danger">No</span></td>    
                                <?php endif; ?>    
                                <?php if ($_SESSION['userType'] <=2 ) : ?>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?php echo URLROOT;?>/years/edit/<?php echo encryptId($year->ID);?>" class="btn bg-olive custom-font btn-sm">Edit</a>
                                            <button class="btn btn-danger custom-font btn-sm btndel">Delete</button>
                                            <?php if($year->closed == 0) : ?>
                                                <button class="btn btn-sm bg-gray-dark custom-font resend">Close</button>
                                            <?php endif; ?>
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
      var table = $('#yearsTable').DataTable({
          'pageLength' : 25,
          'ordering' : false,
          "responsive": true,
          'columnDefs' : [
            { "visible" : false, "targets": 0},
            {"width" : "15%" , "targets": 2},
            {"width" : "15%" , "targets": 3},
            {"width" : "7%" , "targets": 4}
            <?php if ($_SESSION['userType'] <= 2) : ?>
                ,{"width" : "15%" , "targets": 5},
            <?php endif;?>
          ]
      });

      $('#yearsTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          
          $('#yearname').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#yearsTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
      $('#yearsTable').on('click','.resend',function(){
          $('#closeModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          
          $('#cyearname').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#yearsTable').DataTable().row(currentRow).data();
          $('#cid').val(data1[0]);
      });

    });
</script>
</body>
</html>    