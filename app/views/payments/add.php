<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<?php $e = function($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }; ?>
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
                <div class="form-group">
                    <label for="paymethod">Pay Method</label>
                    <select name="paymethod" id="paymethod" class="form-control form-control-sm mandatory" >
                        <?php foreach($data['paymethods'] as $method) : ?>
                            <option value="<?php echo (int)$method->ID;?>" <?php echo (int)$method->ID === 3 ? 'selected' : '';?>><?php echo $e($method->paymentMethod);?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"></span>
                </div>
            </div>
            <div class="col-sm-3" id="cashgroup">
                <div class="form-group">
                    <label for="cashaccount">Cash account</label>
                    <select name="cashaccount" id="cashaccount" class="form-control form-control-sm" disabled>
                        <option value="" selected disabled>Select cash account</option>
                        <?php foreach($data['cashaccounts'] as $account) : ?>
                            <option value="<?php echo (int)$account->ID;?>"><?php echo $e(strtoupper($account->accountType));?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"></span>
                </div>
            </div>
            <div class="col-sm-3" id="bankgroup">
                <div class="form-group">
                    <label for="bank">Bank</label>
                    <select name="bank" id="bank" class="form-control form-control-sm mandatory" >
                        <option value="" selected disabled>Select bank</option>
                        <?php foreach($data['banks'] as $bank) : ?>
                            <option value="<?php echo (int)$bank->ID;?>"><?php echo $e(strtoupper($bank->accountType));?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="invalid-feedback"></span>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label for="date">Payment Date</label>
                    <input type="date" id="date" max="<?php echo date('Y-m-d');?>" class="form-control form-control-sm mandatory">
                    <span class="invalid-feedback"></span>
                </div>
            </div>
            </div>
            <div class="row">
                <div class="col-sm-2 align-self-center">
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
                                    <th>Reference / Cheque No</th>
                                    <th class="d-none">Sid</th>
                                    <th>Supplier</th>
                                    <th>Invoice No</th>
                                    <th>Crediting account</th>
                                    <th>Balance</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <?php for($i = 0; $i < count($data['invoices']); $i++) : ?>
                                    <?php $invoice = $data['invoices'][$i]; $payable = !is_null($invoice->PayableAccountId); ?>
                                    <tr>
                                        <td>
                                            <div class="check-group">
                                                <input type="checkbox" class="chkbx" id="<?php echo $i ;?>" <?php echo $payable ? '' : 'disabled';?>>
                                                <label for="<?php echo $i;?>"></label>
                                            </div>
                                        </td>
                                        <td class="d-none"><input type="number" class="invoiceid" value="<?php echo (int)$invoice->ID;?>"></td>
                                        <td><input type="text" class="table-input w-100 cheque" maxlength="50" readonly></td>
                                        <td class="d-none"><input type="number" class="sid" value="<?php echo (int)$invoice->supplierId;?>"></td>
                                        <td><?php echo $e($invoice->Supplier);?></td>
                                        <td><?php echo is_null($invoice->invoiceNo) ? 'OPENING BALANCE' : $e($invoice->invoiceNo);?></td>
                                        <?php if($payable) : ?>
                                            <td><?php echo $e($invoice->payable);?></td>
                                        <?php else : ?>
                                            <td class="text-danger">None recorded (entered before the ledger migration): cannot be paid</td>
                                        <?php endif; ?>
                                        <td><div class="balance"><?php echo number_format($invoice->Balance,2,'.','');?></div></td>
                                        <td><input type="number" step="0.01" min="0.01" class="table-input w-100 payment" readonly></td>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/suppliers/payment-v2.js"></script>
</body>
</html>
