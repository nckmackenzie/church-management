<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Supplier</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/suppliers/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected supplier?</label>
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
        <?php flash('supplier_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/suppliers/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="suppliersTable">
                    <thead class="bg-navy">
                        <th class="d-none">ID</th>
                        <th>Supplier Name</th>
                        <th>Contact</th>
                        <th>Contact Person</th>
                        <th>Current Balance</th>
                        <th>Actions</th>
                    </thead>
                    <tbody>
                        <?php foreach($data['suppliers'] as $supplier) :?>
                            <tr>
                                <td class="d-none"><?php echo $supplier->ID;?></td>
                                <td><?php echo strtoupper($supplier->supplierName);?></td>
                                <td><?php echo $supplier->contact;?></td>
                                <td><?php echo $supplier->contactPerson;?></td>
                                <td><?php echo $supplier->balance;?></td>
                                <td>
                                    <?php if($_SESSION['userType'] <=2) : ?>
                                        <div class="btn-group">
                                            <a href="<?php echo URLROOT;?>/suppliers/edit/<?php echo $supplier->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                            <button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
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
      $('#suppliersTable').DataTable({
          'ordering' : false,
          'columnDefs' : [
            {"width" : "10%" , "targets": 5},
          ]
      });

      $('#suppliersTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#id').val(data[0]);
          $('#suppliername').val(data[1]);
      });
    });
</script>
</body>
</html>