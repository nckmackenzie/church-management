<?php

 try{
    $con=new PDO('mysql:host=localhost;dbname=bzaadyyq_cms','bzaadyyq_kalimoniadmin','K@limoniParish');
    $con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
}catch(PDOException $err){
    echo $err->getmessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PCEA Kalimoni</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous">
</head>
<body>
    <div class="content-wrapper">
        <section class="content">
        
            <div class="row">
                <div class="col-md-6 mx-auto mt-5">
                <a href="<?php echo URLROOT;?>/churchbudgets" class="btn btn-dark btn-sm mb-2"><i class="fas fa-backward"></i> Back</a>
                    <div class="card bg-light">
                        <div class="card-body">
                            <form method="post" id="import_excel" enctype="multipart/form-data"
                                action="">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="fiscalyear">Fiscal Year</label>
                                            <select name="fiscalyear" id="fiscalyear" name="fiscalyear"
                                                    class="form-control form-control-sm" required>
                                                <?php
                                                    $sql = "SELECT ID,UCASE(yearName) AS yearName FROM tblfiscalyears
                                                            WHERE ID 
                                                            NOT IN (SELECT ID FROM tblchurchbudget_header)";
                                                    $stmt = $con->prepare($sql);
                                                    $stmt->execute();
                                                    $results = $stmt->fetchAll(PDO::FETCH_OBJ);
                                                    foreach ($results as $result ) {
                                                        echo '<option value="'.$result->ID.'">'.$result->yearName.'</option>';
                                                    }
                                                ?>
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
                                            <input type="file" name="import_excel" id="formfile" class="form-control form-control-sm" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-3">
                                        <button type="submit" class="btn btn-sm btn-dark bg-navy" name="btnsave">Save</button>
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
                            <?php
                                $sql = 'SELECT ID,UCASE(accountType) as accountType
                                        FROM tblaccounttypes
                                        WHERE accountTypeId < 3';
                                $stmt = $con->prepare($sql);
                                $stmt->execute();
                                $results = $stmt->fetchAll(PDO::FETCH_OBJ);
                                foreach ($results as $result ) {
                                    echo '
                                        <tr>
                                            <td>'.$result->ID.'</td>
                                            <td>'.$result->accountType.'</td>
                                            <td></td>
                                        </tr>
                                    ';
                                }
                            ?>
                        </tbody>
                    </table>                                
                </div>
            </div>
        </section>
    </div>
    <?php
        // ob_start();
        if (isset($_POST['btnsave'])) {
            //get id
            // $conn = mysqli_connect('localhost','root','','pceakalimoni');
            $dbid ='';
            $year = $_POST['fiscalyear'];
            $dbResult = '';
            $congid = $_SESSION['congId'];
            $sql = "SELECT COUNT(ID) as dbcount FROM tblchurchbudget_header";
            $stmt = $con->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchColumn();
            if ($result == 0) {
                $dbid = 1;
            }
            else{
                $sql = 'SELECT ID FROM tblchurchbudget_header ORDER BY ID DESC LIMIT 1';
                $stmt = $con->prepare($sql);
                $stmt->execute();
                $dbid = ($stmt->fetchColumn()) + 1;
            }
            
            
            try {
                //begin transaction
                $con->beginTransaction();
                $sql ="INSERT INTO tblchurchbudget_header (ID,yearId,congregationId)
                       VALUES(?,?,?)";
                $stmt = $con->prepare($sql);
                $stmt->execute([$dbid,$year,$congid]);
                //details
                if ($_FILES['import_excel']['name']) {
                    $filename = explode('.',$_FILES['import_excel']['name']);
                    if (end($filename) == 'csv') {
                        $handle = fopen($_FILES['import_excel']['tmp_name'],"r");
                        fgetcsv($handle);
                        while ($data = fgetcsv($handle)) {
                            $id = $data[0];
                            $famount = !empty($data[2]) ? $data[2] : NULL;
                            $sql = "INSERT INTO tblchurchbudget_details (ID,accountId,amount)
                                    VALUES(?,?,?)";
                            $stmt = $con->prepare($sql);
                            $stmt->execute([$dbid,$id,$famount]);
                        }
                        if ($con->commit()) {
                            fclose($handle);
                            echo '
                                <div class="row">
                                    <div class="col-md-6 mx-auto mt-2">
                                        <div class="alert alert-success">
                                            Budget Saved Successfully!
                                        </div>
                                    </div>
                                </div>
                            ';
                        }
                    }
                    else{
                        echo 'Something Went Wrong';
                    }
                }
                
            } catch (\Exception $th) {
                if ($con->inTransaction()) {
                    $con->rollBack();
                }
                throw $th;
            }
        }
        // ob_end_flush();
    ?>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns" crossorigin="anonymous"></script>
<script>
class TableCSVExporter {
    constructor (table, includeHeaders = true) {
        this.table = table;
        this.rows = Array.from(table.querySelectorAll("tr"));

        if (!includeHeaders && this.rows[0].querySelectorAll("th").length) {
            this.rows.shift();
        }
    }

    convertToCSV () {
        const lines = [];
        const numCols = this._findLongestRowLength();

        for (const row of this.rows) {
            let line = "";

            for (let i = 0; i < numCols; i++) {
                if (row.children[i] !== undefined) {
                    line += TableCSVExporter.parseCell(row.children[i]);
                }

                line += (i !== (numCols - 1)) ? "," : "";
            }

            lines.push(line);
        }

        return lines.join("\n");
    }

    _findLongestRowLength () {
        return this.rows.reduce((l, row) => row.childElementCount > l ? row.childElementCount : l, 0);
    }

    static parseCell (tableCell) {
        let parsedValue = tableCell.textContent;

        // Replace all double quotes with two double quotes
        parsedValue = parsedValue.replace(/"/g, `""`);

        // If value contains comma, new-line or double-quote, enclose in double quotes
        parsedValue = /[",\n]/.test(parsedValue) ? `"${parsedValue}"` : parsedValue;

        return parsedValue;
    }
}
//table
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