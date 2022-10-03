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
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="congregation">Congregation</label>
                                    <select name="congregation" id="congregation" class="form-control form-control-sm">
                                        <option value="0" selected>All</option>
                                        <?php foreach($data['congregations'] as $congregation) : ?>
                                            <option value="<?php echo $congregation->ID;?>"><?php echo $congregation->CongregationName;?></option>
                                        <?php endforeach;?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="todate">Balance Sheet As At</label>
                                    <input type="date" name="todate" id="todate" class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="todate_err"></span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4">
                                <button class="btn btn-sm btn-primary custom-font" id="preview">Preview</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- data -->
        <div class="row">
            <div class="col-md-9 mx-auto">
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
            var todate_err = '';
            //validate
            if($('#todate').val() == ''){
                todate_err = 'Select Date';
                $('#todate_err').text(todate_err);
                $('#todate').addClass('is-invalid');
                
            }else{
                todate_err = '';
                $('#todate_err').text(todate_err);
                $('#todate').removeClass('is-invalid');
            }

            if(todate_err !== '') return;
            var todate = $('#todate').val();
            var cong = $('#congregation').val();
            
            $.ajax({
                url : '<?php echo URLROOT;?>/parishreports/balancesheetrpt',
                method : 'GET',
                data : {cong : cong, todate : todate},
                success : function(data){
                    // console.log(data);
                    $('#results').html(data);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        ordering : false,
                        searching : false,
                        "responsive" : true,
                        "buttons": ["excel", "pdf","print"],
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });

        });
    });
</script>
</body>
</html>  