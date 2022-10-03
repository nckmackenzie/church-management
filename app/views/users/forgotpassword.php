<?php require APPROOT . '/views/inc/header.php';?>
<body class="hold-transition login-page">
<div class="login-box">
  <!-- /.login-logo -->
  <div class="card card-outline card-navy">
    <div class="card-header text-center">
      FORGOT PASSWORD
    </div>
    <div class="card-body">
      <p class="login-box-msg">Enter Your Mobile No To Reset Password</p>

      <form action="<?php echo URLROOT;?>/users/resendpassword" method="post">
        <div class="input-group mb-3">
          <input type="text" class="form-control form-control-sm mandatory
                <?php echo (!empty($data['phone_err'])) ? 'is-invalid' : ''?>"
                 name="phone" 
                 value="<?php echo $data['phone'];?>"
                 placeholder="Enter Phone Number"
                 maxlength="10">
          <span class="invalid-feedback"><?php echo $data['phone_err'];?></span>       
          
        </div>
        <div class="row">
          <div class="col-md-12 mb-2">
            <button type="submit" class="btn bg-navy">Reset Password</button>
          </div>
          <!-- /.col -->
        </div>
      </form>
    </div>
    <!-- /.card-body -->
  </div>
  <!-- /.card -->
</div>
</body>
</html>