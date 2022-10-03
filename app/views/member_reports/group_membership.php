<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
          <div class="row">
              <div class="col-md-6 mx-auto mt-1">
                  <div class="card bg-light">
                      <div class="card-body">
                          <div class="row">
                              <div class="col-md-6 mx-auto">
                                  <div class="form-group">
                                      <label for="groups">Group</label>
                                      <select name="groups" id="groups" class="form-control form-control-sm">
                                            <option value="0">All Groups</option>
                                            <?php foreach($data['groups'] as $group) :?>
                                                <option value="<?php echo $group->ID;?>">
                                                    <?php echo $group->groupName; ?>
                                                </option>
                                            <?php endforeach; ?>    
                                      </select>
                                  </div>
                              </div>
                          </div>
                          <div class="row">
                              <div class="col-md-6 mx-auto">
                                  <button id="preview" class="btn btn-sm btn-primary custom-font">Preview</button>
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
            <div class="col-md-12 table-responsive">
                <div id="results">

                </div>
            </div>
        </div>                                       
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function (){
        $('#groups').select2();
        $('#preview').click(function(){
            var table = $('#table').DataTable();
            var group = $('#groups').val();

            $.ajax({
                url : '<?php echo URLROOT;?>/member_reports/groupmembership',
                method : 'POST',
                data : {group : group},
                success : function(html){
                    $('#results').html(html);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 25,
                        fixedHeader : true,
                        'columnDefs' : [
                            {"width" : "10%" , "targets": 1},
                            {"width" : "10%" , "targets": 3},
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