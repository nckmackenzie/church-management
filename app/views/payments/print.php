<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalimoni Parish</title>
    <link rel="shortcut icon" href="<?php echo URLROOT;?>/img/cropped-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/fontawesome-free/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/dist/css/style.css">
</head>
<body>
    <div class="wrapper">
        <section class="invoice">
            <!-- title row -->
            <div class="row invoice-info d-flex justify-content-between">
                <div class="col-sm-4 invoice-col">
                    <strong>FROM</strong>
                    <address>
                        <span><?php echo ucwords($data['congregationinfo']->CongregationName);?></span>
                        <br>Address: <?php echo ucwords($data['congregationinfo']->Address);?>
                        <br>Phone: <?php echo $data['congregationinfo']->contact;?>
                        <br>Email: <?php echo $data['congregationinfo']->email;?>
                        <br>PIN: 
                    </address>
                </div><!-- /.col -->
                <div class="col-sm-4 invoice-col">
                    <strong>Payment To</strong>
                    <address>
                        <span><?php echo ucwords($data['supplier']->supplierName);?></span>
                        <br>Address: <?php echo ucwords($data['supplier']->address);?>
                        <br>Phone: <?php echo $data['supplier']->contact;?>
                        <br>Email: <?php echo $data['supplier']->email;?>
                        <br>P.I.N: <?php echo $data['supplier']->pin;?>
                    </address>
                </div><!-- /.col -->
            </div><!-- /.row -->
            <div class ="my-3 d-flex flex-column">
                <div><strong>Payement No: </strong> <?php echo $data['paymentno'];?></div>
                <div><strong>Payement Date: </strong> <?php echo $data['paymentdate'];?></div>
            </div>
            <!-- Table row -->
            <div class="row">
                <div class="col-12 table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Invoice Date</th>
                                <th>Due Date</th>
                                <th>Invoice Value</th>
                                <th>Balance</th>
                                <th>Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['invoicedetails'] as $detail) : ?>
                                <tr>
                                    <td><?php echo strtoupper($detail->invoiceNo); ?></td>
                                    <td><?php echo date('d-m-Y',strtotime($detail->invoiceDate)); ?></td>
                                    <td><?php echo date('d-m-Y',strtotime($detail->duedate)); ?></td>
                                    <td><?php echo number_format($detail->inclusiveVat,2); ?></td>
                                    <td><?php echo number_format($detail->Balance,2); ?></td>
                                    <td><?php echo number_format($detail->amount,2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" style="text-align:center"><span class="h6 font-weight-bold">TOTAL</span></th>
                                <th><span class="h6 font-weight-bold"><?php echo number_format($data['total'],2);?></span></th>
                            </tr>
                        </tfoot>
                    </table>
                </div><!--End Of Col-->
            </div><!--End Of Row -->  

        </section><!--End Of invoice section -->
    </div><!--End Of Wrapper -->
<script>
  window.addEventListener("load", window.print());
</script>
</body>
</html>