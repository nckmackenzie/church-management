<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
         <div class="row">
            <div class="col-12" id="alertBox"></div>
            <div class="col-md-6 col-lg-4 mx-auto mt-2">
                <div class="card">
                    <div class="card-body">
                        <label for="user">Select user</label>
                        <select style="width: 100%" name="user" id="user" class="form-control form-control-sm select2 mandatory">
                            <option value="" selected disabled>Select user</option>
                            <?php foreach($data['users'] as $user) : ?>
                                <option value="<?php echo $user->ID;?>"><?php echo $user->UserName;?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="invalid-feedback"></span>
                    </div>
                </div>
            </div>
         </div>
         <div class="spinner-container justify-content-center">
            
         </div>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/users/userrights.js"></script>
</body>
</html>  