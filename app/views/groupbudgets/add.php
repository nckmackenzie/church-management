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
    <div class="container">
        <div class="row">
            <div class="col-md-9 mx-auto mt-5">
            <a href="<?php echo URLROOT;?>/groupbudgets" class="btn btn-dark btn-sm mb-2"><i class="fas fa-backward"></i> << Back</a>
                <div class="card bg-light">
                    <div class="card-header">Create Group Budget</div>
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="year">Year</label>
                                        <select name="year" id="year" 
                                                class="form-control form-control-sm" required>
                                            <?php foreach($data['years'] as $year) : ?>
                                                <option value="<?php echo $year->ID;?>">
                                                    <?php echo $year->yearName;?>
                                                </option>
                                            <?php endforeach;?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="group">Group</label>
                                        <select name="group" id="group" class="form-control form-control-sm" required>
                                            <?php foreach($data['groups'] as $group) : ?>
                                                <option value="<?php echo $group->ID;?>">
                                                    <?php echo $group->groupName;?>
                                                </option>
                                            <?php endforeach;?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                        <label for="" style="color: #F4F6F9;">Buut</label>
                                        <button type="button" class="btn btn-primary btn-sm custom-font form-control form-control-sm" id="export">Export</button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <label for="formfile">Select Excel File</label>
                                    <input type="file" name="import_excel" id="formfile"
                                           class="form-control form-control-sm" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-2 mt-2">
                                    <button type="submit" class="btn btn-sm btn-dark" name="btnsave">Save</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row" style="display: none;">
            <div class="col-md-12">
                <table class="table" id="exportTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>AccountName</th>
                            <th>Amount</th>
                        </tr>
                    </thead>  
                    <tbody>
                        <?php foreach($data['accounts'] as $account) : ?>
                            <tr>
                                <td><?php echo $account->ID;?></td>
                                <td><?php echo $account->accountType;?></td>
                                <td></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>                             
                </table>
            </div>
        </div>
    </div>
    <?php
        if (isset($_POST['btnsave'])) {
            try{
                $con=new PDO('mysql:host=localhost;dbname='.DB_NAME.'',DB_USER,DB_PASS);
                $con->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            }catch(PDOException $err){
                echo $err->getmessage();
            }
            //variables
            $year = trim($_POST['year']);
            $group = trim($_POST['group']);
            //check if data saved
            $sql = 'SELECT COUNT(ID) FROM tblgroupbudget_header 
                    WHERE (groupId = ?) AND (fiscalYearId= ?)';
            $stmt = $con->prepare($sql);
            $stmt->execute([$group,$year]);
            $result = $stmt->fetchColumn();
            if ($result > 0) {
                echo '
                    <div class="row">
                        <div class="col-md-6 mx-auto mt-2">
                            <div class="alert alert-danger">
                                Budget For Selected Group And Year Already Created!
                            </div>
                        </div>
                    </div>
                ';
                exit();
            }
            try {
                //begin transaction
                $con->beginTransaction();
                //save header
                $sql = 'INSERT INTO tblgroupbudget_header (groupId,fiscalYearId,congregationId) 
                        VALUES(?,?,?)';
                $stmt = $con->prepare($sql);
                $stmt->execute([$group,$year,$_SESSION['congId']]);
                $dbid = $con->lastInsertId();
                //details
                if ($_FILES['import_excel']['name']) {
                    $filename = explode('.',$_FILES['import_excel']['name']);
                    if (end($filename) == 'csv') {
                        $handle = fopen($_FILES['import_excel']['tmp_name'],"r");
                        fgetcsv($handle);
                        while ($data = fgetcsv($handle)) {
                            $id = $data[0];
                            $famount = !empty($data[2]) ? $data[2] : NULL;
                            $sql = "INSERT INTO tblgroupbudget_details (ID,accountId,amount)
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
    ?>
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
            anchorElement.download = "group-budget.csv";
            anchorElement.click();

            setTimeout(() => {
                URL.revokeObjectURL(blobUrl);
            }, 500);
        });
</script>
</body>
</html>