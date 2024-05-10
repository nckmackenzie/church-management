<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 mb-3">
                                <label for="type">Report Type</label>
                                <select name="type" id="type" class="form-control form-control-sm">
                                    <option value="">Select Type</option>
                                    <option value="customer">Customer Payments</option>
                                    <option value="supplier">Supplier Payments</option>
                                </select>
                                <span class="invalid-feedback" id="type_err"></span>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="customer" id="customerselect">Select</label>
                                <select name="customer" id="customer" class="form-control form-control-sm"></select>
                                <span class="invalid-feedback" id="customer_err"></span>
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
    $(function () {
        $('#type').change(function(){
            var type = $(this).val().toString();
            $('#customerselect').text(type);
            $.ajax({
                url: '<?php echo URLROOT;?>/reports/getcustomersupplier',
                method: 'GET',
                data : {type: type},
                success : function (data) {
                    $('#customer').html(data);
                }
            });
        });
        $('#preview').click(function(){
            var table = $('#table').DataTable();
            //validate
            var start_err = '';
            var end_err = '';
            var type_err = '';
            var customer_err = '';

            if($('#type').val() == ''){
                type_err = 'Select report type';
                $('#type_err').text(type_err);
                $('#type').addClass('is-invalid');
                
            }else{
                type_err = '';
                $('#type_err').text(type_err);
                $('#type').removeClass('is-invalid');
            }

            if($('#customer').val() == ''){
                customer_err = 'Select customer or supplier';
                $('#customer_err').text(customer_err);
                $('#customer').addClass('is-invalid');
                
            }else{
                customer_err = '';
                $('#customer_err').text(customer_err);
                $('#customer').removeClass('is-invalid');
            }
            
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

            if(start_err !== '' || end_err !== '' || type_err !== '' || customer_err !== '') return;
            var type = $('#type').val();
            var start = $('#start').val();
            var end = $('#end').val();
            var customer = $('#customer').val();
                        
            $.ajax({
                url : '<?php echo URLROOT;?>/reports/paymentsrpt',
                method : 'GET',
                data : {type : type,start : start,end : end,customer : customer},
                success : function(data){
                    $('#results').html(data);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        "responsive" : true,
                        'columnDefs' : [
                            {"width" : "10%" , "targets": 0},
                        ],
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
                            $('#total').html(format_number(updateValues(1)));
                        }
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });

        });
    });
</script>
</body>
</html>  