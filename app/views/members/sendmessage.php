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
            <div class="card mx-auto col-md-6 mt-4 p-4">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            <label for="filter">Filter Members</label>
                            <select id="filter" name="filter"class="form-control  mandatory required">
                                <option value="all" selected>All</option>
                                <option value="district">By District</option>
                                <option value="group">By Group</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <div class="form-group">
                            <label for="group" id="label">District/Group</label>
                            <select id="district" name="district"class="form-control  mandatory required">

                            </select>
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <div class="form-group">
                            <label for="members">Select Members To Send Message To</label>
                            <select id="members" name="members[]"class="form-control  mandatory required" multiple>
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
                            <textarea class="form-control required mandatory " id="message" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-4 col-lg-2">
                        <button type="submit" class="btn btn-sm bg-navy custom-font btn-block save">Send</button>                
                    </div>
                </div>
            </div>
        </form>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-multiselect/1.1.2/js/bootstrap-multiselect.min.js"></script>
<script type="module" src="<?php echo URLROOT;?>/dist/js/pages/members/send-message-v1.js"></script>
</body>
</html>  