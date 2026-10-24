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
          <a href="<?php echo URLROOT;?>/suppliers" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto" id="alertBox"></div>
        </div>
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-header"><?php echo htmlspecialchars((string)$data['title'],ENT_QUOTES,'UTF-8');?></div>
                    <div class="card-body">    
                        <form action="" method="post" id="suppliers-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="suppliername">Supplier Name</label>
                                        <input type="text" name="suppliername" id="suppliername"
                                            class="form-control form-control-sm mandatory"
                                            value="<?php echo htmlspecialchars((string)$data['suppliername'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="contact">Contact</label>
                                        <input type="text" name="contact" id="contact"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars((string)$data['contact'],ENT_QUOTES,'UTF-8');?>" maxlength="10" autocomplete="off">  
                                        <span class="invalid-feedback"></span> 
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="pin">PIN</label>
                                        <input type="text" name="pin" id="pin"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars((string)$data['pin'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">   
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="address">Address</label>
                                        <input type="address" name="address" id="address"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars((string)$data['address'],ENT_QUOTES,'UTF-8');?>" autocomplete="off"> 
                                        <span class="invalid-feedback"></span>      
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" name="email" id="email"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars((string)$data['email'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">   
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contactperson">Contact Person</label>
                                        <input type="text" name="contactperson" id="contactperson"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars((string)$data['contactperson'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">   
                                        <span class="invalid-feedback"></span>    
                                    </div>
                                </div>
                                <?php $ro = $data['balancelocked'] ? 'readonly' : ''; ?>
                                <?php if($data['balancelocked']) : ?>
                                    <div class="col-12"><div class="alert alert-warning">The opening balance has payments, so its amount, date and crediting account cannot be changed.</div></div>
                                <?php endif; ?>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="openingbal">Opening Balance</label>
                                        <input type="number" step="0.01" name="openingbal" id="openingbal"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars((string)$data['openingbal'],ENT_QUOTES,'UTF-8');?>" autocomplete="off" <?php echo $ro;?>>
                                        <small class="form-text text-muted">Positive: we owe the supplier. Negative: the supplier owes us.</small>
                                        <span class="invalid-feedback"></span>   
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="asof">As Of</label>
                                        <input type="date" name="asof" id="asof" max="<?php echo date('Y-m-d');?>"
                                            class="form-control form-control-sm"
                                            value="<?php echo htmlspecialchars((string)$data['asof'],ENT_QUOTES,'UTF-8');?>" autocomplete="off" <?php echo $ro;?>>
                                        <span class="invalid-feedback"></span>   
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="payable">Crediting account</label>
                                        <select name="payable" id="payable" class="form-control form-control-sm" >
                                            <option value="" <?php echo $data['payable'] === '' ? 'selected' : '';?>>Select account</option>
                                            <?php foreach($data['payables'] as $payable) : ?>
                                                <option value="<?php echo (int)$payable->ID;?>" <?php selectdCheck($data['payable'],$payable->ID);?>>
                                                    <?php echo htmlspecialchars(strtoupper($payable->accountType),ENT_QUOTES,'UTF-8');?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"></span>   
                                    </div>
                                </div>
                            </div>    
                            <div class="row">
                                <div class="col-md-2 mt-2">
                                    <input type="hidden" name="id" id="id" value="<?php echo (int)$data['id'];?>">
                                    <input type="hidden" name="isedit" id="isedit" value="<?php echo $data['isedit'] ? '1' : '';?>">
                                    <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/suppliers/add-v2.js"></script>
</body>
</html>
  

