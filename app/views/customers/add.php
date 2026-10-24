<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid"><a href="<?php echo URLROOT;?>/customers" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a></div>
  </section>
  <section class="content">
    <div class="row"><div class="col-md-6 mx-auto" id="alertBox"></div></div>
    <div class="row">
      <div class="col-md-6 mx-auto">
        <div class="card card-body bg-light mt-5">
          <h5><?php echo htmlspecialchars((string)$data['title'],ENT_QUOTES,'UTF-8');?></h5>
          <hr>
          <form action="" method="post" id="customers-form" data-url="<?php echo URLROOT;?>/customers/<?php echo $data['isedit'] ? 'update' : 'create';?>">
            <div class="row">
              <div class="col-md-12"><div class="form-group">
                <label for="customername">Customer Name</label>
                <input type="text" name="customername" id="customername" maxlength="255" class="form-control form-control-sm mandatory" value="<?php echo htmlspecialchars((string)$data['customername'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">
                <span class="invalid-feedback"></span>
              </div></div>
            </div>
            <div class="row">
              <div class="col-md-9"><div class="form-group">
                <label for="address">Address</label>
                <input type="text" name="address" id="address" maxlength="255" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$data['address'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">
                <span class="invalid-feedback"></span>
              </div></div>
              <div class="col-md-3"><div class="form-group">
                <label for="contact">Contact</label>
                <input type="text" name="contact" id="contact" maxlength="20" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$data['contact'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">
                <span class="invalid-feedback"></span>
              </div></div>
            </div>
            <div class="row">
              <div class="col-md-6"><div class="form-group">
                <label for="pin">PIN</label>
                <input type="text" name="pin" id="pin" maxlength="15" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$data['pin'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">
                <span class="invalid-feedback"></span>
              </div></div>
              <div class="col-md-6"><div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" maxlength="255" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$data['email'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">
                <span class="invalid-feedback"></span>
              </div></div>
            </div>
            <div class="row">
              <div class="col-md-12"><div class="form-group">
                <label for="contactperson">Contact Person</label>
                <input type="text" name="contactperson" id="contactperson" maxlength="255" class="form-control form-control-sm mandatory" value="<?php echo htmlspecialchars((string)$data['contactperson'],ENT_QUOTES,'UTF-8');?>" autocomplete="off">
                <span class="invalid-feedback"></span>
              </div></div>
            </div>
            <input type="hidden" name="id" id="id" value="<?php echo (int)$data['id'];?>">
            <div class="row"><div class="col-md-2 mt-2"><button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button></div></div>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/customers/add-v1.js"></script>
</body>
</html>
