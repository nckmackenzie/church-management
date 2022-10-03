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
                <a href="<?php echo URLROOT;?>/congregations" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card card-body bg-light mt-2">
                    <h5>Add Congregation</h5>
                    <hr>
                    <form action="<?php echo URLROOT;?>/congregations/create" method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="">Congregation Name</label>
                                    <input type="text" name="congregationname"
                                    class="form-control form-control-sm mandatory
                                    <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''?>"
                                    value="<?php echo $data['congregationname'];?>"
                                    autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['name_err'];?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact">Contact</label>
                                    <input type="text" id="contact" name="contact"
                                    value="<?php echo $data['contact'];?>"
                                    class="form-control form-control-sm" autocomplete="off" maxlength="10">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email"
                                    value="<?php echo $data['email'];?>"
                                    class="form-control form-control-sm" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label for="prefix">Prefix</label>
                                    <input type="text" id="prefix" name="prefix"
                                    value="<?php echo $data['prefix'];?>"
                                    class="form-control form-control-sm" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <input type="text" id="address" name="address"
                                    value="<?php echo $data['address']?>"
                                    class="form-control form-control-sm" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="aboutus">About Us</label>
                                    <input type="text" id="aboutus" name="aboutus"
                                    value="<?php echo $data['aboutus'];?>"
                                    class="form-control form-control-sm" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="inauguration ">Inauguration Date</label>
                                    <input type="date" id="inauguration" name="inauguration"
                                    value="<?php echo $data['inauguration'];?>"
                                    class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="started">Year Started</label>
                                    <select name="started" id="started" 
                                            class="form-control form-control-sm mandatory 
                                            <?php echo (!empty($data['started_err'])) ? 'is-invalid' : '' ?>">
                                        <option value="" selected disabled>Select Year</option>
                                        <?php for ($nYear = 1990; $nYear <= date('Y'); $nYear++) : ?>
                                            <option value="<?php echo $nYear;?>" <?php selectdCheck($nYear,$data['started']) ?>><?php echo $nYear;?></option>
                                        <?php endfor; ?>    
                                    </select>
                                    <span class="invalid-feedback"><?php echo $data['started_err'];?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="type">Type Of Sanctuary</label>
                                    <select name="type" id="type" 
                                            class="form-control form-control-sm mandatory 
                                            <?php echo (!empty($data['type_err'])) ? 'is-invalid' : '' ?>">
                                        <option value="" selected disabled>Select Option</option>
                                        <option value="permanent" <?php selectdCheck('permanent',$data['type']) ?>>Permanent</option>
                                        <option value="semi" <?php selectdCheck('semi',$data['type']) ?>>Semi</option>
                                    </select>
                                    <span class="invalid-feedback"><?php echo $data['type_err'];?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="dedication">Dedication Date</label>
                                    <input type="date" id="dedication" name="dedication"
                                    value="<?php echo $data['dedication'];?>"
                                    class="form-control form-control-sm" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="foundation">Foundation Stone Date</label>
                                    <input type="date" id="foundation" name="foundation"
                                    value="<?php echo $data['foundation'];?>"
                                    class="form-control form-control-sm" disabled>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-2 mt-2">
                                <button type="submit" class="btn btn-block btn-sm bg-navy custom-font">Save</button>
                            </div>
                        </div> 
                    </form>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
  
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('#type').change(function(){
            var type = $(this).val();
            if(type === 'permanent'){
                $('#dedication').attr('disabled',false);
                $('#foundation').attr('disabled',false);
            }else{
                $('#dedication').attr('disabled',true);
                $('#foundation').attr('disabled',true);
            }
            $('#dedication').val('');
            $('#foundation').val('');
        });
    });
</script>
</body>
</html>