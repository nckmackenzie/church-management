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
            <a href="<?php echo URLROOT;?>/expenses" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Edit Expense</div>
                    <div class="card-body">
                        <?php if(!empty($data['errors'])) : ?>
                            <div class="alert custom-danger alert-dismissible fade show" role="alert">
                                <?php foreach($data['errors'] as $error) : ?>
                                    <div><?php echo htmlspecialchars($error);?></div>
                                <?php endforeach; ?>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                            </div>
                        <?php endif; ?>
                        <form action="<?php echo URLROOT;?>/expenses/update" id="expenseform" name="expenseform" method="post" data-mode="edit" data-expense-id="<?php echo (int)$data['expense']->ID;?>" data-cash-holding="<?php echo Expense::CASH_HOLDING;?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date">Date</label>
                                        <input type="date" name="date" id="date"
                                            class="form-control form-control-sm mandatory"
                                            value="<?php echo htmlspecialchars((string)$data['date']);?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="voucher">Voucher No</label>
                                        <input type="text" id="voucher" class="form-control form-control-sm"
                                               value="<?php echo htmlspecialchars((string)$data['expense']->voucherNo);?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="expensetype">Expense type</label>
                                        <select name="expensetype" id="expensetype" class="form-control form-control-sm">
                                            <option value="1" <?php echo (int)$data['expensetype'] === 1 ? 'selected' : '';?>>Church Expense</option>
                                            <option value="2" <?php echo (int)$data['expensetype'] === 2 ? 'selected' : '';?>>Group Expense</option>
                                            <option value="3" <?php echo (int)$data['expensetype'] === 3 ? 'selected' : '';?>>District Expense</option>
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account">Account</label>
                                        <select name="account" id="account" class="form-control form-control-sm select2">
                                            <option value="" disabled <?php echo empty($data['account']) ? 'selected' : '';?>>Select account</option>
                                            <?php foreach($data['accounts'] as $account) :?>
                                                <option value="<?php echo $account->ID;?>" <?php echo (int)$data['account'] === (int)$account->ID ? 'selected' : '';?>>
                                                    <?php echo htmlspecialchars($account->accountType);?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="costcentre">Cost Centre</label>
                                        <select name="costcentre" id="costcentre" class="form-control form-control-sm">
                                            <?php if((int)$data['expensetype'] === 1) : ?>
                                                <option value="0">CHURCH</option>
                                            <?php else : ?>
                                                <option value="" disabled <?php echo empty($data['costcentre']) ? 'selected' : '';?>>Select</option>
                                                <?php foreach($data['costcentres'] as $centre) : ?>
                                                    <option value="<?php echo $centre->ID;?>" <?php echo (int)$data['costcentre'] === (int)$centre->ID ? 'selected' : '';?>>
                                                        <?php echo htmlspecialchars($centre->cost_center);?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" step="0.01" name="amount" id="amount"
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo htmlspecialchars((string)$data['amount']);?>"
                                               autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="paymethod">Pay Method</label>
                                        <select name="paymethod" id="paymethod" class="form-control form-control-sm">
                                            <?php foreach($data['paymethods'] as $paymethod) :?>
                                                <option value="<?php echo $paymethod->ID;?>" <?php echo (int)$data['paymethod'] === (int)$paymethod->ID ? 'selected' : '';?>>
                                                    <?php echo strtoupper($paymethod->paymentMethod);?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="paidfrom">Paid from</label>
                                        <select name="paidfrom" id="paidfrom" class="form-control form-control-sm mandatory">
                                            <option value="" disabled <?php echo empty($data['paidfrom']) ? 'selected' : '';?>>Select account</option>
                                            <?php foreach($data['paidfroms'] as $paidfrom) : ?>
                                                <option value="<?php echo $paidfrom->ID;?>" <?php echo (int)$data['paidfrom'] === (int)$paidfrom->ID ? 'selected' : '';?>>
                                                    <?php echo htmlspecialchars($paidfrom->Account);?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="reqid">Requisition</label>
                                        <select name="reqid" id="reqid" class="form-control form-control-sm"
                                                <?php echo empty($data['requisitions']) && empty($data['reqid']) ? 'disabled' : '';?>>
                                            <option value="">No requisition</option>
                                            <?php foreach($data['requisitions'] as $req) : ?>
                                                <option value="<?php echo $req->ID;?>" data-style="<?php echo htmlspecialchars((string)$req->Style);?>"
                                                    <?php echo (int)$data['reqid'] === (int)$req->ID ? 'selected' : '';?>>
                                                    <?php echo htmlspecialchars(strtoupper($req->Label));?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" id="reference" name="reference" maxlength="50"
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo htmlspecialchars((string)$data['reference']);?>"
                                               placeholder="eg MPESA Reference Or Cheque No"
                                               autocomplete="off">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="description">Description</label>
                                        <input type="text" id="description" name="description" maxlength="50"
                                               class="form-control form-control-sm mandatory"
                                               value="<?php echo htmlspecialchars((string)$data['description']);?>"
                                               placeholder="Brief Description"
                                               autocomplete="off">
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-4">
                                    <button id="save-btn" type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo (int)$data['expense']->ID;?>">
                                </div>
                            </div>
                        </form>
                    </div><!--End Card-->
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('#account').select2();
    });
</script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/expenses/add-expense-v8.js"></script>
</body>
</html>
