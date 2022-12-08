<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-12 mt-2" id="alertBox"></div>
        </div>
        <form action="" id="send-form">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="members">Select Members To Send Message To</label>
                        <select id="members" name="members[]"class="form-control required" multiple>
                            <?php foreach($data['members'] as $member): ?>
                                <option value="<?php echo $member->ID;?>"><?php echo $member->MemberName;?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea class="form-control required" id="message" rows="3"></textarea>
                    </div>
                </div>
                <div class="col-sm-12 col-md-4 col-lg-2">
                    <button type="submit" class="btn btn-sm bg-navy custom-font btn-block save">Send</button>                
                </div>
            </div>
        </form>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/1.1.2/js/bootstrap-multiselect.min.js"></script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/members/send-message.js"></script>
</body>
</html>  