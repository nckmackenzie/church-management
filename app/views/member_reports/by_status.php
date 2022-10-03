<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-6 mx-auto mt-2">
              <div class="card bg-light">
                  <div class="card-body">
                      <div class="row">
                          <div class="col-md-6">
                              <div class="form-group">
                                    <label for="status">Select Criteria</label>
                                    <select name="status" id="status" class="form-control form-control-sm">
                                        <option value="0">All</option>
                                        <option value="1">Full</option>
                                        <option value="2">Adherent</option>
                                        <option value="3">Associate</option>
                                        <option value="4">Under-12</option>
                                    </select>
                              </div>
                          </div>
                          <div class="col-md-6">
                              <div class="form-group">
                                    <label for="district">District</label>
                                    <select name="district" id="district" class="form-control form-control-sm select2">
                                        <option value="0">All Districts</option>
                                        <?php foreach($data['districts'] as $district) : ?>
                                            <option value="<?php echo $district->ID;?>">
                                                <?php echo $district->districtName;?>
                                            </option>
                                        <?php endforeach;?>    
                                    </select>
                              </div>
                          </div>
                      </div>
                      <div class="row">
                            <div class="col-2">
                                <button class="btn btn-sm btn-primary custom-font" id="preview">Preview</button>
                            </div>
                      </div>
                  </div>
              </div>  
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div id="results" class="table-responsive">

                </div>
            </div>
        </div>                                    
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('.select2').select2();

        $('#preview').click(function(){
            var table = $('#table').DataTable();
            var status = $('#status').val();
            var district = $('#district').val();
            $.ajax({
                url : '<?php echo URLROOT;?>/member_reports/bystatusrpt',
                method : 'POST',
                data : {status : status, district : district},
                success : function(html){
                    $('#results').html(html);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 25,
                        fixedHeader : true,
                        'columnDefs' : [
                            {"width" : "10%" , "targets": 1},
                            {"width" : "10%" , "targets": 2},
                            {"width" : "15%" , "targets": 3},
                        ],
                        "buttons": ["excel", "pdf","print"]
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });
        });
    });
</script>
</body>
</html>  