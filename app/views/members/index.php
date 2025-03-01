<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<div class="modal fade" id="deleteModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Delete Member</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/members/delete" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Are You Sure You Want To Delete Selected Member?</label>
                  <input type="hidden" name="id" id="id">
                  <input type="hidden" name="membername" id="membername">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Yes</button>
              </div>
          </form>
      </div>
     
    </div>
  </div>
</div>
<div class="modal fade" id="resendModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLongTitle">Resend Update Link</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/members/resend" method="post">
              <div class="row">
                <div class="col-md-9">
                  <label for="">Resend Link To Selected Member?</label>
                  <input type="hidden" name="id" id="rid">
                  <input type="hidden" name="contact" id="contact">
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger">Yes</button>
              </div>
          </form>
      </div>
     
    </div>
  </div>
</div>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
      <?php flash('member_msg');?>
        <div class="row mb-2">
          <div class="col-sm-6">
            <a href="<?php echo URLROOT;?>/members/add" class="btn btn-success btn-sm custom-font">Add New</a>
          </div>
          <?php if((int)$_SESSION['userType'] !== 4) : ?>
            <div class="col-sm-3">
              <select name="filter" id="filter" class="form-control form-control-sm">
                <option value="" selected disabled>Filter by district</option>
              </select>
            </div>
          <?php endif; ?>
        </div>
      </div><!-- /.container-fluid -->
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-12 table-responsive">
                <table id="membersTable" class="table table-bordered table-striped table-sm">
                    <thead class="bg-navy">
                        <tr>
                            <th>ID</th>
                            <th>Member Name</th>
                            <th>Contact</th>
                            <th>Gender</th>
                            <th>District</th>
                            <th>Position</th>
                        <?php if ($_SESSION['userType'] <=2 ) : ?>
                            <th>Actions</th>
                        <?php endif; ?>    
                        </tr>
                    </thead>
                    <tbody>
                    
                            <?php foreach($data['members'] as $member) : ?>
                                <tr>
                                    <td><?php echo $member->ID;?></td> 
                                    <td><?php echo $member->memberName;?></td> 
                                    <td><?php echo $member->contact;?></td> 
                                    <td><?php echo $member->gender;?></td> 
                                    <td><?php echo $member->district;?></td> 
                                    <td><?php echo $member->position;?></td>
                                    <?php if ($_SESSION['userType'] <=2 ) : ?>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm bg-gray-dark custom-font resend" data-id="<?php echo $member->ID;?>" data-contact="<?php echo $member->contact;?>">Resend Update Link</button>
                                                <a href="<?php echo URLROOT;?>/members/edit/<?php echo $member->ID;?>" class="btn bg-olive custom-font btn-sm">Edit</a>
                                                <button class="btn btn-danger custom-font btn-sm btndel" data-id="<?php echo $member->ID;?>">Delete</button>
                                            </div>
                                        </td>
                                    <?php endif; ?> 
                                </tr>   
                            <?php endforeach; ?>
                          
                    </tbody>
                </table>
            </div>
        </div>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->

<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
      var urlParams = new URLSearchParams(window.location.search);
      var isRedirect = !!urlParams.get('redirect') || false;  
    
      var table = $('#membersTable').DataTable({
          'pageLength' : 25,
          'ordering' : false,
          "responsive": true,
          'columnDefs' : [
            { "visible" : false, "targets": 0},
            {"width" : "5%" , "targets": 2},
            {"width" : "10%" , "targets": 3}
         
            ,{"width" : "25%" , "targets": 6},

          ],
          "initComplete": function () {
          this.api().columns([4]).every(function () {
            var column = this;
            var select = $('#filter');

            select.on('change', function () {
                var val = $.fn.dataTable.util.escapeRegex(
                    $(this).val()
                );
                column.search(val ? '^' + val + '$' : '', true, false).draw();
            });

            column.data().unique().sort().each(function (d, j) {
                select.append('<option value="' + d + '">' + d + '</option>')
            });
          });
        }
      });

      if(isRedirect){
        table.page(parseInt(localStorage.getItem('membersPage'))).draw( 'page' );
      }

      $(document).on('click', '#membersTable_next', function () {
        localStorage.setItem('membersPage',table.page.info().page)
      });

      $('#membersTable').on('click','.btndel',function(){
          $('#deleteModalCenter').modal('show');
          $('#id').val($(this).data('id'));
      });
      $('#membersTable').on('click','.resend',function(){
          $('#resendModalCenter').modal('show');
          $('#rid').val($(this).data('id'));
          $('#contact').val($(this).data('contact'));
      });

    });
</script>
</body>
</html>    