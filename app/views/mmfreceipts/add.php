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
            <a href="<?php echo URLROOT;?>/mmfreceipts" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
          <div class="col-sm-6"></div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header"><?php echo $data['isedit'] ? 'Edit MMF' : 'Add MMF';?></div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/mmfreceipts/createupdate" method="post" autocomplete="off">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="tdate">Date</label>
                                        <input type="date" name="tdate" id="date" 
                                               class="form-control form-control-sm mandatory 
                                               <?php echo !empty($data['tdate_err']) ? 'is-invalid' : ''; ?>" 
                                               value="<?php echo $data['tdate'];?>" required>
                                        <span class="invalid-feedback"><?php echo $data['tdate_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="groupid">Lending Group</label>
                                        <select name="groupid" id="groupid" class="form-control form-control-sm mandatory 
                                                <?php echo !empty($data['groupid_err']) ? 'is-invalid' : ''; ?>">
                                            <option value="">Select group</option>
                                            <?php foreach($data['groups'] as $group) : ?>
                                                <option value="<?php echo $group->ID; ?>" <?php selectdCheck($data['groupid'],$group->ID);?>><?php echo $group->GroupName;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['groupid_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="balance">Balance</label>
                                        <input type="number" name="balance" id="balance" 
                                               class="form-control form-control-sm"
                                               value="<?php echo $data['balance'];?>" readonly>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="amount">Amount</label>
                                        <input type="number" name="amount" id="amount" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo !empty($data['amount_err']) ? 'is-invalid' : ''; ?>"
                                               value="<?php echo $data['amount'];?>" placeholder="eg 2,000" required>
                                        <span class="invalid-feedback"><?php echo $data['amount_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="bank">Bank</label>
                                        <select name="bank" id="bank" class="form-control form-control-sm mandatory 
                                                <?php echo !empty($data['bank_err']) ? 'is-invalid' : '';?>">
                                            <option value="">Select Bank</option>
                                            <?php foreach($data['banks'] as $bank): ?>
                                                <option value="<?php echo $bank->ID;?>" <?php selectdCheck($data['bank'],$bank->ID);?>><?php echo $bank->Bank;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $data['bank_err'];?></span>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label for="reference">Reference</label>
                                        <input type="text" name="reference" id="reference" 
                                               class="form-control form-control-sm mandatory
                                               <?php echo !empty($data['reference_err']) ? 'is-invalid' : ''; ?>"
                                               value="<?php echo $data['reference'];?>" placeholder="eg cheque no.." required>
                                        <span class="invalid-feedback"><?php echo $data['reference_err'];?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <input type="hidden" name="id" value="<?php echo $data['id'];?>" />
                                    <input type="hidden" name="isedit" value="<?php echo $data['isedit'];?>" />
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>                
                                </div>
                                
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    const url = '<?php echo URLROOT;?>';
    const balanceInput = document.querySelector('#balance');
    const groupSelect = document.querySelector('#groupid');
    const dateInput = document.querySelector('#date');
 
    groupSelect.addEventListener('change',async function(e){
        if(dateInput.value === ''){
            alert('Select date');
            return;
        }

        const res = await fetch(`${url}/mmfreceipts/getbalance?date=${dateInput.value}&groupid=${e.target.value}`);
        const {balance} = await res.json();
        balanceInput.value = balance;
    });
</script>
</body>
</html>  