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
            <label for="status">Status</label>
            <select name="status" id="status" class="form-control form-control-sm">
                <option value="">Select Status</option>
                <option value="0">Uncleared</option>
                <option value="1">Cleared</option>
            </select>
            <span class="invalid-feedback" id="status_err"></span>
          </div>
          <div class="col-md-3">
            <label for="bank">Bank</label>
            <select name="bank" id="bank" class="form-control form-control-sm">
                <option value="">Select Bank</option>
                <?php foreach($data['banks'] as $bank) : ?>
                    <option value="<?php echo $bank->ID;?>"><?php echo $bank->Bank;?></option>
                <?php endforeach; ?>
            </select>
            <span class="invalid-feedback" id="bank_err"></span>
          </div>
          <div class="col-md-3">
            <label for="from">From</label>
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
        $('#preview').click(function(){
            var table = $('#table').DataTable();
            //validate
            var from_err = '';
            var to_err = '';
            var bank_err = '';
            var status_err = '';

            if($('#bank').val() == ''){
                bank_err = 'Select Bank';
                $('#bank_err').text(bank_err);
                $('#bank').addClass('is-invalid');
            }else{
                bank_err = '';
                $('#bank_err').text(bank_err);
                $('#bank').removeClass('is-invalid');
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

            if($('#to').val() == ''){
                to_err = 'Select End Date';
                $('#to_err').text(to_err);
                $('#to').addClass('is-invalid');
            }else{
                to_err = '';
                $('#to_err').text(to_err);
                $('#to').removeClass('is-invalid');
            }

            if($('#status').val() == ''){
                status_err = 'Select status';
                $('#status_err').text(status_err);
                $('#status').addClass('is-invalid');
            }else{
                status_err = '';
                $('#status_err').text(status_err);
                $('#status').removeClass('is-invalid');
            }

            if(from_err !== '' || to_err !== '' || bank_err !== '' || status_err !== '') return;
            var bank = $('#bank').val();
            var from = $('#from').val();
            var to = $('#to').val();
            var status = $('#status').val();

            $.ajax({
                url : '<?php echo URLROOT;?>/parishreports/bankingrpt',
                method : 'GET',
                data : {bank : bank, from : from, to : to, status : status},
                success : function(data){
                    // console.log(data);
                    $('#results').html(data);
                    table.destroy();
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