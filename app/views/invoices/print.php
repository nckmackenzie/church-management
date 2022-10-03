<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $data['header']->invoiceNo;?></title>
    <link rel="shortcut icon" href="<?php echo URLROOT;?>/img/cropped-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/fontawesome-free/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/dist/css/style.css">
</head>
<body>
    <div class="wrapper">
        <section class="invoice">
            <!-- title row -->
            <div class="row">
                <div class="col-12">
                    <h2 class="page-header">
                        <?php echo $data['congregationinfo']->CongregationName;?>
                    <small class="float-right">Date: <?php echo $data['header']->invoiceDate;?></small>
                    </h2>
                </div><!-- /.col -->
            </div> <!-- info row -->
            <div class="row invoice-info">
                <div class="col-sm-4 invoice-col">
                    FROM
                    <address>
                        <strong><?php echo $data['congregationinfo']->CongregationName;?></strong>
                        <br>Address: <?php echo $data['congregationinfo']->address;?>
                        <br>Phone: <?php echo $data['congregationinfo']->contact;?>
                        <br>Email: <?php echo $data['congregationinfo']->email;?>
                        <br>PIN: 
                    </address>
                </div><!-- /.col -->
                <div class="col-sm-4 invoice-col">
                    TO
                    <address>
                        <strong><?php echo $data['customerinfo']->customerName;?></strong>
                        <br>Address: <?php echo $data['customerinfo']->address;?>
                        <br>Phone: <?php echo $data['customerinfo']->contact;?>
                        <br>Email: <?php echo $data['customerinfo']->email;?>
                        <br>P.I.N: <?php echo $data['customerinfo']->pin;?>
                    </address>
                </div><!-- /.col -->
                <div class="col-sm-4 invoice-col">
                    <b>Invoice #<?php echo $data['header']->invoiceNo;?></b><br>
                    <br>
                    <b>Payment Due:</b> <?php echo $data['header']->duedate;?><br>
                </div><!-- /.col -->
            </div><!-- /.row -->
            <!-- Table row -->
            <div class="row">
                <div class="col-12 table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>PRODUCT</th>
                                <th>DESCRIPTION</th>
                                <th width="10%">QTY</th>
                                <th width="10%">RATE</th>
                                <th width="10%">GROSS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['details'] as $detail) : ?>
                                <tr>
                                    <td><?php echo $detail->accountType; ?></td>
                                    <td><?php echo $detail->description; ?></td>
                                    <td><?php echo number_format($detail->qty,2); ?></td>
                                    <td><?php echo number_format($detail->rate,2); ?></td>
                                    <td><?php echo number_format($detail->gross,2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div><!--End Of Col-->
            </div><!--End Of Row -->  
            <hr>  
            <div class="row">
                <div class="col-6">
                    <p class="lead">NOTES:</p>             
                </div><!--End Of Col-->
                <div class="col-6">
                    
                    <div class="table-responsive">
                        <table class="table">
                            <tr>
                                <th style="width:50%">Subtotal:</th>
                                <td><?php echo number_format($data['header']->exclusiveVat,2)?></td>
                            </tr>
                            <tr>
                                <th>V.A.T</th>
                                <td><?php echo number_format($data['header']->vat,2)?></td>
                            </tr>
                            <tr>
                                <th>Total</th>
                                <td><?php echo number_format($data['header']->inclusiveVat,2)?></td>
                            </tr>
                        </table>
                    </div>             
                </div><!--End Of Col-->                  
            </div><!--End Of Row -->
        </section><!--End Of invoice section -->
    </div><!--End Of Wrapper -->
<script>
  window.addEventListener("load", window.print());
</script>
</body>
</html>