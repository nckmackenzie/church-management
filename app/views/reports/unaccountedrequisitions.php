<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="start">Group/District</label>
                                    <select name="group" id="group" class="form-control form-control-sm">
                                        <option value="all" selected>All</option>
                                        <option value="group">Groups</option>
                                        <option value="district">Districts</option>
                                    </select>   
                                    <span class="invalid-feedback" id="group_err"></span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-4">
                                <button class="btn btn-sm btn-primary custom-font" id="preview">Preview</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- data -->
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div id="results" class="table-responsive">                        
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        $('#preview').click(function(){
            var table = $('#table').DataTable();
            //validate
            var group_err = '';
            var end_err = '';

            if($('#group').val() == ''){
                group_err = 'Select one';
                $('#group_err').text(group_err);
                $('#group').addClass('is-invalid');
                
            }else{
                group_err = '';
                $('#group_err').text(group_err);
                $('#group').removeClass('is-invalid');
            }


            if(group_err !== '') return;
            var group = $('#group').val();
              
            $.ajax({
                url : '<?php echo URLROOT;?>/reports/unaccounted_requisition_rpt',
                method : 'GET',
                data : {group},
                success : function(data){
                    // console.log(data);
                    $('#results').html(data);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
                        ordering : false,
                        searching : true,
                        "responsive" : true,
                        // "buttons": ["excel", "pdf","print"],
                        buttons: [
                            { extend: 'excelHtml5', footer: true },
                            { extend: 'pdfHtml5', footer: true },
                            "print"
                        ],
                        drawCallback: function () {
                            $('.dataTables_paginate > .pagination').addClass('pagination-rounded');
                            let total = 0;
                            let api = this.api();                            
                            total = api
                                .column(4, { filter: 'applied' })
                                .data()
                                .reduce((a, b) => {
                               
                                let x = parseFloat(a.toString().replace(/,/g, '')) || 0;
                                let y = parseFloat(b.toString().replace(/,/g, '')) || 0;
                                return x + y;
                                }, 0);

                            $(api.column(4).footer()).html(
                                numberWithCommas(total.toFixed(2))
                            );
                        },
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });
        });
    });
</script>
</body>
</html>  