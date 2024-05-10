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
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status">Invoice Status</label>
                                    <select name="status" id="status" class="form-control form-control-sm">
                                        <option value="" selected>Select Status</option>
                                        <option value="1">With Balances</option>
                                        <option value="2">Fully Paid</option>
                                        <option value="3">Overdue Invoices</option>
                                    </select>
                                    <span class="invalid-feedback" id="status_err"></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="start">Start Date</label>
                                    <input type="date" name="start" id="start" class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="start_err"></span>
                                </div>
                            </div>
                            <div class="col-md-4">
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
        
        $('#status').change(function(){
            var status = Number($(this).val());
            if (status === 3) {
                $('#start').attr('disabled',true);
                $('#end').attr('disabled',true);
            }else{
                $('#start').attr('disabled',false);
                $('#end').attr('disabled',false);
            }
        });

        $('#preview').click(function(){
            var table = $('#table').DataTable();
            //validate
            var start_err = '';
            var end_err = '';
            var status_err = '';
            var status = Number($('#status').val());
            
            if($('#start').val() == '' && status < 3){
                start_err = 'Select Start Date';
                $('#start_err').text(start_err);
                $('#start').addClass('is-invalid');
            }else if($('#start').val() !== ''){
                start_err = '';
                $('#start_err').text(start_err);
                $('#start').removeClass('is-invalid');
            }

            if($('#end').val() == '' && status < 3){
                end_err = 'Select End Date';
                $('#end_err').text(end_err);
                $('#end').addClass('is-invalid');
            }else if($('#end').val() !== ''){
                end_err = '';
                $('#end_err').text(end_err);
                $('#end').removeClass('is-invalid');
            }

            if($('#status').val() == '' ){
                status_err = 'Select status';
                $('#status_err').text(status_err);
                $('#status').addClass('is-invalid');
            }else{
                status_err = '';
                $('#status_err').text(status_err);
                $('#status').removeClass('is-invalid');
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

            if(start_err !== '' || end_err !== '' || status_err !== '') return;
            var start = $('#start').val();
            var end = $('#end').val();
            var status = $('#status').val();
            
            $.ajax({
                url : '<?php echo URLROOT;?>/parishreports/invoicesrpt',
                method : 'GET',
                data : {status : status, start : start, end : end},
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
                            if (+status < 3){
                                $('#amounttotal').html(format_number(updateValues(3)));
                                $('#paidtotal').html(format_number(updateValues(4)));
                            }
                            if (+status === 1) {
                                $('#baltotal').html(format_number(updateValues(5)));
                            }
                            if(+status === 3){
                                $('#amounttotal').html(format_number(updateValues(5)));
                                $('#paidtotal').html(format_number(updateValues(6)));
                                $('#baltotal').html(format_number(updateValues(7)));
                            }
                            
                        }
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            })
        });
    });
</script>
</body>
</html>  