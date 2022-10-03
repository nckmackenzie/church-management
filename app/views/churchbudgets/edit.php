<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
             <a href="<?php echo URLROOT;?>/churchbudgets" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Edit Budget</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="">Year</label>
                                    <input type="text" class="form-control form-control-sm" 
                                           value="<?php echo $data['header']->yearName;?>"
                                           readonly>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 table-responsive">
                                <table class="table table-sm table-bordered table-stripped">
                                    <thead class="bg-navy">
                                        <tr>
                                            <th style="display: none;">ID</th>
                                            <th>Account</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($data['details'] as $detail) : ?>
                                            <tr>
                                                <td style="display: none;"><?php echo $detail->tid;?></td>
                                                <td><?php echo $detail->accountType;?></td>
                                                <td class="amount" data-ida="<?php echo $detail->tid;?>"
                                                    contenteditable><?php echo $detail->amount;?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        function edit_data(id,amount){
            $.ajax({
                url : '<?php echo URLROOT;?>/churchbudgets/update',
                method : 'POST',
                data : {id : id, amount : amount},
                success : function(data){
                    
                }
            });
        }

        $(document).on('blur','.amount',function(){
            var id = $(this).data("ida");
            var amount = $(this).text();
            edit_data(id,amount);
        });
    });
</script>
</body>
</html>  