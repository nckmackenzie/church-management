<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<?php $e = function($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }; ?>
<!-- Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Add Product</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/supplierinvoices/saveproduct" method="post" autocomplete="off" id="productForm">
               <div class="row">
                <div class="col-12 modalAlert"></div>
                   <div class="col-md-12">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" name="name" id="name"
                                   class="form-control form-control-sm modalrequired"
                                   placeholder="Enter Product Name"
                                   autocomplete="off">
                            <span class="invalid-feedback" id="name_err"></span>
                        </div>
                   </div>
               </div>
               <div class="row">
                   <div class="col-md-12">
                        <div class="form-group">
                            <label for="desc">Description</label>
                            <input type="text" name="desc" id="desc"
                                   class="form-control form-control-sm"
                                   placeholder="Enter Brief Description"
                                   autocomplete="off">
                        </div>
                   </div>
               </div>
               <div class="row">
                   <div class="col-md-6">
                       <div class="form-group">
                            <label for="sellingprice">Rate</label>
                            <input type="number" name="sellingprice" id="sellingprice" step="0.01" min="0.01"
                                   class="form-control form-control-sm modalrequired"
                                   autocomplete="off"
                                   placeholder="eg 5,000">
                            <span id="rate_err" class="invalid-feedback"></span>
                       </div>
                   </div>
                   <div class="col-md-6">
                       <div class="form-group">
                            <label for="account">Associated G/L Account</label>
                            <select name="account" id="account" class="form-control form-control-sm modalrequired">
                                <option value="">Select account</option>
                                <?php foreach($data['accounts'] as $account) : ?>
                                    <option value="<?php echo (int)$account['ID'];?>">
                                        <?php echo $e($account['accountType']);?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="invalid-feedback" id="account_err"></span>
                       </div>
                   </div>
               </div>
               <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="newproduct">Save</button>
               </div>
          </form>
      </div>
    </div>
  </div>
