<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <form method="post" id="form">
        <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-12">
                    <div id="alertBox"></div>
                </div>  
            <div class="col-sm-3">
                <label for="paymethod">Pay Method</label>
                <select name="paymethod" id="paymethod" class="form-control form-control-sm mandatory" >
                    <option value="1">Petty Cash</option>
                    <option value="3" selected>Cheque</option>
                    <option value="4">Bank</option>
                </select>
                <span class="invalid-feedback"></span>
            </div>
            <div class="col-sm-3">
                <label for="bank">Bank</label>
                <select name="bank" id="bank" class="form-control form-control-sm mandatory" >
                    <option value="" selected disabled>Select bank</option>
                    <?php foreach($data['banks'] as $bank) : ?>
                        <option value="<?php echo $bank->ID;?>"><?php echo $bank->Bank;?></option>
                    <?php endforeach; ?>
                </select>
                <span class="invalid-feedback"></span>
            </div>
            <div class="col-sm-3">
                <label for="date">Payment Date</label>
                <input type="date" id="date" class="form-control form-control-sm mandatory">
                <span class="invalid-feedback"></span>
            </div>
            </div>
            <div class="row">
                <div class="col-sm-2 align-self-center">
                    <input type="hidden" name="payid" id="payid" class="form-control form-control-sm" value="<?php echo $data['paymentno'];?>" readonly>
                    <button type="submit" class="btn btn-sm bg-navy btn-block btnsave">Save</button>
                </div>
                <div class="col-sm-4 d-flex flex-column ml-auto text-right">
                <span class="text-muted">Total Payment</span>
                <div class="h2 text-danger" id="total">0.00</div>
            </div>
            </div>
        </div><!-- /.container-fluid -->
        </section>

        <!-- Main content -->
        <section class="content">
            <div class="row">
                <div class="col-12">
                    <div class="table-responsive">
                        <table class="table table-sm" id="paymentsTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>Check</th>
                                    <th class="d-none">invoice_id</th>
                                    <th>Cheque No</th>
                                    <th class="d-none">Sid</th>
                                    <th>Supplier</th>
                                    <th>Invoice No</th>
                                    <th>Opening Bal</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <?php for($i = 0; $i < count($data['invoices']); $i++) : ?>
                                    <tr>
                                        <td>
                                            <div class="check-group">
                                                <input type="checkbox" class="chkbx" id="<?php echo $i ;?>">
                                                <label for="<?php echo $i;?>"></label>
                                            </div>  
                                        </td>
                                        <td class="d-none"><input type="number" class="invoiceid" value="<?php echo $data['invoices'][$i]->ID;?>"></td>
                                        <td><input type="text" class="table-input w-100 cheque" readonly></td>
                                        <td class="d-none"><input type="number" class="sid" value="<?php echo $data['invoices'][$i]->supplierId;?>"></td>
                                        <td><?php echo $data['invoices'][$i]->Supplier;?></td>
                                        <td><?php echo $data['invoices'][$i]->invoiceNo;?></td>
                                        <td><div class="balance"><?php echo $data['invoices'][$i]->Balance;?></div></td>
                                        <td><input type="number" class="table-input w-100 payment" readonly></td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section><!-- /.content -->
    </form>
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/suppliers/payment.js"></script>
</body>
</html>  