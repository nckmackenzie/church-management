<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="congregation">Congregation</label>
                                    <select name="congregation[]" id="congregation" class="form-control form-control-sm multi" multiple data-actions-box="true">
                                        <?php foreach($data['congregations'] as $congregation) : ?>
                                            <option value="<?php echo $congregation->ID;?>"><?php echo $congregation->CongregationName;?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback" id="congregation_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="account">Account</label>
                                    <select name="account[]" id="account" class="form-control form-control-sm multi" multiple data-actions-box="true">
                                        <?php foreach($data['accounts'] as $accounts) : ?>
                                            <option value="<?php echo $accounts->ID;?>"><?php echo $accounts->accountType;?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback" id="account_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start">Start Date</label>
                                    <input type="date" name="start" id="start" class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="start_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="end">End Date</label>
                                    <input type="date" name="end" id="end" class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="end_err"></span>      
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
        <!-- data -->
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
        $('.multi').selectpicker();

        $('#preview').click(function(){
            var table = $('#table').DataTable();
            //validate
            var start_err = '';
            var end_err = '';
            var congregation_err = '';
            var account_err = '';

            if($('#start').val() == ''){
                start_err = 'Select Start Date';
                $('#start_err').text(start_err);
                $('#start').addClass('is-invalid');
                
            }else{
                start_err = '';
                $('#start_err').text(start_err);
                $('#start').removeClass('is-invalid');
            }

            if($('#end').val() == ''){
                end_err = 'Select End Date';
                $('#end_err').text(end_err);
                $('#end').addClass('is-invalid');
                end = $('#end').val();
            }else{
                end_err = '';
                $('#end_err').text(end_err);
                $('#end').removeClass('is-invalid');
            }

            if($('#congregation').val() == '' ){
                congregation_err = 'Select at least one congregation';
                $('#congregation_err').text(congregation_err);
                $('#congregation').addClass('is-invalid');
                $('.bootstrap-select').addClass('is-invalid');
            }else{
                congregation_err = '';
                $('#congregation_err').text(congregation_err);
                $('#congregation').removeClass('is-invalid');
                $('.bootstrap-select').removeClass('is-invalid');
            }

            if($('#account').val() == '' ){
                account_err = 'Select at least one account';
                $('#account_err').text(account_err);
                $('#account').addClass('is-invalid');
                $('.bootstrap-select').addClass('is-invalid');
            }else{
                account_err = '';
                $('#account_err').text(account_err);
                $('#account').removeClass('is-invalid');
                $('.bootstrap-select').removeClass('is-invalid');
            }

            if($('#start').val() !== '' && $('#end').val() !== ''){
                if($('#start').val() > $('#end').val()){
                    start_err = 'Start Date Cannot Be Greater Than End Date';
                    $('#start_err').text(start_err);
                    $('#start').addClass('is-invalid');
                }else{
                    start_err = '';
                    $('#start_err').text(start_err);
                    $('#start').removeClass('is-invalid');
                }
            }

            if(start_err !== '' || end_err !== '' || account_err !== '' || congregation_err !== '') return;
            var start = $('#start').val();
            var end = $('#end').val();
            var congregations = $('#congregation').val();
            var accounts = $('#account').val();

            $.ajax({
                url : '<?php echo URLROOT;?>/parishreports/bycontributorrpt',
                method : 'GET',
                data : {congregations : congregations, accounts : accounts, start : start, end : end},
                success : function(data){
                    // console.log(data);
                    $('#results').html(data);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        "responsive" : true,
                        // "buttons": ["excel", "pdf","print"],
                        buttons: [
                            { extend: 'excelHtml5', footer: true },
                            { extend: 'pdfHtml5', footer: true },
                            "print"
                        ],
                        "footerCallback": function ( row, data, start, end, display ) {
                            var api = this.api(), data;
                             // Remove the formatting to get integer data for summation
                            var intVal = function ( i ) {
                                return typeof i === 'string' ?
                                    i.replace(/[\$,]/g, '')*1 :
                                    typeof i === 'number' ?
                                        i : 0;
                            };

                            function updateValues(cl){
                                total = api
                                      .column( cl )
                                      .data()
                                      .reduce( function (a, b) {
                                      return intVal(a) + intVal(b);
                                      },0);
                                return total;      
                            }

                            function format_number(n) {
                              return n.toFixed(2).replace(/(\d)(?=(\d{3})+\.)/g, "$1,");
                            }
                            // Update footer
                            $('#total').html(format_number(updateValues(2)));
                            
                        }
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });

        });
    });
</script>
</body>
</html>  