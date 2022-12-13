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
            <div class="d-flex justify-content-end align-items-center mb-2">
                <span><strong>Voucher No:&nbsp;</strong></span><span><?php echo $data['paymentno'];?></span>
            </div>
            <div class="text-center">
                <h6 class="font-weight-bolder h3"><?php echo strtoupper($data['congregationinfo']->CongregationName);?></h6>
                <p class="h5 text-uppercase text-decoration-underline pb-1" style="text-decoration: underline;">cheque payment voucher</p>
            </div>
            <!-- title row -->
            <div class="row invoice-info">
                <div class="col-sm-12 invoice-col mb-2">
                    <strong>BANK ACCOUNT:&nbsp; &nbsp;</strong>
                    <span><?php echo $data['bank'];?></span>
                </div><!-- /.col -->
                <div class="col-sm-9 invoice-col mb-2">
                    <strong>CHEQUE NO:&nbsp; &nbsp;</strong>
                    <span><?php echo $data['chequeno'];?></span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-2">
                    <strong>PAYMENT DATE:&nbsp; &nbsp;</strong>
                    <span><?php echo $data['paymentdate'];?></span>
                </div><!-- /.col -->
                <div class="col-sm-12 invoice-col mb-2">
                    <strong>PAYEE NAME:&nbsp; &nbsp;</strong>
                    <span><?php echo strtoupper($data['supplier']->supplierName);?></span>
                </div><!-- /.col -->
            </div><!-- /.row -->
            <!-- Table row -->
            <div class="row">
                <div class="col-12 table-responsive mt-5">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Invoice Date</th>
                                <th>Due Date</th>
                                <th>Invoice Value</th>
                                <th>Payment</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($data['invoicedetails'] as $detail) : ?>
                                <tr>
                                    <td><?php echo strtoupper($detail->invoiceNo); ?></td>
                                    <td><?php echo date('d-m-Y',strtotime($detail->invoiceDate)); ?></td>
                                    <td><?php echo date('d-m-Y',strtotime($detail->duedate)); ?></td>
                                    <td><?php echo number_format($detail->inclusiveVat,2); ?></td>
                                    <td><?php echo number_format($detail->amount,2); ?></td>
                                    <td><?php echo number_format($detail->Balance,2); ?></td>
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
            <div class="row invoice-info mb-3">
                <div class="col-sm-6 invoice-col mb-3">
                    <strong>Prepared By:&nbsp; &nbsp;</strong>
                    <span>.......................................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Sign:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Date:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
            </div><!-- /.row -->  
            <div class="row invoice-info mb-3">
                <div class="col-sm-6 invoice-col mb-3">
                    <strong>Cheque Picked By:&nbsp; &nbsp;</strong>
                    <span>.......................................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Sign:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Date:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
            </div><!-- /.row -->
            <div class="mb-3">
                <p class="h6 text-uppercase text-decoration-underline font-weight-bolder" style="text-decoration: underline;">approved by signatories</p>
            </div>
            <div class="row invoice-info mb-3">
                <div class="col-sm-6 invoice-col mb-3">
                    <strong>Signatory 1:&nbsp; &nbsp;</strong>
                    <span>.......................................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Sign:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Date:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
            </div><!-- /.row --> 
            <div class="row invoice-info mb-3">
                <div class="col-sm-6 invoice-col mb-3">
                    <strong>Signatory 2:&nbsp; &nbsp;</strong>
                    <span>.......................................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Sign:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Date:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
            </div><!-- /.row --> 
            <div class="row invoice-info">
                <div class="col-sm-6 invoice-col mb-3">
                    <strong>Signatory 3:&nbsp; &nbsp;</strong>
                    <span>.......................................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Sign:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
                <div class="col-sm-3 invoice-col mb-3">
                    <strong>Date:&nbsp; &nbsp;</strong>
                    <span>...................................</span>
                </div><!-- /.col -->
            </div><!-- /.row -->                    
        </section><!--End Of invoice section -->
    </div><!--End Of Wrapper -->
<script>
  window.addEventListener("load", window.print());
</script>
</body>
</html>