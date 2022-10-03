<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-10 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="type">Report Type</label>
                                <select name="type" id="type" class="form-control form-control-sm">
                                    <option value="1">All</option>
                                    <option value="2">By Expenses Acc</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="account">Expenses Acc</label>
                                <select name="account[]" id="account" class="form-control form-control-sm" multiple disabled>
                                    <?php foreach($data['accounts'] as $accounts) : ?>
                                        <option value="<?php echo $accounts->ID;?>"><?php echo $accounts->accountType;?></option>
                                    <?php endforeach;?>
                                </select>
                                <span class="invalid-feedback" id="account_err"></span>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="start">Start Date</label>
                                <input type="date" name="start" id="start" class="form-control form-control-sm">
                                <span class="invalid-feedback" id="start_err"></span>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="end">End Date</label>
                                <input type="date" name="end" id="end" class="form-control form-control-sm">
                                <span class="invalid-feedback" id="end_err"></span>
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
        $('#account').selectpicker();
        //change
        $('#type').change(function(){
            var type = $(this).val();
            if(type == 2 ){
                $('#account').attr('disabled',false);
                $('.bootstrap-select').removeClass('disabled');
                $('.bootstrap-select button').removeClass('disabled');
            }else{
                $('#account').attr('disabled',true);
                $('.bootstrap-select').addClass('disabled');
                $('.bootstrap-select button').addClass('disabled');
            }
        });

        $('#preview').click(function(){
            var table = $('#table').DataTable();
            //validate
            var start_err = '';
            var end_err = '';
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

            if(Number($('#type').val()) === 2 && $('#account').val() == '' ){
                account_err = 'Select at least one account';
                $('#account_err').text(account_err);
                $('#account').addClass('is-invalid');
                $('.bootstrap-select').addClass('is-invalid');
            }else if(Number($('#type').val()) === 2 && $('#account').val() != ''){
                account_err = '';
                $('#account_err').text(account_err);
                $('#account').removeClass('is-invalid');
                $('.bootstrap-select').removeClass('is-invalid');
                account = $('#account').val();
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

            if(start_err !== '' || end_err !== '' || account_err !== '') return;
            var type = $('#type').val();
            var start = $('#start').val();
            var end = $('#end').val();
            var account = $('#account').val();
            
            $.ajax({
                url : '<?php echo URLROOT;?>/reports/expensesrpt',
                method : 'GET',
                data : {type : type, start : start,end : end, account : account},
                success : function(data){
                    // console.log(data);
                    $('#results').html(data);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        "responsive" : true,
                        'columnDefs' : [
                            {"width" : "10%" , "targets": 0},
                            {"width" : "15%" , "targets": 4},
                            {"width" : "15%" , "targets": 5},
                        ],
                        "buttons": ["excel", "pdf","print"],
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
                            $('#total').html(format_number(updateValues(4)));
                            
                        }
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });

        });
        

    });
</script>
</body>
</html>  