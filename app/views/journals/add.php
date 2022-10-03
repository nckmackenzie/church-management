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
                <a href="<?php echo URLROOT;?>/journals" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="journalno">Journal No</label>
                    <input type="text" class="form-control form-control-sm" name="journalno"
                           id="journalno" 
                           value="<?php echo $data['journalno'];?>"
                           readonly>
                    <input type="hidden" id="no">       
                </div>
            </div>    
            <div class="col-md-3">
                <div class="form-group">
                    <label for="debits">Debits</label>
                    <input type="text" name="debits" id="debits"
                            class="form-control form-control-sm" readonly>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label for="credits">Credits</label>
                    <input type="text" name="credits" id="credits"
                            class="form-control form-control-sm" readonly>
                </div>
            </div>
        </div><!--End Of Row -->
        <div class="row">
            <div class="col-md-2">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" class="form-control form-control-sm"
                           name="date" id="date">
                </div>
            </div> 
            <div class="col-md-3">
                <div class="form-group">
                    <label for="account">Account</label>
                    <select name="account" id="account" class="form-control form-control-sm select2">
                        <?php foreach($data['accounts'] as $account) : ?>
                            <option value="<?php echo $account->ID;?>">
                                <?php echo strtoupper($account->accountType);?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="type">Type</label>
                    <select name="type" id="type" class="form-control form-control-sm">
                        <option value="1">Debit</option>
                        <option value="2">Credit</option>
                    </select>        
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label for="amount">Amount</label>
                    <input type="number" name="amount" id="amount" 
                           class="form-control form-control-sm"
                           autocomplete="off">       
                </div>
            </div> 
            <div class="col-md-3">
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" name="description" id="description" class="form-control form-control-sm"
                    autocomplete="off">        
                </div>
            </div> 
        </div><!--End Of Row -->
        <div class="row">
            <div class="col-2">
                <button id="add" class="btn btn-success btn-block btn-sm custom-font">Add</button>            
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
            <br>
                <table id="journal" class="table table-bordered table-sm ">
                    <thead class="bg-navy">
                        <th>#</th>
                        <th>Date</th>
                        <th>Account</th>
                        <th>Type</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th style="display: none;">Aid</th>
                        <th>Description</th>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="row">
            <div class="col-4">
                <button type="submit" class="btn btn-sm bg-navy" id="save">Save</button>
                <!-- <button type="submit" class="btn btn-sm btn-danger" id="test">Test</button> -->
            </div>
            
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('.select2').select2();

        var set_number = function(){
            var table_len = $('#journal tbody tr').length + 1;
            $('#no').val(table_len);
        }

        set_number(); //set table number
        //declare debit & credit
        var debits = 0;
        var credits = 0;
        
        $('#add').click(function(){
            if ($('#date').val() == '') {
                alert('Select Date');
                return false;
            }
            if ($('#account').val() == '' || $('#account').val() == null) {
                alert('Select Account');
                return false;
            }
            if ($('#amount').val() == '') {
                alert('Enter Amount');
                return false;
            }
            if ($('#description').val() == '') {
                alert('Enter Description');
                return false;
            }

            var debit;
            var credit;
            var no = $('#no').val();
            var tdate = $('#date').val();
            var account = $('#account').val();
            var accName = $('#account').find('option:selected').text();
            var type = $('#type').val();
            var typeName = $('#type').find('option:selected').text();
            if (type == 1) {
                debit = $('#amount').val();
                credit = '';
                debits += Number(debit);
            }
            else{
                debit = '';
                credit = $('#amount').val();
                credits += Number(credit);
            }
            var desc = $('#description').val();

            $('#journal tbody:last-child').append(
                '<tr>'+
                    '<td>'+no+'</td>'+
                    '<td>'+tdate+'</td>'+
                    '<td>'+accName+'</td>'+
                    '<td>'+typeName+'</td>'+
                    '<td>'+debit+'</td>'+
                    '<td>'+credit+'</td>'+
                    '<td style="display:none;">'+account+'</td>'+
                    '<td>'+desc+'</td>'+
                '</tr>'    
            );
            set_number();
            $('#account').val('');
            $('#type').val(1);
            $('#amount').val('');
            $('#description').val('');
            $('#debits').val(debits);
            $('#credits').val(credits);

        });

        $('#save').click(function(e){
            e.preventDefault();
            if ($('#no').val() == 1) {
                alert("Nothing Entered");
                return
            }
             
            if (Number($('#debits').val()) !== Number($('#credits').val())) {
                alert("Sum Of Debits And Credits Doesn't Match");
                return
            }

            var table_data = [];

            var journal = $('#journalno').val();

            $('#journal tr').each(function(row,tr){

                if ($(tr).find('td:eq(0)').text() == '') {
        
                }
                else{
                    var sub = {
                        'no' : $(tr).find('td:eq(0)').text(),
                        'date' : $(tr).find('td:eq(1)').text(),
                        'account' : $(tr).find('td:eq(2)').text(),
                        'type' : $(tr).find('td:eq(3)').text(),
                        'debit' : $(tr).find('td:eq(4)').text(),
                        'credit' : $(tr).find('td:eq(5)').text(),
                        'aid' : $(tr).find('td:eq(6)').text(),
                        'desc' : $(tr).find('td:eq(7)').text(),
                    }
                    table_data.push(sub);
                }    
            });

            // console.log(journal);

            $.ajax({
                url : '<?php echo URLROOT;?>/journals/create',
                method : 'POST',
                data : {journal : journal, table_data : table_data},
                success : function(data){
                    window.location.href = '../journals';
                }
            });
        });
        // $('#test').click(function(){
        //     // window.location.replace("http://stackoverflow.com");
        //     window.location.href = '../journals';
        // });
    });
</script>
</body>
</html>  