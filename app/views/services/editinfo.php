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
                <a href="<?php echo URLROOT;?>/services/service_info" class="btn btn-dark btn-sm mt-2"><i class="fas fa-backward"></i> Back</a>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="card card-body bg-light mt-5">
                    <h5>Edit Service Information</h5>
                    <hr>
                    <form action="<?php echo URLROOT;?>/services/updateinfo" method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="service">Services</label>
                                    <select name="service" id="service" class="form-control form-control-sm mandatory
                                    <?php echo (!empty($data['service_err'])) ? 'is-invalid': ''?>">
                                        <?php foreach($data['services'] as $service) :?>
                                            <option value="<?php echo $service->ID;?>"
                                            <?php selectdCheck($data['serviceinfo']->serviceId,$service->ID );?>>
                                                <?php echo strtoupper($service->serviceName);?>
                                            </option>
                                        <?php endforeach; ?>    
                                    </select>
                                    <span class="invalid-feedback"><?php echo $data['service_err'];?></span>       
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date">Service Date</label>
                                    <input type="date" name="date" id="date" 
                                           class="form-control form-control-sm mandatory
                                           <?php echo (!empty($data['date_err'])) ? 'is-invalid': ''?>"
                                           value="<?php echo $data['serviceinfo']->serviceDate;?>">
                                    <span class="invalid-feedback"><?php echo $data['date_err'];?></span>       
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="headed">Headed By</label>
                                    <input type="text" class="form-control form-control-sm"
                                    value="<?php echo strtoupper($data['serviceinfo']->headedBy);?>"
                                    name="headedby" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="preacher">Preacher</label>
                                    <input type="text" class="form-control form-control-sm"
                                    value="<?php echo strtoupper($data['serviceinfo']->preacher);?>"
                                    name="preacher" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="attendance">Service Attendance</label>
                                    <input type="text" class="form-control form-control-sm"
                                    value="<?php echo $data['serviceinfo']->attendance;?>"
                                    name="attendance" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="envelopepledge">Envelope Pledge</label>
                                    <input type="number" class="form-control form-control-sm"
                                    value="<?php echo $data['serviceinfo']->envelopePledge;?>"
                                    name="envelopepledge" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="ordinary">Ordinary</label>
                                    <input type="number" class="form-control form-control-sm"
                                    value="<?php echo $data['serviceinfo']->ordinary;?>"
                                    name="ordinary" autocomplete="off">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="special">Special</label>
                                    <input type="number" class="form-control form-control-sm"
                                    value="<?php echo $data['serviceinfo']->special;?>"
                                    name="special" autocomplete="off">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-2">
                                <button type="submit" class="btn btn-sm bg-navy custom-font">Save</button>
                                <input type="hidden" name="servicename" id="servicename">
                                <input type="hidden" name="id" value="<?php echo $data['serviceinfo']->ID;?>">
                            </div>
                            
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        function getServiceName(){
            var serviceName = $('#service').find('option:selected').text();
            $('#servicename').val(serviceName.trim());
        }
        $('#service').change(function(){
            getServiceName();
        });
        $('#date').focusout(function(){
            getServiceName();
        });
    });
</script>
</body>
</html>