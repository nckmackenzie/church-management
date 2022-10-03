<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('account_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/accounts/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="accountsTable">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Account Name</th>
                            <th>Account Type</th>
                            <?php if ($_SESSION['userType'] <=2) : ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data['accounts'] as $account) :?>
                            <tr>
                                <td><?php echo $account->ID;?></td>
                                <td class="sub-level-<?php echo $account->levels;?>"><?php echo $account->accountType;?></td>
                                <td><?php echo $account->atype;?></td>
                                <?php if($_SESSION['userType'] <=2) : ?>
                                  <td>
                                     <?php if($account->isEditable == 1) : ?>
                                        <a href="<?php echo URLROOT;?>/accounts/edit/<?php echo encryptId($account->ID);?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                    <?php endif;?>
                                  </td>     
                                <?php endif; ?>
                            </tr>
                            <?php
                                $con=new PDO('mysql:host=localhost;dbname=bzaadyyq_cms',DB_USER,DB_PASS);
                                $con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
                                $sql = 'SELECT t.ID,UCASE(t.accountType) as accountType,
                                               a.accountType as atype,brand_level(t.ID) AS levels,
                                               t.isEditable
                                        FROM   tblaccounttypes t inner join tblaccounttypes as a 
                                               on t.accountTypeId=a.ID
                                        WHERE  (t.isBank=0) AND (t.deleted=0) AND (t.parentId=?)';
                                $stmt = $con->prepare($sql);
                                $stmt->execute([$account->ID]);
                                $hasChildren = $stmt->rowCount() > 0 ? true : false;
                            ?>
                            <?php if($hasChildren) : ?>
                                <?php foreach($stmt->fetchAll(PDO::FETCH_OBJ) as $child) : ?>
                                  <tr>
                                      <td><?php echo $child->ID;?></td>
                                      <td class="sub-level-3"><?php echo $child->accountType;?></td>
                                      <td><?php echo $child->atype;?></td>
                                      <td>
                                          <a href="<?php echo URLROOT;?>/accounts/edit/<?php echo encryptId($child->ID);?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                      </td>
                                  </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>    
        </div>        
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
      $('#accountsTable').DataTable({
        'pageLength' : 100,
        'ordering': false,
        'columnDefs' : [
            {"visible" : false, "targets": 0},
            {"width" : "15%" , "targets": 2}
            <?php if ($_SESSION['userType'] <=2) : ?>
            ,{"width" : "10%" , "targets": 3},
            <?php endif;?>
          ]
      });
    });
</script>
</body>
</html>