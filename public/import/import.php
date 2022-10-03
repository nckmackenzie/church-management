<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITENAME; ?></title>
    <link rel="shortcut icon" href="<?php echo URLROOT;?>/img/cropped-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/fontawesome-free/css/all.min.css" />
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/select2/dist/css/select2.min.css">
    <link rel="stylesheet" href="<?php echo URLROOT;?>/plugins/CustomDate/src/DateTimePicker.css">
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/dist/css/adminlte.min.css" />
    <link rel="stylesheet" href="<?php echo URLROOT; ?>/dist/css/style.css">
</head>
<body>
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
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-body">
                         <form method="post" id="import_excel" enctype="multipart/form-data"
                               action="<?php echo URLROOT;?>/churchbudgets/import">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fiscalyear">Fiscal Year</label>
                                        <select name="fiscalyear" id="fiscalyear" name="fiscalyear"
                                                class="form-control form-control-sm">
                                            <?php foreach($data['years'] as $year) : ?>
                                                <option value="<?php echo $year->ID;?>">
                                                    <?php echo strtoupper($year->yearName);?>
                                                </option>
                                            <?php endforeach; ?>    
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label for="" style="color: #F4F6F9;">Buut</label>
                                    <button type="button" class="btn btn-primary btn-sm custom-font form-control form-control-sm" id="export">Export</button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="formfile">Select Excel File</label>
                                        <input type="file" name="formtext" id="formfile" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                </div>
                            </div>
                        </div>
                    </form>    
                </div>
            </div>
        </div>
        <div class="row" style="display: none;">
            <div class="col-12">
                <table class="table table-bordered" id="exportTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>AccountName</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['accounts'] as $account) : ?>
                            <tr>
                                <td><?php echo $account->ID;?></td>
                                <td><?php echo strtoupper($account->accountType);?></td>
                                <td></td>
                            </tr>    
                        <?php endforeach; ?>
                    </tbody>
                </table>                                
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<script src="<?php echo URLROOT;?>/dist/js/html2CSV.js"></script>
<script>
        const dataTable = document.getElementById("exportTable");
        const btnExportToCsv = document.getElementById("export");

        btnExportToCsv.addEventListener("click", () => {
            const exporter = new TableCSVExporter(dataTable);
            const csvOutput = exporter.convertToCSV();
            const csvBlob = new Blob([csvOutput], { type: "text/csv" });
            const blobUrl = URL.createObjectURL(csvBlob);
            const anchorElement = document.createElement("a");

            anchorElement.href = blobUrl;
            anchorElement.download = "church-budget.csv";
            anchorElement.click();

            setTimeout(() => {
                URL.revokeObjectURL(blobUrl);
            }, 500);
        });
</script>
</body>
</html>  