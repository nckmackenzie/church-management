<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('invoice_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/invoices/add" class="btn btn-sm btn-success custom-font">Add New</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <table class="table table-striped table-bordered table-sm" id="invoicesTable">
            <thead class="bg-navy">
                <th>ID</th>
                <th>Date</th>
                <th>No</th>
                <th>Customer</th>
                <th>Due Date</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Actions</th>
            </thead>
            <tbody>
                <?php $type = (int)$_SESSION['userType']; $fullRights = ($type <= 2 || $type > 4); ?>
                <?php foreach($data['invoices'] as $invoice) :?>
                    <?php
                        $canEdit = $fullRights || ((int)$invoice->postedBy === (int)$_SESSION['userId'] && (int)$invoice->status === 0);
                        $id = encryptId($invoice->ID);
                    ?>
                    <tr>
                        <td><?php echo (int)$invoice->ID;?></td>
                        <td><?php echo htmlspecialchars((string)$invoice->invoiceDate,ENT_QUOTES,'UTF-8');?></td>
                        <td><?php echo htmlspecialchars((string)$invoice->invoiceNo,ENT_QUOTES,'UTF-8');?></td>
                        <td><?php echo htmlspecialchars((string)$invoice->customer,ENT_QUOTES,'UTF-8');?></td>
                        <td><?php echo htmlspecialchars((string)$invoice->duedate,ENT_QUOTES,'UTF-8');?></td>
                        <td><?php echo number_format($invoice->balance,2);?></td>
                        <?php if($invoice->status == 0) : ?>
                            <td class="text-danger"><?php echo htmlspecialchars((string)$invoice->state,ENT_QUOTES,'UTF-8'); ?></td>
                        <?php elseif($invoice->status == 2) : ?>
                            <td class="text-success"><?php echo htmlspecialchars((string)$invoice->state,ENT_QUOTES,'UTF-8'); ?></td>
                        <?php else : ?>
                            <td class="text-warning"><?php echo htmlspecialchars((string)$invoice->state,ENT_QUOTES,'UTF-8'); ?></td>
                        <?php endif; ?>
                        <td>
                            <div class="btn-group">
                                <?php if($canEdit) : ?>
                                    <a href="<?php echo URLROOT;?>/invoices/edit/<?php echo $id;?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                <?php endif; ?>
                                <?php if($invoice->status < 2) : ?>
                                    <a href="<?php echo URLROOT;?>/invoices/pay/<?php echo $id;?>" class="btn btn-sm btn-dark custom-font">Pay</a>
                                <?php endif; ?>
                                <a href="<?php echo URLROOT;?>/invoices/print/<?php echo $id;?>" class="btn btn-sm btn-warning custom-font" target="_blank">Print</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
      $('#invoicesTable').DataTable({
          'pageLength' : 50,
          'ordering' : false,
          'columnDefs' : [
            {"visible" : false, "targets": 0},
            {"width" : "10%" , "targets": 1},
            {"width" : "10%" , "targets": 2},
            {"width" : "10%" , "targets": 4},
            {"width" : "10%" , "targets": 6},
            {"width" : "20%" , "targets": 7},
          ]
      });
    });
</script>
</body>
</html>