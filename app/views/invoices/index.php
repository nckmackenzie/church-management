<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Invoice</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/invoices/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Invoice?</label>
                  <input type="hidden" name="id" id="id">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Yes</button>
              </div>
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
                <?php foreach($data['invoices'] as $invoice) :?>
                    <tr>
                        <td><?php echo $invoice->ID;?></td>
                        <td><?php echo $invoice->invoiceDate;?></td>
                        <td><?php echo $invoice->invoiceNo;?></td>
                        <td><?php echo $invoice->customer;?></td>
                        <td><?php echo $invoice->duedate;?></td>
                        <td><?php echo number_format($invoice->balance,2);?></td>
                       
                        <?php if($invoice->status == 0) : ?>
                            <td class="text-danger"><?php echo $invoice->state; ?></td>
                        <?php elseif($invoice->status == 2) : ?>
                            <td class="text-success"><?php echo $invoice->state; ?></td>
                        <?php else : ?>
                            <td class="text-warning"><?php echo $invoice->state; ?></td>    
                        <?php endif; ?>
                        <?php if($_SESSION['userType'] <=2) : ?>
                            <td>
                                <div class="btn-group">
                                    <?php if($invoice->status == 0) : ?>
                                        <a href="<?php echo URLROOT;?>/invoices/pay/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-dark custom-font">Pay</a>  
                                        <a href="<?php echo URLROOT;?>/invoices/edit/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm bg-olive custom-font">Edit</a> 
                                        <a href="<?php echo URLROOT;?>/invoices/print/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-warning custom-font" target="_blank">Print</a>
                                        <button class="btn btn-danger btn-sm custom-font btndel">Delete</button>
                                    <?php elseif($invoice->status == 1) : ?>
                                        <a href="<?php echo URLROOT;?>/invoices/pay/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-dark custom-font">Pay</a> 
                                        <a href="<?php echo URLROOT;?>/invoices/print/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-warning custom-font" target="_blank">Print</a>
                                    <?php else : ?>
                                        <a href="<?php echo URLROOT;?>/invoices/print/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-warning custom-font" target="_blank">Print</a>    
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php elseif($_SESSION['userType'] <=5) : ?>
                          <td>
                            <div class="btn-group">
                                <?php if($invoice->status == 0) : ?>
                                    <a href="<?php echo URLROOT;?>/invoices/pay/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-dark custom-font">Pay</a>
                                    <!-- Allow edit if created by logged user -->
                                    <?php if($invoice->postedBy == $_SESSION['userId']) : ?>
                                      <a href="<?php echo URLROOT;?>/invoices/edit/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm bg-olive custom-font">Edit</a>
                                    <?php endif; ?>
                                    <a href="<?php echo URLROOT;?>/invoices/print/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-warning custom-font" target="_blank">Print</a>
                                <?php elseif($invoice->status == 1) : ?>
                                    <a href="<?php echo URLROOT;?>/invoices/pay/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-dark custom-font">Pay</a> 
                                    <a href="<?php echo URLROOT;?>/invoices/print/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-warning custom-font" target="_blank">Print</a>
                                <?php else : ?>
                                    <a href="<?php echo URLROOT;?>/invoices/print/<?php echo encryptId($invoice->ID);?>" class="btn btn-sm btn-warning custom-font" target="_blank">Print</a> 
                                <?php endif; ?>
                            </div>
                          </td>
                        <?php endif; ?>
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

      $('#invoicesTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $tr = $(this).closest('tr');

          let data = $tr.children('td').map(function(){
              return $(this).text();
          }).get();
          $('#id').val(data[0]);
      });
    });
</script>
</body>
</html>