<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="card card-body bg-light">
          <div class="row mb-2">
            <div class="col-sm-4">
                <label for="user">User</label>
                <select name="user" id="user" class="form-control form-control-sm">
                    <?php foreach($data['users'] as $user) : ?>
                        <option value="<?php echo $user->ID;?>"><?php echo $user->UserName;?></option>
                    <?php endforeach; ?>  
                </select>
            </div>
            <div class="col-sm-4">
                <label for="start">Start Date</label>
                <input type="date" name="start" id="start" class="form-control form-control-sm">
                <span class="invalid-feedback" id="start_err"></span>
            </div>
            <div class="col-sm-4">
                <label for="end">End Date</label>
                <input type="date" name="end" id="end" class="form-control form-control-sm">
                <span class="invalid-feedback" id="end_err"></span>
            </div>
          </div>
          <div class="row">
            <div class="col-2">
              <button id="search" class="btn btn-sm btn-success">Search</button>
            </div>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-md-12 table-responsive">
            <div id="results">

            </div>
        </div>
      </div>              
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
  $(function(){
    $('#user').select2();
    var table = $('#table').DataTable();

    $('#search').click(function(){
      var user = $('#user').val();
      var start;
      var end;
        if ($('#start').val() == '') {
          $('#start').addClass('is-invalid');
          $('#start_err').text('Start Date Required');
        }
        else{
          $('#start').removeClass('is-invalid');
          start = $('#start').val();
        }

        if ($('#end').val() == '') {
          $('#end').addClass('is-invalid');
          $('#end_err').text('End Date Required');
        }
        else{
          $('#end').removeClass('is-invalid');
          end = $('#end').val();
        }
        if ($('#start').val() == '' || $('#end').val() == '') {
          return
        }

        $.ajax({
            url : '<?php echo URLROOT;?>/users/activityresult',
            method : 'POST',
            data : {user : user,start : start, end : end},
            success : function(html){
              $('#results').html(html);
                table.destroy();
                table = $('#table').DataTable({
                    'pageLength' : 25,
                    "buttons": ["excel", "pdf", "print"]
                }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
            }
        });
    });
  });
</script>
</body>
</html> 