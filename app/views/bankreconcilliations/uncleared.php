<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h5 class="title"></h5>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12">
                <div class="spinner-container justify-content-center"></div>
                <div id="alertBox"></div>
            </div>
            <div class="col-12">
                <div id="results" class="d-none">
                    <table class="table table-bordered table-sm" id="unclearedTable">
                        <thead class="bg-navy">
                            <tr>
                                <th>Transaction Date</th>
                                <th>Amount</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/bankings/reconcilliation-report.js"></script>
<script>
  $(function(){
    $('#unclearedTable').DataTable({
      lengthChange: !1,
        pageLength: pageLength || 25,
        // buttons: ['print', 'excel', 'pdf'],
        buttons: [
          { extend: 'excelHtml5', footer: true },
          { extend: 'pdfHtml5', footer: true },
          'print',
        ],
        columnDefs: columnDefs,
        ordering: false,
        drawCallback: function () {
          $('.dataTables_paginate > .pagination').addClass(
            'pagination-rounded'
          );
        },
    }).buttons()
      .container()
      .appendTo(`#unclearedTable_wrapper .col-md-6:eq(0)`);;
  })
</script>
</body>
</html>  