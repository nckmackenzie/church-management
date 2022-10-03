<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
      <?php flash('transfer_msg');?>
      <div class="row">
        <div class="col-md-12 mx-auto mt-1">
          <div class="card bg-light">
            <div class="card-header">Transfer Member</div>
            <div class="card-body">
              <form action="<?php echo URLROOT;?>/members/transfermember" method="post">
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="congregationfrom">Current Congregation</label>
                          <select name="congregationfrom" id="congregationfrom" 
                                  class="form-control">
                              <option value="" selected disabled>Select congregation</option>
                              <?php foreach($data['congregations'] as $congregation) : ?>
                                 <option value="<?php echo $congregation->ID;?>"
                                 <?php selectdCheck($data['congregationfrom'],$congregation->ID)?>>
                                    <?php echo $congregation->CongregationName;?>
                                 </option>
                              <?php endforeach; ?>
                          </select>
                          <span class="invalid-feedback"></span>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="district">Current District</label>
                          <select name="district" id="district" 
                                  class="form-control">
                          </select>
                          <span class="invalid-feedback"></span>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="member">Member(s)</label>
                          <select id="member" name="member[]"class="form-control"></select>
                          <span class="invalid-feedback"></span>
                      </div>
                    </div>
                  </div><!--End Of Row-->
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="newcongregation">New Congregation</label>
                          <select name="newcongregation" id="newcongregation" 
                                  class="form-control">
                              <option value="" selected disabled>Select congregation</option>    
                              <?php foreach($data['congregations'] as $congregation) : ?>
                                 <option value="<?php echo $congregation->ID;?>"
                                 <?php selectdCheck($data['newcongregation'],$congregation->ID)?>>
                                    <?php echo $congregation->CongregationName;?>
                                 </option>
                              <?php endforeach; ?>
                          </select>
                          <span class="invalid-feedback"></span>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="newdistrict">New District</label>
                          <select name="newdistrict" id="newdistrict" 
                                  class="form-control mandatory">
                            <?php if(!empty($data['districts'])) : ?>
                                  <?php foreach($data['districts'] as $district) : ?>
                                    <option value="<?php echo $district->ID;?>"
                                    <?php selectdCheck($data['newdistrict'],$district->ID)?>>
                                      <?php echo $district->districtName;?>
                                    </option>
                                  <?php endforeach; ?>
                              <?php endif;?>
                          </select>
                          <span class="invalid-feedback"></span>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                           <label for="date">Transfer Date</label>
                           <input type="date" name="date" id="date"
                                  class="form-control mandatory
                                  <?php echo (!empty($data['date_err'])) ? 'is-invalid' : ''?>"
                                  value="<?php echo $data['date'];?>">
                           <span class="invalid-feedback"></span>        
                      </div>
                    </div>
                  </div><!--End Of Row-->
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                            <label for="reason">Reason For Transfer</label>
                            <input type="text" name="reason" id="reason"  
                                  class="form-control mandatory
                                  <?php echo (!empty($data['reason_err'])) ? 'is-invalid' : ''?>"
                                  value="<?php echo $data['reason'];?>"
                                  placeholder="Enter Reason For Transfer"
                                  autocomplete="off">
                            <span class="invalid-feedback"></span>
                      </div>
                    </div>
                  </div><!--End Of Row-->
                  <div class="row">
                    <div class="col-3">
                      <button class="btn btn-sm bg-navy custom-font">Save</button>
                      <input type="hidden" name="membername" id="membername">
                      <input type="hidden" name="currentname" id="currentname">
                      <input type="hidden" name="newname" id="newname">
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/1.1.2/js/bootstrap-multiselect.min.js"></script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/members/transfers.js"></script>
</body>
</html>  