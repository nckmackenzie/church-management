<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<?php $e = function($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }; $inv = $data['invoice']; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
             <a href="<?php echo URLROOT;?>/invoices" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <?php if($data['errors']) : ?>
                    <div class="alert custom-danger" role="alert" id="payerrors">
                        <?php foreach($data['errors'] as $error) : ?><div><?php echo $e($error);?></div><?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if(is_null($inv->ReceivableAccountId)) : ?>
                    <div class="alert alert-warning">This invoice has no receivable account recorded (it was entered before the ledger migration), so it cannot be paid. Ask the administrator to correct it.</div>
                <?php endif; ?>
                <div class="card bg-light">
                    <div class="card-header">Invoice Payment</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/invoices/payment" method="post" id="payForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Customer</label>
                                        <input type="text" class="form-control form-control-sm" value="<?php echo $e($inv->customerName);?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Invoice #</label>
                                        <input type="text" class="form-control form-control-sm" value="<?php echo $e($inv->invoiceNo);?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Invoice Amount</label>
                                        <input type="text" class="form-control form-control-sm" value="<?php echo number_format($inv->inclusiveVat,2);?>" readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Receivable account (credited)</label>
                                        <input type="text" class="form-control form-control-sm" value="<?php echo $e($inv->receivable);?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Balance</label>
                                        <input type="text" class="form-control form-control-sm" value="<?php echo number_format($inv->balance,2);?>" readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="paydate">Payment Date</label>
                                        <input type="date" name="paydate" id="paydate" max="<?php echo date('Y-m-d');?>" class="form-control form-control-sm mandatory" value="<?php echo $e($data['paydate']);?>">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control form-control-sm mandatory"
                                               value="<?php echo $e($data['amount']);?>" placeholder="Payment Amount" autocomplete="off">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="paymethod">Payment Method</label>
                                        <select name="paymethod" id="paymethod" class="form-control form-control-sm mandatory">
                                            <?php foreach($data['paymethods'] as $paymethod) : ?>
                                                <option value="<?php echo (int)$paymethod->ID;?>" <?php selectdCheck($data['paymethod'],$paymethod->ID)?>>
                                                    <?php echo $e(strtoupper($paymethod->paymentMethod));?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-4" id="cashgroup">
                                    <div class="form-group">
                                        <label for="cashaccount">Cash account</label>
                                        <select name="cashaccount" id="cashaccount" class="form-control form-control-sm">
                                            <option value="" selected disabled>Select cash account</option>
                                            <?php foreach($data['cashaccounts'] as $account) : ?>
                                                <option value="<?php echo (int)$account->ID;?>" <?php selectdCheck($data['cashaccount'],$account->ID)?>><?php echo $e(strtoupper($account->accountType));?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-4" id="bankgroup">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" class="form-control form-control-sm">
                                            <option value="" selected disabled>Select bank</option>
                                            <?php foreach($data['banks'] as $bank) : ?>
                                                <option value="<?php echo (int)$bank->ID;?>" <?php selectdCheck($data['bank'],$bank->ID)?>><?php echo $e(strtoupper($bank->accountType));?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference" maxlength="50" class="form-control form-control-sm"
                                               value="<?php echo $e($data['reference']);?>" placeholder="Payment Reference eg Cheque No" autocomplete="off">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font" id="save" <?php echo is_null($inv->ReceivableAccountId) ? 'disabled' : '';?>>Save</button>
                                    <input type="hidden" name="id" value="<?php echo (int)$inv->ID;?>">
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/invoices/customer-pay-v1.js"></script>
</body>
</html>
