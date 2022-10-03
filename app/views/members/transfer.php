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
                                  class="form-control form-control-sm">
                              <?php foreach($data['congregations'] as $congregation) : ?>
                                 <option value="<?php echo $congregation->ID;?>"
                                 <?php selectdCheck($data['congregationfrom'],$congregation->ID)?>>
                                    <?php echo $congregation->CongregationName;?>
                                 </option>
                              <?php endforeach; ?>
                          </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="member">Member</label>
                          <select name="member" id="member" 
                                  class="form-control form-control-sm">
                              <?php if(!empty($data['members'])) : ?>
                                  <?php foreach($data['members'] as $member) : ?>
                                    <option value="<?php echo $member->ID;?>"
                                    <?php selectdCheck($data['member'],$member->ID)?>>
                                      <?php echo $member->memberName;?>
                                    </option>
                                  <?php endforeach; ?>
                              <?php endif;?>
                          </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="district">Current District</label>
                          <select name="district" id="district" 
                                  class="form-control form-control-sm">
                          </select>
                      </div>
                    </div>
                  </div><!--End Of Row-->
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="newcongregation">New Congregation</label>
                          <select name="newcongregation" id="newcongregation" 
                                  class="form-control form-control-sm
                                  <?php echo (!empty($data['newcong_err'])) ? 'is-invalid' : ''?>">
                              <?php foreach($data['congregations'] as $congregation) : ?>
                                 <option value="<?php echo $congregation->ID;?>"
                                 <?php selectdCheck($data['newcongregation'],$congregation->ID)?>>
                                    <?php echo $congregation->CongregationName;?>
                                 </option>
                              <?php endforeach; ?>
                          </select>
                          <span class="invalid-feedback"><?php echo $data['newcong_err'];?></span>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                          <label for="newdistrict">New District</label>
                          <select name="newdistrict" id="newdistrict" 
                                  class="form-control form-control-sm mandatory
                                  <?php echo (!empty($data['newdist_err'])) ? 'is-invalid' : ''?>">
                            <?php if(!empty($data['districts'])) : ?>
                                  <?php foreach($data['districts'] as $district) : ?>
                                    <option value="<?php echo $district->ID;?>"
                                    <?php selectdCheck($data['newdistrict'],$district->ID)?>>
                                      <?php echo $district->districtName;?>
                                    </option>
                                  <?php endforeach; ?>
                              <?php endif;?>
                          </select>
                          <span class="invalid-feedback"><?php echo $data['newdist_err'];?></span>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                           <label for="date">Transfer Date</label>
                           <input type="date" name="date" id="date"
                                  class="form-control form-control-sm mandatory
                                  <?php echo (!empty($data['date_err'])) ? 'is-invalid' : ''?>"
                                  value="<?php echo $data['date'];?>">
                           <span class="invalid-feedback"><?php echo $data['date_err'];?></span>        
                      </div>
                    </div>
                  </div><!--End Of Row-->
                  <div class="row">
                    <div class="col-md-12">
                      <div class="form-group">
                            <label for="reason">Reason For Transfer</label>
                            <input type="text" name="reason" id="reason"  
                                  class="form-control form-control-sm mandatory
                                  <?php echo (!empty($data['reason_err'])) ? 'is-invalid' : ''?>"
                                  value="<?php echo $data['reason'];?>"
                                  placeholder="Enter Reason For Transfer"
                                  autocomplete="off">
                            <span class="invalid-feedback"><?php echo $data['reason_err'];?></span>
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
<script>
  $(function(){
    $('#member').select2();

      $('#congregationfrom').change(function(){
          listMembers($(this).val());
          getNames();
      });

      $('#member').change(function(){
        var member = $(this).val();
          $.ajax({
              url  : '<?php echo URLROOT;?>/members/districtchange',
              method : 'POST',
              data : {member : member},
              success : function(html){
                // console.log(html);
                $('#district').html(html);
              }
          });
          getNames();
      });

      $('#newcongregation').change(function(){
          var cong = $(this).val();
          $.ajax({
              url  : '<?php echo URLROOT;?>/members/getdistrictbycong',
              method : 'POST',
              data : {cong : cong},
              success : function(html){
                // console.log(html);
                $('#newdistrict').html(html);
              }
          });
          getNames();
      });
      $('#newdistrict').change(function(){
          // var cong = $(this).val();
          // console.log(cong);
          getNames();
      });

      function listMembers(congregation){
         $.ajax({
            url  : '<?php echo URLROOT;?>/members/getmemberbycong',
            method : 'POST',
            data : {congregation : congregation},
            success : function(html){
              // console.log(html);
              $('#member').html(html);
            }
         });
      }
      
      $('#congregationfrom').val('');
      $('#newcongregation').val('');


      function getNames(){
        var memberName = $('#member').find('option:selected').text();
        var currentName = $('#congregationfrom').find('option:selected').text();
        var newcongregation = $('#newcongregation').find('option:selected').text();
        $('#membername').val(memberName.trim());
        $('#currentname').val(currentName.trim());
        $('#newname').val(newcongregation.trim());
      }
    
      $(document).ready(function(){
        listMembers($('#congregationfrom').val());
      });
     
    
      $(window).on('load',function(){
        listMembers($('#congregationfrom').val());
      });

      $('#reason').focusout(function(){
          getNames();
      });
  });
</script>
</body>
</html>  