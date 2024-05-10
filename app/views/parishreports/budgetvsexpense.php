<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="congregation">Congregation</label>
                                    <select name="congregation" id="congregation" class="form-control form-control-sm">
                                        <option value="" selected disabled>Select Congregation</option>
                                        <?php foreach($data['congregations'] as $congregation) : ?>
                                            <option value="<?php echo $congregation->ID;?>"><?php echo $congregation->CongregationName;?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback" id="cong_err"></span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="type">Report Type</label>
                                    <select name="type" id="type" class="form-control form-control-sm">
                                        <option value="1">Church</option>
                                        <option value="2">Groups</option>
                                    </select>            
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="group">Group</label>
                                    <select name="group" id="group" class="form-control form-control-sm" disabled>
                                        <option value="">Select Group</option>
                                        <?php foreach($data['groups'] as $group) : ?>
                                            <option value="<?php echo $group->ID;?>"><?php echo $group->groupName;?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback" id="group_err"></span>        
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="year">Financial Year</label>
                                    <select name="year" id="year" class="form-control form-control-sm">
                                        <option value="">Select Year</option>
                                        <?php foreach($data['years'] as $year) : ?>
                                            <option value="<?php echo $year->ID;?>" <?php selectdCheck($data['current'],$year->ID)?>><?php echo $year->yearName;?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback" id="year_err"></span>        
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
        $('#type').change(function(){
            var type = Number($(this).val());
            if (type === 2) {
                $('#group').attr('disabled',false);
            }
            if (type === 1) {
                $('#group').attr('disabled',true);
                $('#group').val('');
            }
        });

        $('#congregation').change(function(){
            var cong = Number($(this).val());
            $.ajax({
                url : '<?php echo URLROOT;?>/parishreports/getgroups',
                method : 'GET',
                data : {cong : cong},
                success : function(data){
                    $('#group').html(data);
                }
            });
        });

        $('#preview').click(function(){
            var table = $('#table').DataTable();
            var cong_err='';
            var group_err='';
            var year_err='';
            var type = Number($('#type').val());
            //validate
            if (Number($('#congregation').val()) === 0) {
                cong_err = 'Select congregation';
                $('#congregation').addClass('is-invalid');
                $('#cong_err').text(cong_err);
            }else{
                cong_err = '';
                $('#congregation').removeClass('is-invalid');
                $('#cong_err').text(cong_err);
            }

            if (type === 2 && $('#group').val() == '') {
                group_err = 'Select group';
                $('#group').addClass('is-invalid');
                $('#group_err').text(group_err);
            }else if(type === 2 && $('#group').val() != ''){
                group_err = '';
                $('#group').removeClass('is-invalid');
                $('#group_err').text(group_err);
            }

            if ($('#year').val() == '') {
                year_err = 'Select Year';
                $('#year').addClass('is-invalid');
                $('#year_err').text(year_err);
            }else{
                year_err = '';
                $('#year').removeClass('is-invalid');
                $('#year_err').text(year_err);
            }

            if(group_err !=='' || year_err !== '' || cong_err !== '') return;

            var type = Number($('#type').val());
            var year = Number($('#year').val());
            var cong = Number($('#congregation').val());
            var group = '';
            if (type === 2) {
                group = Number($('#group').val().trim());
            }
            
            $.ajax({
                url : '<?php echo URLROOT;?>/parishreports/budgetvsexpenserpt',
                method : 'GET',
                data : {cong : cong, type : type, year : year, group : group},
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
                            $('#budtotal').html(format_number(updateValues(1)));
                            $('#jantotal').html(format_number(updateValues(2)));
                            $('#febtotal').html(format_number(updateValues(3)));
                            $('#martotal').html(format_number(updateValues(4)));
                            $('#aprtotal').html(format_number(updateValues(5)));
                            $('#maytotal').html(format_number(updateValues(6)));
                            $('#juntotal').html(format_number(updateValues(7)));
                            $('#jultotal').html(format_number(updateValues(8)));
                            $('#augtotal').html(format_number(updateValues(9)));
                            $('#septotal').html(format_number(updateValues(10)));
                            $('#octtotal').html(format_number(updateValues(11)));
                            $('#novtotal').html(format_number(updateValues(12)));
                            $('#dectotal').html(format_number(updateValues(13)));
                            $('#exptotal').html(format_number(updateValues(14)));
                            $('#vartotal').html(format_number(updateValues(15)));
                            
                        }
                    }).buttons().container().appendTo('#table_wrapper .col-md-6:eq(0)');
                }
            });
        });
    });
</script>
</body>
</html>  