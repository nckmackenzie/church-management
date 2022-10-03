<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
         <div class="row">
             <div class="col-md-6 mx-auto mt-2">
                <form action="<?php echo URLROOT;?>/users/assignrights" method="post">
                    <div class="card bg-light">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-sm-6">
                                    <input type="hidden" name="userid"
                                           class="form-control form-control-sm" 
                                           value="<?php echo $data['user']->ID;?>">
                                    <input type="text" value="<?php echo $data['user']->UserName;?>" 
                                           class="form-control form-control-sm" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-12">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th style="display : none;">FormID</th>
                                                <th style="display : none;">Path</th>
                                                <th>Form Name</th>
                                                <th>Access</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($data['forms'] as $form) : ?>
                                                <tr>
                                                    <td style="display : none;">
                                                        <input type="text" name="fid[]" class="form-control form-control-sm" 
                                                               value="<?php echo $form->ID;?>">
                                                    </td>
                                                    <td style="display : none;">
                                                        <input type="text" name="path[]" class="form-control form-control-sm" 
                                                               value="<?php echo $form->Path;?>">
                                                    </td>
                                                    <td>
                                                        <input type="text" name="fname[]" class="form-control form-control-sm" 
                                                               value="<?php echo $form->FormName;?>" readonly>
                                                    </td>
                                                    <td>
                                                        <select name="access[]" class="form-control form-control-sm">
                                                            <option value="1" <?php selectdCheck($form->access,1) ?>>True</option>
                                                            <option value="0" <?php selectdCheck($form->access,0) ?>>False</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>   
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-2">
                                    <button type="submit" class="btn btn-block btn-sm bg-navy">Save</button>
                                </div>
                            </div>
                        </div>
                    </div><!--</Card -->
                </form>
             </div>
         </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
</body>
</html>  