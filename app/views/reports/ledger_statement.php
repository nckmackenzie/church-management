<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <div id="alertBox"></div>
            </div>
          <div class="col-sm-4">
             <div class="form-group">
                <label for="account">G/L Account</label>
                <select name="account" id="account" class="form-control form-control-sm mandatory">
                    <option value="" selected disabled>Select G/L account</option>
                    <?php foreach($data['accounts'] as $account) : ?>
                        <option value="<?php echo $account->ID?>"><?php echo $account->accountType?></option>
                    <?php endforeach?>
                </select>
                <span class="invalid-feedback" id="account-err"></span>
             </div>
          </div>
          <div class="col-sm-4">
             <div class="form-group">
                <label for="sdate">Start Date</label>
                <input type="date" name="sdate" id="sdate" class="form-control form-control-sm">
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-4">
             <div class="form-group">
                <label for="edate">End Date</label>
                <input type="date" name="edate" id="edate" class="form-control form-control-sm">
                <span class="invalid-feedback"></span>
             </div>
          </div>
          <div class="col-sm-2">
            <button type="button" class="btn btn-sm btn-primary preview">Preview</button>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="col-md-12">
            <!-- <div id="spinner" style="display:none;">Loading...</div> -->
            <div id="spinner" style="display:none;">
                <div class="spinner md mx-auto"></div>
            </div>
            <div id="results" class="table-responsive"></div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('#account').select2();

        $('#account').on('change',function(){
            $('#account-err').removeClass('d-block');
            $('#account-err').text('');
        });

        $('#sdate').on('change',function(){
            $('#sdate').removeClass('is-invalid');
            $('#sdate').siblings('.invalid-feedback').text('');
        });

        $('#edate').on('change',function(){
            $('#edate').removeClass('is-invalid');
            $('#edate').siblings('.invalid-feedback').text('');
        });

        $('.preview').on('click',function(){
            var table = $('#table').DataTable();
            let account = $('#account').val();
            let from = $('#sdate').val();
            let to = $('#edate').val();
            if(!account || account.trim() === ''){
               
                $('#account-err').addClass('d-block');
                $('#account-err').text('Please select account');
            }
            if(from === ''){
                $('#sdate').addClass('is-invalid');
                $('#sdate').siblings('.invalid-feedback').text('Please select start date');
            }
            if(to === ''){
                $('#edate').addClass('is-invalid');
                $('#edate').siblings('.invalid-feedback').text('Please select end date');
            }
            if(account === '' || from === '' || to === '') return

            $.ajax({
                url : '<?php echo URLROOT;?>/reports/ledgerstatementrpt',
                method : 'GET',
                data : {account, from, to},
                beforeSend: function() {        
                    $('#spinner').show();
                },
                success: function(data) {
                    $('#spinner').hide();

                    if ($.fn.DataTable.isDataTable('#table')) {
                        $('#table').DataTable().clear().destroy();
                    }

                    $('#results').html('');

                    $('#results').html(data);

                    table = $('#table').DataTable({
                        pageLength: 50,
                        fixedHeader: true,
                        ordering: false,
                        searching: false,
                        bLengthChange: false,
                        info: false,
                        paging: false,
                        responsive: true,
                        columnDefs: [
                            {"width": "40%", "targets": 1},
                        ],
                        buttons: ["excel", "pdf", "print"],
                    });

                    table.buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                },
                error: function() {
                    $('#spinner').hide();
                }
            });
        });       
    });    
</script>
</body>
</html>  