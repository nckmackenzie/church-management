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
            <a href="<?php echo URLROOT;?>/officials" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
               <?php if(!empty($data['err'])) : ?>
                    <div class="alert custom-danger alert-dismissible fade show" role="alert">
                        <strong>Error! </strong><?php echo $data['err'];?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
               <?php endif; ?>
               <div class="card bg-light">
                    <div class="card-header">Group Officials</div>
                        <form action="<?php echo URLROOT;?>/officials/update" method="post">
                            <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="year">Year</label>
                                        <select name="year" id="year" class="form-control form-control-sm" disabled>
                                            <?php foreach($data['years'] as $year) : ?>
                                                <option value="<?php echo $year->ID;?>"
                                                <?php selectdCheck($data['officials']->yearId,$year->ID)?>><?php echo $year->yearName;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="group">Group</label>            
                                        <select name="group" id="group" class="form-control form-control-sm" disabled>
                                            <?php foreach($data['groups'] as $group) : ?>
                                                    <option value="<?php echo $group->ID;?>"
                                                    <?php selectdCheck($data['officials']->groupId,$group->ID)?>><?php echo $group->groupName;?></option>
                                            <?php endforeach; ?>        
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="chairman">Chairman</label>
                                        <select name="chairman" id="chairman" class="form-control form-control-sm select2">
                                            <?php foreach($data['members'] as $member) : ?>
                                                    <option value="<?php echo $member->ID;?>"
                                                    <?php selectdCheck($data['officials']->chairmanId,$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach; ?>        
                                        </select>         
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vchairman">Vice Chairman</label>
                                        <select name="vchairman" id="vchairman" class="form-control form-control-sm select2">
                                            <?php foreach($data['members'] as $member) : ?>
                                                    <option value="<?php echo $member->ID;?>"
                                                    <?php selectdCheck($data['officials']->vchairmanId,$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach; ?>        
                                        </select>        
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="treasurer">Treasurer</label>
                                        <select name="treasurer" id="treasurer" class="form-control form-control-sm select2">
                                            <?php foreach($data['members'] as $member) : ?>
                                                    <option value="<?php echo $member->ID;?>"
                                                    <?php selectdCheck($data['officials']->treasurerId,$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach; ?>        
                                        </select>        
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vtreasurer">Vice Treasurer</label>
                                        <select name="vtreasurer" id="vtreasurer" class="form-control form-control-sm select2">
                                            <?php foreach($data['members'] as $member) : ?>
                                                    <option value="<?php echo $member->ID;?>"
                                                    <?php selectdCheck($data['officials']->vtreasurerId,$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach; ?>        
                                        </select>         
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="secretary">Secretary</label>
                                        <select name="secretary" id="secretary" class="form-control form-control-sm select2">
                                            <?php foreach($data['members'] as $member) : ?>
                                                    <option value="<?php echo $member->ID;?>"
                                                    <?php selectdCheck($data['officials']->secretaryId,$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach; ?>        
                                        </select>        
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="vsecretary">Vice Secretary</label>
                                        <select name="vsecretary" id="vsecretary" class="form-control form-control-sm select2">
                                            <?php foreach($data['members'] as $member) : ?>
                                                    <option value="<?php echo $member->ID;?>"
                                                    <?php selectdCheck($data['officials']->vsecretaryId,$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach; ?>        
                                        </select>         
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="patron">Patron</label>
                                        <select name="patron" id="patron" class="form-control form-control-sm select2">
                                            <?php foreach($data['members'] as $member) : ?>
                                                    <option value="<?php echo $member->ID;?>"
                                                    <?php selectdCheck($data['officials']->patronId,$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach; ?>        
                                        </select>         
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="groupname" id="groupname">
                                    <input type="hidden" name="yearname" id="yearname">
                                    <input type="hidden" name="id" value="<?php echo $data['officials']->ID;?>">       
                                </div>
                            </div>
                            </div><!--End Of Body -->
                        </form>    
                </div><!--End of Card -->
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        $('.select2').select2();

        function getNames(){
            var groupname = $('#group').find('option:selected').text();
            var yearname = $('#year').find('option:selected').text();
            $('#groupname').val(groupname);
            $('#yearname').val(yearname);
        }
        $('#chairman').change(function(){
           getNames();
        });
        $('#secretary').change(function(){
            getNames();
        });
        $('#treasurer').change(function(){
            getNames();
        });
        $('#group').change(function(){
            getNames();
        });
        $('#year').change(function(){
            getNames();
        });
        $(window).on('load', function() {
            getNames();
        });
    });
</script>
</body>
</html>