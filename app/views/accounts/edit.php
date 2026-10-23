<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<?php $h = function ($v) { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }; ?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
             <a href="<?php echo URLROOT;?>/accounts" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card bg-light">
                    <div class="card-header">Edit Account</div>
                    <div class="card-body">
                        <?php if(!empty($data['errors']['general'])) : ?>
                            <div class="alert alert-danger"><?php echo $h($data['errors']['general']);?></div>
                        <?php endif;?>
                        <?php if($data['protected']) : ?>
                            <div class="alert alert-info">This is a protected account: it cannot be renamed, moved, deactivated or deleted here. The description and For Group can be changed.</div>
                        <?php endif;?>
                        <form action="<?php echo URLROOT;?>/accounts/update" method="post">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="accountname">Account Name</label>
                                        <input type="text" name="accountname" id="accountname"
                                               class="form-control form-control-sm mandatory
                                               <?php echo (!empty($data['errors']['name'])) ? 'is-invalid' : ''?>"
                                               value="<?php echo $h($data['accountname']);?>"
                                               autocomplete="off" maxlength="100"
                                               <?php echo $data['protected'] ? 'readonly' : ''?>
                                               placeholder="eg Offering,Tithe,Water Bill etc">
                                        <span class="invalid-feedback"><?php echo $h($data['errors']['name'] ?? '');?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>Congregation</label>
                                        <input type="text" class="form-control form-control-sm" value="<?php echo $h($data['congregationName']);?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <?php if(!$data['isroot']) : ?>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="parent">Sub Category Of</label>
                                        <?php if($data['protected']) : ?>
                                            <input type="text" class="form-control form-control-sm" value="<?php echo $h(strtoupper($data['parentpath']));?>" readonly>
                                        <?php else : ?>
                                        <select name="parent" id="parent"
                                                 class="form-control form-control-sm
                                                 <?php echo (!empty($data['errors']['parent'])) ? 'is-invalid' : ''?>">
                                            <?php foreach($data['parents'] as $p) : ?>
                                                <option value="<?php echo (int)$p['ID'];?>" data-forgroup="<?php echo (int)$p['forGroup'];?>"
                                                    <?php selectdCheck((string)$data['parent'],(string)$p['ID'])?>>
                                                    <?php echo $h(strtoupper($p['path']));?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="invalid-feedback"><?php echo $h($data['errors']['parent'] ?? '');?></span>
                                        <small class="text-muted">Moving the account moves its sub accounts with it. An account (or sub accounts) with postings can only
                                            move inside the same account type.</small>
                                        <?php endif;?>
                                    </div>
                                </div>
                            </div>
                            <?php endif;?>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                         <label for="description">Description</label>
                                         <input type="text" name="description" id="description"
                                                class="form-control form-control-sm" maxlength="255"
                                                value="<?php echo $h($data['description']);?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label class="custom-sm">
                                                <input type="checkbox" id="forgroup" name="forgroup"
                                                <?php echo ($data['forgroup']) ? 'checked' : ''?>>For Group
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="checkbox">
                                            <label class="custom-sm">
                                                <input type="checkbox" id="active" name="active"
                                                <?php echo ($data['active']) ? 'checked' : ''?> <?php echo $data['protected'] ? 'onclick="return false;"' : ''?>>Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="id" value="<?php echo (int)$data['id'];?>">
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
</body>
</html>
