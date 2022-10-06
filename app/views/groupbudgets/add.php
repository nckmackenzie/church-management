<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid"></div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <?php if(!empty($data['errmsg'])) : ?>
                    <?php echo alert($data['errmsg']); ?>
                <?php endif;?>
                <form action="<?php echo URLROOT;?>/groupbudgets/createupdate" autocomplete="off" method="post">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <p class="align-self-center m-0">Add Budget</p>
                            <input type="hidden" name="id" id="id" value="<?php echo $data['id'];?>">
                            <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>">
                            <button type="submit" class="btn btn-sm bg-navy custom-font ml-auto savebtn">Save</button>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <label for="year">Fiscal Year</label>
                                    <select name="year" id="year" class="form-control form-control-sm mandatory" <?php echo $data['isedit'] ? 'disabled' : '';?>>
                                        <option value="" selected disabled>Select fiscal year</option>
                                        <?php foreach($data['years'] as $year) : ?>
                                            <option value="<?php echo $year->ID;?>" <?php selectdCheck($year->ID,$data['year']);?>><?php echo $year->yearName;?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="col-6">
                                    <label for="group">Group</label>
                                    <select name="group" id="group" class="form-control form-control-sm mandatory" <?php echo $data['isedit'] ? 'disabled' : '';?>>
                                        <option value="" selected disabled>Select group</option>
                                        <?php foreach($data['groups'] as $group) : ?>
                                            <option value="<?php echo $group->ID;?>" <?php selectdCheck($group->ID,$data['group']);?>><?php echo $group->groupName;?></option>
                                        <?php endforeach;?>
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="col-12 mt-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-stripped" id="budget">
                                            <thead class="table-info">
                                                <tr>
                                                    <th class="d-none">ID</th>
                                                    <th>Expense Account</th>
                                                    <th class="text-center">Amount Budgeted</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($data['table'] as $table) :?>
                                                    <tr>
                                                        <td class="d-none"><input type="text" name="accountsid[]" value="<?php echo $table['aid'];?>" readonly></td>
                                                        <td><input type="text" class="table-input w-100" name="accountsname[]" value="<?php echo $table['name'];?>" readonly></td>
                                                        <td class="text-center"><input type="number" class="table-input text-center table-input-custom" name="amounts[]" value="<?php echo $table['amount'];?>"></td>
                                                    </tr>
                                                <?php endforeach;?>
                                            </tbody>
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
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/budgets/group-budget.js"></script>
</body>
</html>  