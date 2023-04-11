<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher # <?php echo $data['expense']->voucherNo;?></title>
    <link rel="shortcut icon" href="<?php echo URLROOT;?>/img/cropped-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/fontawesome-free/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/dist/css/style.css">
</head>
<body>
    <div class="wrapper">
        <section class="invoice">
            <!-- title row -->
            <div class="row mb-3">
                <div class="col-12">
                    <h2 class="page-header">
                        <?php echo $data['expense']->CongregationName;?>
                    </h2>
                </div><!-- /.col -->
            </div> <!-- info row -->
            <div class="flex flex-column mb-5">
                <p class="h5 custom-font"><strong>Date: </strong><span><?php echo $data['expense']->expenseDate;?></span></p>
                <p class="h5 custom-font"><strong>Voucher No: </strong><span><?php echo $data['expense']->voucherNo;?></span></p>
            </div>
            <div class="row">
                <div class="col-md-9 mx-auto">
                    <div class="card">
                        <div class="card-header text-center h3">VOUCHER</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12 table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th width="20%">Expense</th>
                                                <th width="20%">Type Of Expense</th>
                                                <th width="20%">Cost Center</th>
                                                <th width="30%">Description</th>
                                                <th width="10%">Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php echo $data['expense']->account; ?></td>
                                                <td><?php echo $data['expense']->category; ?></td>
                                                <td><?php echo $data['expense']->costcentre; ?></td>
                                                <td><?php echo $data['expense']->narration; ?></td>
                                                <td><?php echo $data['expense']->amount; ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </section><!--End Of invoice section -->
    </div><!--End Of Wrapper -->
<script>
  window.addEventListener("load", window.print());
</script>
</body>
</html>