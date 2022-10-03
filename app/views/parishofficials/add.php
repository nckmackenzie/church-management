<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card bg-light mt-2">
                    <div class="card-header">Add Officials</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/parishofficials/create" method="post">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="start">Start Date</label>
                                        <input type="date" name="start" id="start" 
                                               class="form-control form-control-sm mandatory 
                                               <?php echo (!empty($data['start_err'])) ? 'is-invalid' : '' ;?>"
                                               value="<?php echo $data['start'];?>">
                                        <span class="invalid-feedback"><?php echo $data['start_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="end">End Date</label>
                                        <input type="date" name="end" id="end" 
                                               class="form-control form-control-sm mandatory 
                                               <?php echo (!empty($data['end_err'])) ? 'is-invalid' : '' ;?>"
                                               value="<?php echo $data['end'];?>">
                                        <span class="invalid-feedback"><?php echo $data['end_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="pminister">Parish Minister</label>
                                        <select name="pminister" id="pminister" 
                                                class="form-control form-control-sm mandatory 
                                                       <?php echo (!empty($data['pminister_err'])) ? 'is-invalid' : '' ;?>">
                                            <option value="" selected disabled>Select Parish Minister</option>
                                            <?php foreach($data['members'] as $member) : ?>
                                                <option value="<?php echo $member->ID;?>" <?php selectdCheck($data['pminister'],$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach;?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['pminister_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="sclerk">Session Clerk</label>
                                        <select name="sclerk" id="sclerk" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['sclerk_err'])) ? 'is-invalid' : '' ;?>">
                                            <option value="" selected disabled>Select Session Clerk</option>
                                            <?php foreach($data['members'] as $member) : ?>
                                                <option value="<?php echo $member->ID;?>" <?php selectdCheck($data['sclerk'],$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach;?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['sclerk_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fchair">Finance Chair</label>
                                        <select name="fchair" id="fchair" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['fchair_err'])) ? 'is-invalid' : '' ;?>">
                                            <option value="" selected disabled>Select Finance Chair</option>
                                            <?php foreach($data['members'] as $member) : ?>
                                                <option value="<?php echo $member->ID;?>" <?php selectdCheck($data['fchair'],$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach;?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['fchair_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="treasurer">Treasurer</label>
                                        <select name="treasurer" id="treasurer" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['treasurer_err'])) ? 'is-invalid' : '' ;?>">
                                            <option value="" selected disabled>Select Finance Chair</option>
                                            <?php foreach($data['members'] as $member) : ?>
                                                <option value="<?php echo $member->ID;?>" <?php selectdCheck($data['treasurer'],$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach;?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['treasurer_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="pelder">Pairing Elder</label>
                                        <select name="pelder" id="pelder" 
                                                class="form-control form-control-sm mandatory 
                                                <?php echo (!empty($data['pelder_err'])) ? 'is-invalid' : '' ;?>">
                                            <option value="" selected disabled>Select Pairing Elder</option>
                                            <?php foreach($data['members'] as $member) : ?>
                                                <option value="<?php echo $member->ID;?>" <?php selectdCheck($data['pelder'],$member->ID)?>><?php echo $member->memberName;?></option>
                                            <?php endforeach;?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['pelder_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <button type="submit" class="btn btn-block btn-sm bg-navy">Save</button>
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
    $('#pminister').val('');
</script>
</body>
</html>  