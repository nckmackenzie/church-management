<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-1">
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
          <div class="col-md-3">
            <label for="balance">Balance as per statement</label>
            <input type="number" name="balance" id="balance" class="form-control form-control-sm">
            <span class="invalid-feedback" id="balance_err"></span>
          </div>
          <div class="col-md-6 mt-2">
            <button class="btn btn-sm bg-navy custom-font" id="submit">Submit</button>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <!-- <div id="spinner" style="display:none;">Loading...</div> -->
                <div id="spinner" style="display:none;">
                    <div class="spinner md mx-auto"></div>
                </div>
                <div id="results" class="table-responsive">

                </div>
            </div>
        </div>           
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('#submit').click(function(){
            var table = $('#table').DataTable();
            //validate
            var from_err = '';
            var to_err = '';
            var bank_err = '';
            var balance_err = '';

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

            if($('#balance').val() == ''){
                balance_err = 'Enter Balance';
                $('#balance_err').text(balance_err);
                $('#balance').addClass('is-invalid');
            }else{
                balance_err = '';
                $('#balance_err').text(balance_err);
                $('#balance').removeClass('is-invalid');
            }

            if(from_err !== '' || to_err !== '' || bank_err !== '' || balance_err !== '') return;
            var bank = $('#bank').val();
            var from = $('#from').val();
            var to = $('#to').val();
            var balance = $('#balance').val();

            $.ajax({
                url : '<?php echo URLROOT;?>/bankreconcilliations/bankrecon',
                method : 'GET',
                data : {bank : bank, from : from, to : to, balance : balance},
                beforeSend: function() {        
                    $('#spinner').show();
                },
                success : function(data){
                    $('#spinner').hide();
                    
                    $('#results').html(data);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        ordering : false,
                        searching : false,
                        bLengthChange: false,
                        info : false,
                        paging : false,
                        "responsive" : true,
                        'columnDefs' : [
                            {"width" : "80%" , "targets": 0},
                            {"width" : "20%" , "targets": 1},
                        ],
                        "buttons": ["excel", "pdf","print"],
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                },
                error: function() {
                    // Hide the spinner in case of an error
                    $('#spinner').hide();
                }
            });
        });
    });
</script>
</body>
</html>  