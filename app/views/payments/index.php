<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>

<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <?php flash('deposit_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/payments/add" class="btn btn-sm btn-success custom-font">New Payment</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table class="table table-striped table-bordered table-sm" id="paymentsTable">
                    <thead class="bg-navy">
                        <th>Payment No</th>
                        <th>Supplier</th>
                        <th>Payment Date</th>
                        <th>Amount Paid</th>
                        <th>Actions</th>
                    </thead>
                    <tbody>
                       <?php foreach($data['payments'] as $payment): ?>
                          <tr>
                            <td><?php echo $payment->paymentNo;?></td>
                            <td><?php echo $payment->supplierName;?></td>
                            <td><?php echo date('d-m-Y',strtotime($payment->paymentDate)) ;?></td>
                            <td><?php echo number_format($payment->AmountPaid,2);?></td>
                            <td>
                               <a href="<?php echo URLROOT;?>/payments/print/<?php echo $payment->paymentNo;?>" target="_blank" class="btn btn-sm bg-olive custom-font">Print Voucher</a>
                            </td>
                          </tr>
                       <?php endforeach; ?>
                    </tbody>
                </table>
            </div>    
        </div>        
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
  $('#paymentsTable').DataTable({
          'ordering' : false,
          'columnDefs' : [
            {"width" : "15%" , "targets": 0},
            {"width" : "15%" , "targets": 4},
          ]
      });
</script>
</body>
</html>