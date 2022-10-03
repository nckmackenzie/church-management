<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Service</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/services/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Service?</label>
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
      <div class="container-fluid">
        <?php flash('service_added');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/services/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <table class="table table-striped table-bordered table-sm" id="servicesTable">
                    <thead class="bg-navy">
                        <th style="display: none;">ID</th>
                        <th>Service Name</th>
                        <th>Service Time</th>
                        <?php if($_SESSION['userType'] <=2) : ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                      <?php if($_SESSION['userType'] <=2) : ?>  
                          <?php foreach($data['services'] as $service) :?>
                              <tr>
                                  <td style="display: none;"><?php echo $service->ID;?></td>
                                  <td><?php echo strtoupper($service->serviceName);?></td>
                                  <td><?php echo strtoupper($service->serviceTime);?></td>
                                  <?php if($_SESSION['userType'] <=2) : ?>
                                  <td>
                                      <div class="btn-group">
                                          <a href="<?php echo URLROOT;?>/services/edit/<?php echo $service->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                          <button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
                                      </div>
                                  </td>     
                                  <?php endif; ?>
                              </tr>
                          <?php endforeach; ?>
                      <?php endif; ?>  
                    </tbody>
                </table>
            </div>    
        </div>        
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->



<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
      $('#servicesTable').DataTable({
          'ordering' : false,
          'columnDefs' : [
            {"width" : "5%" , "targets": 3},
          ]
      });

      $('#servicesTable').on('click','.btndel',function(){
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