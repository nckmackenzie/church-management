<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Service Info</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/services/deleteinfo" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Service Information?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="servicename" id="servicename">
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
      <?php flash('serviceinfo_msg');?>
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
                <a href="<?php echo URLROOT;?>/services/add_service_info" class="btn btn-sm btn-success custom-font">Add New</a>  
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="servicesInfoTable">
                    <thead class="bg-navy">
                        <tr>
                            <th style="display: none;">ID</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Headed By</th>
                            <th>Preacher</th>
                            <th>Attendance</th>
                            <?php if($_SESSION['userType'] <=2 ) :?>
                                <th>Action</th>
                            <?php endif; ?>    
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['servicesinfo'] as $serviceinfo) :?>
                            <tr>
                                <td style="display: none;"><?php echo $serviceinfo->ID;?></td>
                                <td><?php echo $serviceinfo->serviceName;?></td>
                                <td><?php echo $serviceinfo->serviceDate;?></td>
                                <td><?php echo $serviceinfo->headedBy;?></td>
                                <td><?php echo $serviceinfo->preacher;?></td>
                                <td><?php echo $serviceinfo->attendance;?></td>
                                <?php if($_SESSION['userType'] <=2 ) :?>
                                    <th>
                                        <div class="btn-group">
                                            <a href="<?php echo URLROOT;?>/services/edit_service_info/<?php echo $serviceinfo->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                            <button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
                                        </div>
                                    </th>
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
        $('#servicesInfoTable').DataTable({
            'ordering' : false,
            'columnDefs' : [
                {"width" : "20%" , "targets": 1},
                {"width" : "10%" , "targets": 2},
                {"width" : "10%" , "targets": 5},
                {"width" : "10%" , "targets": 6},
            ]
        });
        $('#servicesInfoTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#id').val(data[0]);
          $('#servicename').val(data[1]);
      });
    });
</script>
</body>
</html> 