</div>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12 mt-2" id="alertBox"></div>
            <div class="col-md-12 mt-1">
                <div class="card bg-light">
                    <div class="card-header"><?php echo $data['isedit'] ? 'Edit Invoice' : 'Add Invoice';?></div>
                    <div class="card-body">
                        <?php if($data['haspayments']) : ?>
                            <div class="alert alert-warning">This invoice has payments: only the due date and the invoice number can be changed.</div>
                        <?php endif; ?>
                        <form action="" method="post" id="invoiceForm" data-locked="<?php echo $data['haspayments'] ? '1' : '';?>">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="supplier">Supplier</label>
                                        <select name="supplier" id="supplier"
                                                class="form-control form-control-sm mandatory">
                                            <option value="" selected disabled>Select Supplier</option>
                                            <?php foreach($data['suppliers'] as $supplier) : ?>
                                                <option value="<?php echo (int)$supplier->ID;?>" <?php selectdCheck($data['supplier'],$supplier->ID);?>>
                                                    <?php echo $e($supplier->supplierName); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="text" id="email"
                                               class="form-control form-control-sm" value="<?php echo $e($data['email']);?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="pin">PIN</label>
                                        <input type="text" id="pin"
                                               class="form-control form-control-sm" value="<?php echo $e($data['pin']);?>" readonly>
                                    </div>
                                </div>
                            </div><!--end of row -->
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="idate">Invoice Date</label>
                                        <input type="date" name="idate" id="idate" max="<?php echo date('Y-m-d');?>"
                                               class="form-control form-control-sm mandatory" value="<?php echo $e($data['idate']);?>">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="duedate">Due Date</label>
                                        <input type="date" name="duedate" id="duedate"
                                               class="form-control form-control-sm mandatory" value="<?php echo $e($data['ddate']);?>">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vtype">V.A.T Type</label>
                                        <select name="vattype" id="vattype"
                                                class="form-control form-control-sm">
                                            <option value="1" <?php selectdCheck($data['vattype'],1);?>>No Vat</option>
                                            <option value="2" <?php selectdCheck($data['vattype'],2);?>>Inclusive</option>
                                            <option value="3" <?php selectdCheck($data['vattype'],3);?>>Exclusive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vat">V.A.T</label>
                                        <select name="vat" id="vat" class="form-control form-control-sm" <?php echo (int)$data['vattype'] > 1 ? '' : 'disabled';?>>
                                            <?php foreach($data['vats'] as $vat) : ?>
                                                <option value="<?php echo $e($vat->rate);?>" <?php selectdCheck($data['vat'],$vat->rate);?>>
                                                    <?php echo $e($vat->vatName); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="invoiceno">Invoice #</label>
                                        <input type="text" class="form-control form-control-sm mandatory" name="invoiceno" id="invoiceno" maxlength="50" value="<?php echo $e($data['invoiceno']);?>">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="payable">Crediting account</label>
                                        <select name="payable" id="payable" class="form-control form-control-sm mandatory">
                                            <option value="" <?php echo $data['payable'] === '' ? 'selected' : '';?> disabled>Select account</option>
                                            <?php foreach($data['payables'] as $payable) : ?>
                                                <option value="<?php echo (int)$payable->ID;?>" <?php selectdCheck($data['payable'],$payable->ID);?>>
                                                    <?php echo $e(strtoupper($payable->accountType)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <hr>
                            <div class="row">
                                <div class="col-md-5">
                                    <div class="form-group">
                                       <label for="product">Product</label>
                                       <select name="product" id="product"
                                                class="form-control form-control-sm addcontrol">
                                                <option value="" selected disabled>Select product</option>
                                            <option value="0" style="background-color: #a7f3d0; color :black;"><span class="selectspan">Add NEW</span></option>
                                            <?php foreach($data['products'] as $product) : ?>
                                                <option value="<?php echo (int)$product->ID;?>">
                                                    <?php echo $e($product->productName);?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                        <small class="form-text text-muted" id="lineaccount"></small>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="">Qty</label>
                                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm addcontrol" id="qty">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="rate">Rate</label>
                                        <input type="number" step="0.01" min="0.01" id="rate" class="form-control form-control-sm addcontrol">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="gross">Gross</label>
                                        <input type="text" class="form-control form-control-sm"
                                           id="gross" readonly>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="form-group">
                                        <label for="description" style="color: #F4F6F9;">Description</label>
                                        <button type="button" id="add"
                                        class="btn btn-sm btn-success custom-font form-control form-control-sm">Add</button>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="total">Total</label>
                                        <input type="text" id="totals"
                                           class="form-control form-control-sm" readonly>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label for="vatamount">VAT</label>
                                        <input type="text" id="vatamount"
                                           class="form-control form-control-sm" readonly>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                           <hr>
                           <div class="row">
                               <div class="col-md-12 table-responsive mt-2">
                                    <table id="details" class="table table-bordered table-sm table-striped">
                                        <thead class="bg-navy">
                                            <tr>
                                                <th class="d-none">Product</th>
                                                <th width="20%">Product</th>
                                                <th width="20%">Account</th>
                                                <th width="5%">Qty</th>
                                                <th width="10%">Rate</th>
                                                <th width="10%">Gross</th>
                                                <th width="5%"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($data['table'] as $detail) : ?>
                                                <tr>
                                                    <td class="d-none pid"><?php echo (int)$detail['pid'];?></td>
                                                    <td><?php echo $e($detail['pname']);?></td>
                                                    <td class="account"><?php echo $e($detail['account']);?></td>
                                                    <td class="qty"><?php echo $e($detail['qty']);?></td>
                                                    <td class="rate"><?php echo $e($detail['rate']);?></td>
                                                    <td class="gross"><?php echo $e($detail['gross']);?></td>
                                                    <td class="btnremove"><button type="button" class="tablebtn text-danger btnremove">Remove</button></td>
                                                </tr>
                                            <?php endforeach;?>
                                        </tbody>
                                    </table>
                               </div>
                           </div>
                           <hr>
                           <div class="row">
                                <div class="col-3">
                                    <button type="submit" id="save" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" id="vrate" name="vrate">
                                    <input type="hidden" id="id" name="id" value="<?php echo $e($data['id']);?>">
                                    <input type="hidden" id="isedit" name="isedit" value="<?php echo $data['isedit'] ? '1' : '';?>">
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/invoices/supplier-v2.js"></script>
</body>
</html>
