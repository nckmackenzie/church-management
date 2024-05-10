<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select name="status" id="status" class="form-control form-control-sm">
                                        <option value="1">Active</option>
                                        <option value="2">Inactive</option>
                                        <option value="3">By Age</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label for="district">District</label>
                                    <select name="district[]" id="district" class="form-control form-control-sm select2">
                                        <option value="0">All Districts</option>
                                        <?php foreach($data['districts'] as $district) : ?>
                                            <option value="<?php echo $district->ID;?>">
                                                <?php echo $district->districtName;?>
                                            </option>
                                        <?php endforeach;?>    
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="from">From</label>           
                                    <input type="number" name="from" id="from" class="form-control form-control-sm" disabled>        
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="to">To</label>           
                                    <input type="number" name="to" id="to" class="form-control form-control-sm" disabled>
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

        // var table = $('#table').DataTable();
        $('#status').on('change',function(){
            var status = $(this).val();
            if (Number(status) === 3) {
                $('#from').attr('disabled',false);
                $('#to').attr('disabled',false);
            }else{
                $('#from').attr('disabled',true);
                $('#to').attr('disabled',true);
                $('#from').val('');
                $('#to').val('');
            }
        });

        $('#preview').click(function(){
            var table = $('#table').DataTable();
            var district = $('#district').val();
            var status = $('#status').val();
            var from = $('#from').val();
            var to = $('#to').val();

            if (Number(status) === 3) {
                if ($('#from').val() == '') {
                    alert('Select From Age');
                    return
                }
                if ($('#to').val() == '') {
                    alert('Select To Age');
                    return
                }
                if (Number(from) > Number(to)) {
                    alert('Start Age Cannot Be Greater Than End');
                    return
                }
            }

            $.ajax({
                url : '<?php echo URLROOT;?>/reports/memberreport',
                method : 'POST',
                data : {district : district, status : status , from : from ,to : to},
                success : function(html){
                    // console.log(html);
                    $('#results').html(html);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        'columnDefs' : [
                            {"width" : "10%" , "targets": 2},
                            {"width" : "10%" , "targets": 3},
                            {"width" : "15%" , "targets": 1},
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