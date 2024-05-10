<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
       <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mx-auto">
                                <div class="form-group">
                                    <label for="district">District</label>
                                    <select name="district" id="district" 
                                            class="form-control form-control-sm select2">
                                        <option value="0">All Districts</option>
                                        <?php foreach($data['districts'] as $district) :?>
                                            <option value="<?php echo $district->ID;?>">
                                                <?php echo $district->districtName;?>
                                            </option>
                                        <?php endforeach; ?>    
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mx-auto">
                                <button class="btn btn-sm btn-primary custom-font" id="preview">Preview</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
        $('.select2').select2();
        //preview
        $('#preview').click(function(){
            var table = $('#table').DataTable();
            var district = $('#district').val();
            $.ajax({
                url : '<?php echo URLROOT;?>/reports/residencereport',
                method : 'POST',
                data : {district : district},
                success : function(html){
                    $('#results').html(html);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        ordering : false,
                        fixedHeader : true,
                        'columnDefs' : [
                            {"width" : "15%" , "targets": 1},
                            {"width" : "10%" , "targets": 2},
                            {"width" : "15%" , "targets": 5},
                            
                        ],
                        // "buttons": ["excel", "pdf","print"]
                        buttons: [
                            { extend: 'excelHtml5', footer: true },
                            { extend: 'pdfHtml5', footer: true },
                            "print"
                        ],
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });
        });
    });
</script>
</body>
</html>  