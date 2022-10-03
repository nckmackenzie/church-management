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
                    <div class="card-header">Transfered Between</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="from">From</label>
                                    <input type="date" name="from" id="from"
                                           class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="from_err"></span>       
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="to">To</label>
                                    <input type="date" name="to" id="to"
                                           class="form-control form-control-sm"
                                           >
                                    <span class="invalid-feedback" id="to_err"></span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-3">
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
        $('#preview').click(function(){
            var table = $('#table').DataTable();
            var from = $('#from').val();
            var to = $('#to').val();

            if (from == '') {
                $('#from').addClass('is-invalid');
                $('#from_err').text('Select Start Date');
            }
            else{
                $('#from').removeClass('is-invalid');
                $('#from_err').text('');
            }

            if (to == '') {
                $('#to').addClass('is-invalid');
                $('#to_err').text('Select Start Date');
            }
            else{
                $('#to').removeClass('is-invalid');
                $('#to_err').text('');
            }
            
            if (from == '' && to == '') {
                return
            }

            $.ajax({
                url : '<?php echo URLROOT;?>/member_reports/transferedrpt',
                method : 'POST',
                data : {from : from, to : to},
                success : function(html){
                    $('#results').html(html);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 25,
                        fixedHeader : true,
                        'columnDefs' : [
                            {"width" : "10%" , "targets": 2},
                            {"width" : "10%" , "targets": 4},
                            {"width" : "10%" , "targets": 1},
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