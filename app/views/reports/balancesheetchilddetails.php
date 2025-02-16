<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12" id="alertBox"></div>
          <div class="col-sm-6">
            <h6 class="text-capitalize"></h6>
          </div>
          <div class="col-sm-6"></div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="row">
            <div class="col-md-9 mx-auto">
                <div id="results" class="table-responsive">
                        
                </div>
                <div id="loading" style="display: none;">Loading...</div>
                <div id="error" style="display: none; color: red;">An error occurred while fetching data.</div>
            </div>
      </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
  $(function(){
    const urlSearchParams = new URLSearchParams(window.location.search);
    const params = Object.fromEntries(urlSearchParams.entries());
    var table = $('#table').DataTable();

    const { account, asdate } = params;

    $('#loading').show();
    $('#results').hide();
    $('#error').hide();

    $('.text-capitalize').text(`${account} break down As At ${asdate}`);

    if(!account || !asdate){
        $('#loading').hide();
        $('#error').show();
        $('#results').hide();
        return;
    }

      $.ajax({
          url : '<?php echo URLROOT;?>/reports/balancesheetchilddetailsrpt',
          method : 'GET',
          data : {account, asdate},
          success : function(data){
            $('#loading').hide();
            $('#results').html(data).show();
              table.destroy();
              table = $('#table').DataTable({
                  pageLength : 50,
                  fixedHeader : true,
                  ordering : false,
                  searching : false,
                  "responsive" : true,
                  buttons: [
                      { extend: 'excelHtml5', footer: true },
                      { extend: 'pdfHtml5', footer: true },
                      "print"
                  ],
                  drawCallback: function () {
                    $('.dataTables_paginate > .pagination').addClass('pagination-rounded');                    
                   
                      let total = 0;
                      let api = this.api();

                      // Calculate the sum of the filtered rows for the specific column
                      total = api
                        .column(1, { filter: 'applied' }) // Only consider filtered rows
                        .data()
                        .reduce((a, b) => {
                          // Remove commas if present and convert to a float
                          let x = parseFloat(a.toString().replace(/,/g, '')) || 0;
                          let y = parseFloat(b.toString().replace(/,/g, '')) || 0;
                          return x + y;
                        }, 0);

                      // Format the sum and display it in the footer
                      $(api.column(1).footer()).html(
                        total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')
                      );
                  
              },
              }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
          },
          error: function() {
            $('#loading').hide();
            $('#error').show();
        }
      });
  });
</script>
</body>
</html>  