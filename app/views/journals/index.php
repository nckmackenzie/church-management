<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Contribution</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/contributions/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Delete Selected Contribution?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="date" id="date">
                  <input type="hidden" name="contributor" id="contributor">
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
<!-- Modal -->
 <!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('journal_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/journals/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php require APPROOT . '/views/inc/footer.php'?>
<!-- <script>
    $(function(){
      $('#contributionsTable').DataTable({
        'pageLength': 25,
        'columnDefs' : [
            {"visible" : false, "targets": 0},
            {"width" : "10%" , "targets": 1},
            {"width" : "10%" , "targets": 3},
            {"width" : "10%" , "targets": 4},
            {"width" : "10%" , "targets": 6},
          ]
      });

      $('#contributionsTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#date').val(data[0]);
          $('#contributor').val(data[4]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#contributionsTable').DataTable().row(currentRow).data();
          $('#id').val(data1[0]);
      });
      $('#contributionsTable').on('click','.btnapprove',function(){
        $('#approveModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#adate').val(data[0]);
          $('#acontributor').val(data[4]);
          var currentRow = $(this).closest("tr");
          var data1 = $('#contributionsTable').DataTable().row(currentRow).data();
          $('#aid').val(data1[0]);
      });
    });
</script> -->
</body>
</html>