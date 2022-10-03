<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
 <!-- Content Wrapper. Contains page content -->
 <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <a href="<?php echo URLROOT;?>/groups" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card card-body bg-light mt-5">
                    <h5>Create Group</h5>
                <hr>
                    <form action="<?php echo URLROOT;?>/groups/create" method="post">
                        <div class="row">
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label for="groupName">Group Name</label>
                                    <input type="text" class="form-control mandatory form-control-sm
                                    <?php echo (!empty($data['name_err'])) ? 'is-invalid' : ''?>"
                                        id="groupName" 
                                        value="<?php echo $data['name'];?>"
                                        name="groupname"
                                        placeholder="Enter Group Name eg PCMF"
                                        autocomplete="off">
                                    <span class="invalid-feedback"><?php echo $data['name_err'];?></span>
                                </div>    
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-9">
                                <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="active" id="active" checked>
                                        <label class="form-check-label" for="active">Active</label>
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
</body>
</html>