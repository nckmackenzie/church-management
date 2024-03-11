<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
         <div class="col-md-3">
            <label for="subaccounts">Sub account</label>
            <select name="subaccounts" id="subaccounts" class="form-control form-control-sm">
                <option value="">Select sub account</option>
                <option value="all">All</option>
                <?php foreach($data['subaccounts'] as $account) : ?>
                    <option value="<?php echo $account->ID;?>"><?php echo $account->AccountName;?></option>
                <?php endforeach; ?>
            </select>
            <span class="invalid-feedback" id="subaccounts_err"></span>
          </div>
          <div class="col-md-3">
            <label for="from" id="fromLabel">From</label>
            <input type="date" name="from" id="from" class="form-control form-control-sm">
            <span class="invalid-feedback" id="from_err"></span>
          </div>
          <div class="col-md-3">
            <label for="to">To</label>
            <input type="date" name="to" id="to" class="form-control form-control-sm">
            <span class="invalid-feedback" id="to_err"></span>
          </div>
          <div class="col-md-6 mt-2">
            <button class="btn btn-sm bg-navy custom-font" id="preview">Preview</button>
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

        $('#subaccounts').change(function(){
            const value = $(this).val();
            if(value === 'all'){
                $('#fromLabel').text('As of');
                $('#to').prop('disabled', true);
            }else{
                $('#fromLabel').text('From');
                $('#to').prop('disabled', false);
            }
        })

        $('#preview').click(function(){
            var table = $('#table').DataTable();
            //validate
            var from_err = '';
            var to_err = '';
            var bank_err = '';
           
            if($('#subaccounts').val() == ''){
                bank_err = 'Select Sub account';
                $('#subaccounts_err').text(bank_err);
                $('#subaccounts').addClass('is-invalid');
            }else{
                bank_err = '';
                $('#subaccounts_err').text(bank_err);
                $('#subaccounts').removeClass('is-invalid');
            }

            if($('#from').val() == ''){
                from_err = 'Select Start Date';
                $('#from_err').text(from_err);
                $('#from').addClass('is-invalid');
            }else{
                from_err = '';
                $('#from_err').text(from_err);
                $('#from').removeClass('is-invalid');
            }

            if($('#to').val() == '' && $('#subaccounts').val() !== 'all'){
                to_err = 'Select End Date';
                $('#to_err').text(to_err);
                $('#to').addClass('is-invalid');
            }else if ($('#to').val() !== '' && $('#subaccounts').val() !== 'all'){
                to_err = '';
                $('#to_err').text(to_err);
                $('#to').removeClass('is-invalid');
            }

            if(from_err !== '' || to_err !== '' || bank_err !== '') return;
            var account = $('#subaccounts').val();
            var from = $('#from').val();
            var to = $('#to').val();
         
            $.ajax({
                url : '<?php echo URLROOT;?>/reports/subaccountsrpt',
                method : 'GET',
                data : {account, from, to},
                success : function(data){
                    // console.log(data);
                    $('#results').html(data);
                    table.destroy();
                    if(account !== 'all'){
                     
                        table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        ordering : false,
                        "responsive" : true,
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
                            $('#debits').html(format_number(updateValues(2)));
                            $('#credits').html(format_number(updateValues(3)));
                            
                        }
                        }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                    }
                    else {
                        
                        table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        ordering : false,
                        "responsive" : true,
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
                            $('#totals').html(format_number(updateValues(1)));
                        }
                        }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                    }
                }
            });
        });
    });
</script>
</body>
</html>  