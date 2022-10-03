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
                          <div class="col-md-9">
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
            var groupColumn = 0;
            var table = $('#table').DataTable();
            var district = $('#district').val();
            $.ajax({
                url : '<?php echo URLROOT;?>/member_reports/familyreport',
                method : 'POST',
                data : {district : district},
                success : function(html){
                    $('#results').html(html);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 25,
                        fixedHeader : true,
                        'columnDefs' : [
                            {'visible' : false, "targets": groupColumn}
                        ],
                        "drawCallback": function ( settings ) {
                            var api = this.api();
                            var rows = api.rows( {page:'current'} ).nodes();
                            var last=null;
                
                            api.column(groupColumn, {page:'current'} ).data().each( function ( group, i ) {
                                if ( last !== group ) {
                                    $(rows).eq( i ).before(
                                        '<tr class="group bg-info text-light"><td colspan="3">'+group+'</td></tr>'
                                    );
                                    last = group;
                                }
                            } );
                        },
                        "order": [[ groupColumn, 'asc' ]],
                        "buttons": ["excel", "pdf","print"]
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });
        });
    });
</script>
</body>
</html>  