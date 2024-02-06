<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="addModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Add Role</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/roles/addrole" method="post">
              <div class="row">
                <div class="col-md-9">
                  <div class="form-group">
                    <label for="">Role</label>
                    <input type="text" name="name" id="name" class="form-control form-control-sm" placeholder="eg Chairperson">
                  </div>
                </div>
              </div>
              <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
          </form>
      </div>
     
    </div>
  </div>
</div>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/roles" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <?php if(!empty($data['error'])) : ?>
            <div class="alert custom-danger alert-dismissible fade show" role="alert">
                <?php echo $data['error'];?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-12 mt-2" id="alertBox"></div>
            <div class="col-md-4 mx-auto">
                <div class="card card-light">
                    <div class="card-header">Role</div>
                    <div class="card-body">
                        <select name="role" id="role" class="form-control form-control-sm">
                            <option value="" selected disabled>Select role</option>
                            <option value="new">Add role</option>
                            <?php foreach($data['roles'] as $role): ?>
                                <option value="<?php echo $role->ID;?>"><?php echo ucwords($role->RoleName);?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="spinner-container justify-content-center"></div>
        <div class="row d-none" id="table-area">
            <div class="col-md-8 mx-auto">
                <form id="rights-form" method="post">
                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-sm-2">
                                    <button type="submit" class="btn btn-sm bg-navy btn-block btnsave">Save</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table class="table table-sm" id="rights-table">
                                            <thead class="bg-navy">
                                                <tr>
                                                    <th class="d-none">ID</th>
                                                    <th>Access</th>
                                                    <th>Form</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
         </div>
       
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/roles/index.js"></script>
</body>
</html>  