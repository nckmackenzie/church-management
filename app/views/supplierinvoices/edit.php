<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
   
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 mt-1">
                <div class="card bg-light">
                    <div class="card-header">Edit Invoice</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/supplierinvoices/update" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier">Supplier</label>
                                        <select name="supplier" id="supplier"
                                                class="form-control form-control-sm select2">
                                            <?php foreach($data['suppliers'] as $supplier) : ?>
                                                <option value="<?php echo $supplier->ID;?>"
                                                <?php selectdCheck($data['header']->supplierId,$supplier->ID)?>>
                                                    <?php echo $supplier->supplierName; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="text" id="email"
                                               class="form-control form-control-sm" readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="pin">PIN</label>
                                        <input type="text" id="pin"
                                               class="form-control form-control-sm" readonly>
                                    </div>
                                </div>
                            </div><!--end of row -->
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="idate">Invoice Date</label>
                                        <input type="date" name="idate" id="idate"
                                               class="form-control form-control-sm" 
                                               value="<?php echo $data['header']->invoiceDate;?>"
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="duedate">Due Date</label>
                                        <input type="date" name="duedate" id="duedate" 
                                               class="form-control form-control-sm" 
                                               value="<?php echo $data['header']->duedate;?>"
                                               required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vtype">V.A.T Type</label>
                                        <select name="vattype" id="vattype" 
                                                class="form-control form-control-sm">
                                            <option value="1" <?php selectdCheck($data['header']->vattype,1)?>>No Vat</option>
                                            <option value="2" <?php selectdCheck($data['header']->vattype,2)?>>Inclusive</option>
                                            <option value="3" <?php selectdCheck($data['header']->vattype,3)?>>Exclusive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vat">V.A.T</label>
                                        <select name="vat" id="vat" class="form-control form-control-sm">
                                            <?php foreach($data['vats'] as $vat) : ?>
                                                <option value="<?php echo $vat->rate;?>"
                                                <?php selectdCheck($data['header']->vatId,$vat->ID)?>>
                                                    <?php echo $vat->vatName; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="invoiceno">Invoice #</label>
                                        <input type="text" class="form-control form-control-sm" name="invoiceno" id="invoiceno"
                                        value="<?php echo $data['header']->invoiceNo;?>"
                                        readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                       <label for="product">Product</label>
                                       <select name="product" id="product" 
                                                class="form-control form-control-sm select2">
                                            <?php foreach($data['products'] as $product) : ?>
                                                <option value="<?php echo $product->ID;?>">
                                                    <?php echo $product->productName;?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                         <label for="">Qty</label>
                                         <input type="number" class="form-control form-control-sm" id="qty">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="rate">Rate</label>
                                        <input type="number" id="rate" class="form-control form-control-sm">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="gross">Gross</label>
                                        <input type="text" class="form-control form-control-sm" 
                                           id="gross" readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <input type="text" id="description" class="form-control form-control-sm" autocomplete="off">        
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label for="description" style="color: #F4F6F9;">Description</label>
                                        <button type="button" id="add" 
                                        class="btn btn-sm btn-success custom-font form-control form-control-sm">Add</button>        
                                    </div>
                                </div>
                                <div class="col-md-1">
                                
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="total">Total</label>
                                        <input type="text" id="totals"
                                           class="form-control form-control-sm" 
                                           value="<?php echo ($data['header']->vattype == 3) ? $data['header']->exclusiveVat : $data['header']->inclusiveVat ?>"
                                           readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vatamount">VAT</label>
                                        <input type="text" id="vatamount"
                                           class="form-control form-control-sm" 
                                           value="<?php echo $data['header']->vat;?>"
                                           readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                           <hr>
                           <div class="row">
                               <div class="col-md-12 table-responsive mt-2">
                                    <table id="details" class="table table-bordered table-sm table-striped">
                                        <thead class="bg-navy">
                                            <tr>
                                                <th style="display: none;">Product</th>
                                                <th width="20%">Product</th>
                                                <th width="20%">Desc</th>
                                                <th width="5%">Qty</th>
                                                <th width="10%">Rate</th>
                                                <th width="10%">Gross</th>
                                                <th width="5%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($data['details'] as $detail) : ?>
                                                <tr>
                                                    <td style="display: none;"><?php echo $detail->productId;?></td>
                                                    <td><?php echo $detail->accountType;?></td>
                                                    <td><?php echo $detail->description;?></td>
                                                    <td><?php echo $detail->qty;?></td>
                                                    <td><?php echo $detail->rate;?></td>
                                                    <td><?php echo $detail->gross;?></td>
                                                    <td><a href="#" class="btnRemove">Remove</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>            
                               </div>
                           </div>
                           <hr>
                           <div class="row">
                                <div class="col-3">
                                    <button id="save" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" id="vrate" name="vrate">
                                    <input type="hidden" name="id" id="eid" value="<?php echo $data['header']->ID;?>">
                                </div>
                           </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function() {
        $('.select2').select2();
        $('#product').select2({
            placeholder: 'Select Product'
        });
        //----load ---
        $(window).on('load',function(){
            // var now = new Date();
            // var day = ("0" + now.getDate()).slice(-2);
            // var month = ("0" + (now.getMonth() + 1)).slice(-2);
            // var today = now.getFullYear()+"-"+(month)+"-"+(day) ;
            // $('#idate').val(today);
            // $('#duedate').val(formatDate(addDays(today, 30)));
            // $('#vat').attr('disabled',true);
            // $('#vat').val('');
            // $('#totals').val(0);
            getSupplierDetails($('#supplier').val())
        });
        $('#product').change(function(){
            getProductRate();
        });
        //supplier selectedIndex_change
        $('#supplier').change(function(){
            var cid = $(this).val();
            getSupplierDetails(cid);
        });
        //date change
        $('#idate').change(function(){
            var days = $(this).val();
            var date = $('#idate').val();
            $('#duedate').val(formatDate(addDays(date, 30))); 
        });
        //vatype_change
        $('#vattype').change(function(){
            var vattype = $(this).val();
            if (Number(vattype) === 1) {
                $('#vat').val('');
                $('#vat').attr('disabled',true);
            }
            else{
                $('#vat').attr('disabled',false);
                $("#vat").prop("selectedIndex", 0);
                // getRate();
            }
            calculateVat();
        });
        $('#vat').change(function(){
            if ($(this).val() != '') {
                // getRate();
                calculateVat();
            }
        });
        //get supplier details
        function getSupplierDetails(id){
            $.ajax({
                url : '<?php echo URLROOT;?>/supplierinvoices/fetchsupplierdetails',
                method : 'POST',
                data : {id : id},
                dataType : 'json',
                success : function(json){
                    // console.log(json);
                    $('#email').val(json.email);
                    $('#pin').val(json.pin);
                }
            });
        }
        //function to add 30 days
        function addDays(date, days) {
            var result = new Date(date);
            result.setDate(result.getDate() + days);
            return result;
        }
        //date format
        function formatDate(date) {
            var d = new Date(date),
            month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

            if (month.length < 2) 
                month = '0' + month;
            if (day.length < 2) 
                day = '0' + day;

            return [year, month, day].join('-');
        }
        //sum products
        var getGross = function(){
            var qty = Number($('#qty').val());
            var rate = Number($('#rate').val());
            var gross = Number(qty) * Number(rate);
            return gross;
        }
        //get gross value
        $('#qty').focusout(function(){
            if ($('#qty').val() !== '' && $('#rate').val() !== '') {
                $('#gross').val(getGross);
            }
        });
        $('#rate').focusout(function(){
            if ($('#qty').val() !== '' && $('#rate').val() !== '') {
                $('#gross').val(getGross);
            }
        });
        //add button
        $('#add').click(function(){
            if ($('#product').val() == '') {
                alert('Select Product');
                return
            }
            if ($('#qty').val() == '') {
                alert('Enter Qty');
                return
            }
            if ($('#rate').val() == '') {
                alert('Enter Rate');
                return
            }
            if ($('#description').val() == '') {
                alert('Enter Description');
                return
            }
            var pid = $('#product').val();
            var product = $('#product').find('option:selected').text();
            var qty = $('#qty').val();
            var rate = $('#rate').val();
            var gross = $('#gross').val();
            var description = $('#description').val();
            $('#details tbody:last-child').append(
                '<tr>'+
                    '<td style="display: none;">'+pid+'</td>'+
                    '<td>'+product+'</td>'+
                    '<td>'+description+'</td>'+
                    '<td>'+qty+'</td>'+
                    '<td>'+rate+'</td>'+
                    '<td>'+gross+'</td>'+
                    '<td><a href="#" class="btnRemove">Remove</a></td>'+
                '</tr>'    
            );
            $('#qty').val('');
            $('#description').val('');
            $('#rate').val('');
            $('#gross').val('');
            var currentTotal = Number($('#totals').val());
            currentTotal += Number(gross);
            $('#totals').val(currentTotal);
            calculateVat();
        });
        //remove
        $('#details').on('click','.btnRemove',function(){
            // $(this).closest("tr").remove();
            $tr = $(this).closest('tr');
            
            let data = $tr.children('td').map(function(){
                return $(this).text();
            }).get();

            
            var amountToDeduct = parseFloat(data[5]);
            var total = parseFloat($('#totals').val());
            console.log(amountToDeduct,total);
            var newAmount = total - amountToDeduct;
            console.log(amountToDeduct,total,newAmount);
            $('#totals').val(newAmount);
            calculateVat();

            $tr.remove();
        });
        //get vat rate
        function getRate(){
            var vat = $('#vat').val().trim();
            $.ajax({
                url : '<?php echo URLROOT;?>/invoices/getrate',
                method : 'POST',
                data : {vat : vat},
                success : function(html){
                    // console.log(html);
                    $('#vrate').val(html);
                }
            });
        }

        function getRate2(){
            var vat = $('#vat').val().trim();
            var rate;
            $.ajax({
                url : '<?php echo URLROOT;?>/invoices/getrate',
                method : 'POST',
                data : {vat : vat},
                success : function(html){
                   rate = parseFloat(html);
                }
            });
            return rate;
        }
        //get product rate
        function getProductRate(){
            var product = $('#product').val();
            $.ajax({
                url : '<?php echo URLROOT;?>/invoices/getproductrate',
                method : 'POST',
                data : {product : product},
                success : function(html){
                    // console.log(html);
                    $('#rate').val(html);
                }
            });
        }
        //calc vat
        function calculateVat(){
            var vattype = $('#vattype').val();
            if (Number(vattype) === 1) {
                $('#vatamount').val(0);
            }
            else if(Number(vattype) === 2){
                // var rate = parseFloat($('#vrate').val());
                var rate = ($('#vat').val()) / 100;
                var irate = rate + 1;
                var net = parseFloat($('#totals').val());
                var vat = (rate * parseFloat(net)) / irate;
                $('#vatamount').val(parseFloat(vat).toFixed(2));
                // var net = parseFloat($('#totals').val());
                // var vat = (0.16 * parseFloat(net)) / 1.16;
                // $('#vatamount').val(parseFloat(vat).toFixed(2));
            }
            else if (Number(vattype) === 3) {
                // getRate();
                // var rate = parseFloat($('#vrate').val());
                var rate = ($('#vat').val()) / 100;
                var net = parseFloat($('#totals').val());
                var vat = parseFloat(net) * rate;
                // console.log(rate);
                $('#vatamount').val(parseFloat(vat).toFixed(2));
                // var net = parseFloat($('#totals').val());
                // var vat = parseFloat(net) * 0.16;
                // $('#vatamount').val(parseFloat(vat).toFixed(2));
            }
        }
        //save
        $('#save').click(function(e){
        e.preventDefault();
        if ($('#supplier').val() == '' || $('#supplier').val() == null) {
            alert('Select supplier');
            return
        }
        if (Number($('#totals').val()) === 0) {
            alert('Nothing Added To Grid');
            return
        }

        var table_data = [];
        var id = $('#eid').val();
        var supplierId = $('#supplier').val();
        var invoicedate = $('#idate').val();
        var invoice = $('#invoiceno').val();
        var duedate = $('#duedate').val();
        var vattype = $('#vattype').val();
        // var vat = $('#vat').val();
        var vat = $('#vat').find('option:selected').text();
        var totals = $('#totals').val();

        $('#details tr').each(function(row,tr){
            if ($(tr).find('td:eq(0)').text() == '') {
                    
            }else{
                var sub = {
                    'pid' : $(tr).find('td:eq(0)').text(),
                    'pname' : $(tr).find('td:eq(1)').text(),
                    'desc' : $(tr).find('td:eq(2)').text(),
                    'qty' : $(tr).find('td:eq(3)').text(),
                    'rate' : $(tr).find('td:eq(4)').text(),
                    'gross' : $(tr).find('td:eq(5)').text(),
                }
                table_data.push(sub);
            }
        });

        $.ajax({
            url : '<?php echo URLROOT;?>/supplierinvoices/update',
            method : 'POST',
            data : {
                id : id,
                supplierId : supplierId,
                invoicedate : invoicedate,
                invoice : invoice,
                duedate : duedate,
                vattype : vattype,
                vat : vat,
                totals : totals,
                table_data : table_data
            },
            success : function(){
                window.location.href = '<?php echo URLROOT;?>/supplierinvoices';
            }
        });
    });
    });
</script>
</body>
</html>  