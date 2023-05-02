<footer class="main-footer">
    <!-- To the right -->
    <div class="float-right d-none d-sm-inline">v2.0.1</div>
    <!-- Default to the left -->
    <strong
        >Copyright &copy; <?php echo date("Y");?>
        <a href="#">Designed By Mack Softwares</a>.</strong
    >
    All rights reserved.
</footer>
<div class="modal fade" id="dateModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Set Process Date</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/users/setdate" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Process Date</label>
                  <input type="date" name="date" id="date" class="form-control form-control-sm">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Save</button>
              </div>
          </form>
      </div>
     
    </div>
  </div>
</div>
</div>
<script src="<?php echo URLROOT; ?>/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo URLROOT; ?>/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/jquery-validation/jquery.validate.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/jquery-validation/additional-methods.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/select2/dist/js/select2.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/moment/moment.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/daterangepicker/daterangepicker.js"></script>
<script src="<?php echo URLROOT;?>/plugins/CustomDate/dist/DateTimePicker.min.js"></script>
<script src="<?php echo URLROOT;?>/plugins/bs-multiselect/dist/js/bootstrap-select.min.js"></script>
<script src="<?php echo URLROOT; ?>/dist/js/adminlte.min.js"></script>
<!-- <script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script> -->
<!-- <script src="<?php echo URLROOT; ?>/dist/js/main.js"></script> -->
<script>
    $(function(){
        $('.processdate').on('click', function(){
            $('#dateModalCenter').modal('show');
        });
    })
</script>