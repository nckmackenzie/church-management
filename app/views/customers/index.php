<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Customer</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/customers/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Customer?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="customername" id="customername">
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
        <?php flash('customer_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/customers/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="customersTable">
                    <thead class="bg-navy">
                        <th style="display: none;">ID</th>
                        <th>Customer Name</th>
                        <th>Contact</th>
                        <th>PIN</th>
                        <th>Contact Person</th>
                        <?php if($_SESSION['userType'] <=2) : ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php foreach($data['customers'] as $customer) :?>
                            <tr>
                                <td style="display: none;"><?php echo $customer->ID;?></td>
                                <td><?php echo strtoupper($customer->customerName);?></td>
                                <td><?php echo $customer->contact;?></td>
                                <td><?php echo strtoupper($customer->pin);?></td>
                                <td><?php echo strtoupper($customer->contactPerson);?></td>
                                <?php if($_SESSION['userType'] <=2) : ?>
                                <td>
                                    <div class="btn-group">
                                        <a href="<?php echo URLROOT;?>/customers/edit/<?php echo $customer->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
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
      $('#customersTable').DataTable({
          'ordering' : false,
          'columnDefs' : [
            {"width" : "10%" , "targets": 2},
            {"width" : "10%" , "targets": 3}
            <?php if ($_SESSION['userType'] <=2) : ?>
              ,{"width" : "10%" , "targets": 5},
            <?php endif;?>
          ]
      });

      $('#customersTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#id').val(data[0]);
          $('#customername').val(data[1]);
      });
    });
</script>
</body>
</html>