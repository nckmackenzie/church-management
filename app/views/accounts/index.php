<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<?php $h = function ($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }; ?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Account</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/accounts/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Account?</label>
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
        <?php flash('account_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/accounts/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="accountsTable">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Account Name</th>
                            <th>Account Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['accounts'] as $account) :?>
                            <tr class="<?php echo !$account->active ? 'text-danger' : '';?>">
                                <td><?php echo (int)$account->ID;?></td>
                                <td class="sub-level-<?php echo (int)$account->depth;?>"><?php echo $h(strtoupper($account->accountType));?></td>
                                <td><?php echo $h($account->typeName);?></td>
                                <td>
                                    <?php if($account->canEdit) : ?>
                                        <a href="<?php echo URLROOT;?>/accounts/edit/<?php echo (int)$account->ID;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                    <?php endif;?>
                                    <?php if($account->canDelete) : ?>
                                        <button type="button" class="btn btn-sm btn-danger custom-font btndel">Delete</button>
                                    <?php endif;?>
                                    <?php if($account->note !== '') : ?>
                                        <small class="text-muted"><?php echo $h($account->note);?></small>
                                    <?php endif;?>
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
      $('#accountsTable').DataTable({
        'pageLength' : 100,
        'ordering': false,
        'columnDefs' : [
            {"visible" : false, "targets": 0},
            {"width" : "15%" , "targets": 2},
            {"width" : "20%" , "targets": 3}
          ]
      });

      $('#accountsTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          var data1 = $('#accountsTable').DataTable().row($(this).closest("tr")).data();
          $('#id').val(data1[0]);
      });
    });
</script>
</body>
</html>
