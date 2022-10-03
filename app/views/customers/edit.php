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
          <a href="<?php echo URLROOT;?>/customers" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card card-body bg-light mt-5">
                    <h5>Create Customer</h5>
                    <hr>
                    <form action="<?php echo URLROOT;?>/customers/update" method="post">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="customername">Customer Name</label>
                                    <input type="text" name="customername" id="customername"
                                    class="form-control form-control-sm mandatory
                                    <?php echo (!empty($data['customername_err'])) ? 'is-invalid' : ''?>"
                                    value="<?php echo strtoupper($data['customer']->customerName);?>" autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['customername_err'];?></span>
                                </div>
                            </div>
                        </div>    
                        <div class="row">
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <input type="text" name="address" id="address"
                                    class="form-control form-control-sm"
                                    value="<?php echo strtoupper($data['customer']->address);?>" autocomplete="off">   
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="contact">Contact</label>
                                    <input type="text" name="contact" id="contact"
                                    class="form-control form-control-sm"
                                    value="<?php echo $data['customer']->contact;?>"
                                    maxlength="10" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="pin">Pin</label>
                                    <input type="text" name="pin" id="pin"
                                    class="form-control form-control-sm"
                                    value="<?php echo strtoupper($data['customer']->pin);?>" autocomplete="off"
                                    oninput="this.value = this.value.toUpperCase()"
                                    maxlength="11">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" name="email" id="email"
                                    class="form-control form-control-sm"
                                    value="<?php echo $data['customer']->email;?>" autocomplete="off"> 
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="contactperson">Contact Person</label>
                                    <input type="text" name="contactperson" id="contactperson"
                                    class="form-control form-control-sm mandatory
                                    <?php echo (!empty($data['contactperson_err'])) ? 'is-invalid' : ''?>"
                                    value="<?php echo strtoupper($data['customer']->contactPerson);?>" autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['contactperson_err'];?></span>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2 mt-2">
                                <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
                                <input type="hidden" name="id" value="<?php echo $data['customer']->ID;?>">
                            </div>
                        </div>   
                    </form>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
  

<?php require APPROOT . '/views/inc/footer.php'?>
</body>
</html>