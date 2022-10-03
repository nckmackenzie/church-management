<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Clear Banking</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form action="<?php echo URLROOT;?>/clearbankings/delete" method="post">
            <div class="row">
            <div class="col-md-9">
                <label for="">Delete Selected Banking?</label>
                <input type="hidden" name="id" id="id">
            </div>
            </div>
            <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-danger">Yes</button>
            </div>
        </form>
      </div>
     
    </div>
  </div>
</div>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('bankings_msg');?> 
        <div class="row mb-2">
            
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="bank">Bank</label>
                                    <select name="bank" id="bank" class="form-control form-control-sm">
                                        <option value="">Select Bank</option>
                                        <?php foreach($data['banks'] as $bank) : ?>
                                            <option value="<?php echo $bank->ID;?>"><?php echo $bank->Bank;?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="invalid-feedback" id="bank_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="from">From</label>
                                    <input type="date" name="from" id="from" class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="from_err"></span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="to">To</label>
                                    <input type="date" name="to" id="to" class="form-control form-control-sm">
                                    <span class="invalid-feedback" id="to_err"></span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label for="" style="color: #F8F9FA;">button</label>
                                <button type="button" class="btn btn-sm btn-info form-control form-control-sm fetch">Fetch</button>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="balance">Balance</label>
                                    <input type="hidden" id="balance" class="form-control form-control-sm" readonly>
                                    <input type="text" id="balanced" class="form-control form-control-sm" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="deposits">Cleared deposits</label>
                                    <input type="hidden" id="deposits" class="form-control form-control-sm" readonly>      
                                    <input type="text" id="depositsd" class="form-control form-control-sm" readonly>      
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="withdrawals">Cleared withdrawals</label>
                                    <input type="hidden" id="withdrawals" class="form-control form-control-sm" readonly>       
                                    <input type="text" id="withdrawalsd" class="form-control form-control-sm" readonly>       
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="variance">Variance</label>
                                    <input type="text" id="variance" class="form-control form-control-sm" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12 table-responsive">
                <form action="<?php echo URLROOT;?>/clearbankings/clear">
                    <button type="submit" id="save" class="btn btn-sm bg-navy custom-font">Clear Selected</button>
                    <div id="results"></div>
                </form>    
            </div>
        </div><!--End of row -->
    </section><!-- /.content -->
    <!-- </form> -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>

<script>
$(function () {
    function numberWithCommas(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }
    function removeCommas(x) {
        return x.toString().replace(",", "");
    }
    $('.fetch').click(function(){
        var from_err = '';
        var to_err = '';
        var bank_err = '';

        if ($('#bank').val() == '') {
            bank_err = 'Select Bank';
            $('#bank_err').text(bank_err);
            $('#bank').addClass('is-invalid');
        } else {
        bank_err = '';
            $('#bank_err').text(bank_err);
            $('#bank').removeClass('is-invalid');
        }

        if ($('#from').val() == '') {
            from_err = 'Select Start Date';
            $('#from_err').text(from_err);
            $('#from').addClass('is-invalid');
        } else {
            from_err = '';
            $('#from_err').text(from_err);
            $('#from').removeClass('is-invalid');
        }

        if ($('#to').val() == '') {
            to_err = 'Select End Date';
            $('#to_err').text(to_err);
            $('#to').addClass('is-invalid');
        } else {
        to_err = '';
            $('#to_err').text(to_err);
            $('#to').removeClass('is-invalid');
        }

        if (from_err !== '' || to_err !== '' || bank_err !== '') return;
        var bank = $('#bank').val();
        var from = $('#from').val();
        var to = $('#to').val();

        $.ajax({
            url: '<?php echo URLROOT;?>/clearbankings/fetch',
            method: 'GET',
            data: { bank: bank, from: from, to: to },
            success: function (data) {
                // console.log(data);
                $('#results').html(data);
                table = $('#bankingsTable').DataTable({
                    pageLength : 100,
                    ordering : false,
                    searching : false,
                    bLengthChange: false,
                    "responsive" : true,
                    'columnDefs' : [
                        {"visible" : false , "targets": 0},
                    ],
                });
                //fetch details
                $.ajax({
                    url: '<?php echo URLROOT;?>/clearbankings/getValues',
                    method: 'GET',
                    data: { bank: bank, from: from, to: to },
                    dataType : 'json',
                    success : function(values){
                        // console.log(values.debits);
                        $('#deposits').val(values.debits);
                        $('#depositsd').val(numberWithCommas(values.debits));
                        $('#withdrawals').val(values.credits);
                        $('#withdrawalsd').val(numberWithCommas(values.credits));
                        $('#balance').val(values.balance);
                        $('#balanced').val(numberWithCommas(values.balance));
                        $('#variance').val(numberWithCommas(values.variance));
                    }
                });
            }
        });
    });

    $('#results').on('click','#cleared',function(){
        var debitsTotal = 0;
        var creditsTotal = 0;
        var variance = 0;
        var checkedItems = $('#bankingsTable input[type="checkbox"]:checked').each(
            function () {
                $tr = $(this).closest('tr');

                let data = $tr
                .children('td')
                .map(function () {
                    return $(this).text();
                })
                .get();

                // console.log(removeCommas(data[2]),Number(removeCommas(data[2])) >= 0);
                
                if(Number(removeCommas(data[2])) >= 0){
                    debitsTotal += Number(removeCommas(data[2]))
                }else{
                    creditsTotal += Number(removeCommas(data[2]))
                } 
            }
        );

        var initialDebits = Number($('#deposits').val());
        var initialCredits = Number($('#withdrawals').val());
        var balance = Number($('#balance').val());
        var totalDebits = initialDebits + debitsTotal;
        var totalCredits = initialCredits + creditsTotal;
        var runningVariance = balance - (totalDebits - totalCredits);
        $('#variance').val(numberWithCommas(runningVariance));
        $('#depositsd').val(numberWithCommas(totalDebits));
        $('#withdrawalsd').val(numberWithCommas(totalCredits));
        // console.log(initialDebits,initialCredits);
    })

    $('#save').click(function(e){
        e.preventDefault();
        var table_data = [];
        var checkedItems = $('#bankingsTable input[type="checkbox"]:checked').each(
            function () {
                $tr = $(this).closest('tr');

                let data = $tr
                .children('td')
                .map(function () {
                    return $(this).text();
                })
                .get();
                table_data.push(data[0]);
                // console.log($(this).parent('tr').find('.data-selector').val());
            }
        );
        if (table_data.length === 0) {
            alert('Nothing Selected');
            return;
        }
        $.ajax({
            url: '<?php echo URLROOT;?>/clearbankings/clear',
            method: 'POST',
            data: {
                table_data: table_data,
            },
            success: function (data) {
                window.location.href = '<?php echo URLROOT;?>/clearbankings';
            },
        });
    });

    $('#results').on('click','.btndel',function(){
        $('#deleteModalCenter').modal('show');
        $tr = $(this).closest('tr');

        let data = $tr.children('td').map(function(){
              return $(this).text();
        }).get();

        var currentRow = $(this).closest("tr");
        var data1 = $('#bankingsTable').DataTable().row(currentRow).data();
        $('#id').val(data1[0]);

        // let data = $tr
        // .children('td')
        // .map(function () {
        //     return $(this).text();
        // })
        // .get();
        // $('#id').val(data[0]);
    });
    
});
</script>
</body>
</html> 