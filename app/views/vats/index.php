<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete V.A.T</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/vats/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected V.A.T?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="vatname" id="vatname">
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
        <?php flash('vat_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/vats/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="vatsTable">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>VAT Name</th>
                            <th>Rate</th>
                            <th>State</th>
                            <?php if ($_SESSION['userType'] <=2) : ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['vats'] as $vat) :?>
                            <tr>
                                <td><?php echo $vat->ID;?></td>
                                <td><?php echo $vat->vatName;?></td>
                                <td><?php echo ($vat->rate) / 100;?></td>
                                <?php if($vat->active == 1) : ?>
                                    <td class="text-success">Active</td>
                                <?php else : ?>
                                    <td class="text-danger">Inactive</td>
                                <?php endif; ?>
                                <?php if($_SESSION['userType'] <=2) : ?>
                                  <td>
                                      <div class="btn-group">
                                          <a href="<?php echo URLROOT;?>/vats/edit/<?php echo encryptId($vat->ID);?>" class="btn btn-sm bg-olive custom-font">Edit</a>
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
      $('#vatsTable').DataTable({
        'columnDefs' : [
            {"visible" : false, "targets": 0}
            <?php if ($_SESSION['userType'] <=2) : ?>
            ,{"width" : "15%" , "targets": 3},
            <?php endif;?>
          ]
      });

      $('#vatsTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#vatname').val(data[0]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#vatsTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
    });
</script>
</body>
</html>