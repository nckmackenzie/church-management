<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars((string)$data['header']->invoiceNo,ENT_QUOTES,'UTF-8');?></title>
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
                        <?php echo htmlspecialchars((string)$data['congregationinfo']->CongregationName,ENT_QUOTES,'UTF-8');?>
                    <small class="float-right">Date: <?php echo htmlspecialchars((string)$data['header']->invoiceDate,ENT_QUOTES,'UTF-8');?></small>
                    </h2>
                </div><!-- /.col -->
            </div> <!-- info row -->
            <div class="row invoice-info">
                <div class="col-sm-4 invoice-col">
                    FROM
                    <address>
                        <strong><?php echo htmlspecialchars((string)$data['congregationinfo']->CongregationName,ENT_QUOTES,'UTF-8');?></strong>
                        <br>Address: <?php echo htmlspecialchars((string)$data['congregationinfo']->address,ENT_QUOTES,'UTF-8');?>
                        <br>Phone: <?php echo htmlspecialchars((string)$data['congregationinfo']->contact,ENT_QUOTES,'UTF-8');?>
                        <br>Email: <?php echo htmlspecialchars((string)$data['congregationinfo']->email,ENT_QUOTES,'UTF-8');?>
                        <br>PIN: 
                    </address>
                </div><!-- /.col -->
                <div class="col-sm-4 invoice-col">
                    TO
                    <address>
                        <strong><?php echo htmlspecialchars((string)$data['customerinfo']->customerName,ENT_QUOTES,'UTF-8');?></strong>
                        <br>Address: <?php echo htmlspecialchars((string)$data['customerinfo']->address,ENT_QUOTES,'UTF-8');?>
                        <br>Phone: <?php echo htmlspecialchars((string)$data['customerinfo']->contact,ENT_QUOTES,'UTF-8');?>
                        <br>Email: <?php echo htmlspecialchars((string)$data['customerinfo']->email,ENT_QUOTES,'UTF-8');?>
                        <br>P.I.N: <?php echo htmlspecialchars((string)$data['customerinfo']->pin,ENT_QUOTES,'UTF-8');?>
                    </address>
                </div><!-- /.col -->
                <div class="col-sm-4 invoice-col">
                    <b>Invoice #<?php echo htmlspecialchars((string)$data['header']->invoiceNo,ENT_QUOTES,'UTF-8');?></b><br>
                    <br>
                    <b>Payment Due:</b> <?php echo htmlspecialchars((string)$data['header']->duedate,ENT_QUOTES,'UTF-8');?><br>
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
                                    <td><?php echo htmlspecialchars((string)$detail->accountType,ENT_QUOTES,'UTF-8');?></td>
                                    <td><?php echo htmlspecialchars((string)$detail->description,ENT_QUOTES,'UTF-8');?></td>
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