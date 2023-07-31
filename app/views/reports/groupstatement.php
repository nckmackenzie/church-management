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
                        <div class="col-md-6 mb-3">
                            <label for="group">Group</label>
                            <select name="group" id="group" class="control form-control form-control-sm">
                                <option value="" selected disabled>Select group</option>
                                <?php foreach($data['groups'] as $group) : ?>
                                    <option value="<?php echo $group->ID;?>"><?php echo $group->groupName;?></option>
                                <?php endforeach;?>
                            </select>
                            <span class="invalid-feedback" id="account_err"></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="start">Requisition</label>
                            <select name="requisition" id="requisition" class="control form-control form-control-sm">
                                <option value="" selected disabled>Select requisition</option>
                            </select>
                            <span class="invalid-feedback" id="start_err"></span>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/reports/groupstatement/index.js"></script>
<script>
    $(function(){
        $('#preview').click(function(){
            var table = $('#table').DataTable();
            let count = 0;
            const controls = document.querySelectorAll('.control');
            controls.forEach(cntrl => {
                if(cntrl.value  == ''){
                    cntrl.classList.add('is-invalid');
                    cntrl.nextSibling.nextSibling.textContent = 'field is required';
                    count++
                }
            });
            if(count > 0) return;
            // const start = $('#start').val();
            // const end = $('#end').val();
            const reqid = $('#requisition').val();
            $.ajax({
                url : '<?php echo URLROOT;?>/reports/groupstatementrpt',
                method : 'GET',
                data : {reqid : reqid},
                success : function(data){
                    // console.log(data);
                    $('#results').html(data);
                    table.destroy();
                    table = $('#table').DataTable({
                        pageLength : 100,
                        fixedHeader : true,
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
                            $('#deposits').html(format_number(updateValues(2)));
                            $('#withdrawals').html(format_number(updateValues(3)));
                            
                        }
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });
        });
    });
</script>
</body>
</html>  