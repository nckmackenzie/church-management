<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-md-9 mx-auto mt-2">
                <div class="card bg-light">
                    <div class="card-header">Edit Family Members</div>
                    <div class="card-body">
                        <form action="<?php echo URLROOT;?>/members/editfamily" method="post">
                            <div class="row">
                                <div class="col-md-6 mx-auto">
                                    <div class="form-group">
                                        <label for="member">Main Member</label>
                                        <select name="member" id="member"
                                                class="form-control form-control-sm select2"
                                                style="width: 100%;">
                                            <?php foreach($data['members'] as $member) : ?>
                                                <option value="<?php echo $member->ID;?>" <?php selectdCheck($data['member'],$member->ID); ?>><?php echo $member->memberName;?></option>    
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div><!--End Of Row -->
                            <hr>
                            <div class="row">
                                <div class="col-md-2">
                                     <div class="form-group">
                                          <label for="type">Type</label>      
                                          <select name="type" id="type" class="form-control form-control-sm">
                                             <option value="1">Member</option>
                                             <option value="0">Non Member</option>
                                          </select>
                                     </div>           
                                </div>
                                <div class="col-md-5" id="familypart">
                                    <div class="form-group">
                                        <label for="">Family Member</label>
                                        <select name="family" id="family" class="form-control form-control-sm"
                                                style="width: 100%;">
                                            <?php foreach($data['members'] as $member) : ?>
                                                <option value="<?php echo $member->ID;?>"><?php echo $member->memberName;?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="rship">Relationship</label>
                                        <select name="rship" id="rship" class="form-control form-control-sm">
                                            <?php foreach($data['relations'] as $relation) : ?>
                                                <option value="<?php echo $relation->ID;?>">
                                                    <?php echo $relation->relationship;?>
                                                </option>
                                            <?php endforeach; ?>   
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                            <label for="" style="color: #F4F6F9;">Add</label>
                                            <button type="button" id="add"
                                                    class="btn btn-sm btn-success custom-font form-control form-control-sm" 
                                                    >Add</button>
                                    </div>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-12 table-responsive">
                                    <table class="table table-sm table-bordered table-striped" id="familyTable">
                                        <thead class="bg-navy">
                                            <tr>
                                                <th style="display: none;">MemberID</th>
                                                <th width="70%">Member Name</th>
                                                <th style="display: none;">rid</th>
                                                <th width="20%">Relationship</th>
                                                <th style="display: none;">type</th>
                                                <th width="10%">Action</th>
                                                
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($data['family'] as $family) : ?>
                                                <tr>
                                                    <td class="d-none"><?php echo $family->mid;?></td>
                                                    <td><?php echo $family->memberName;?></td>
                                                    <td class="d-none"><?php echo $family->relationshipId;?></td>
                                                    <td><?php echo $family->relationship;?></td>
                                                    <td class="d-none"><?php echo $family->type;?></td>
                                                    <td>
                                                        <button type="button" class="btnRemove btn btn-danger btn-sm">Delete</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-2">
                                    <button type="submit" id="save" class="btn btn-sm bg-navy custom-font">Save</button>
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
$(function(){
    $('.select2').select2();
    $('#family').select2({
        placeholder: "Select a Family Member"
    });

   
    $('#type').change(function(){
        $('#familypart div').html('');
        var type = $(this).val();
        if (Number(type) === 0) {
            $('#familypart').html(
                `
                <div class="form-group">
                    <label for="">Family Member</label>
                    <input type="text" class="form-control form-control-sm" id="family" name="family"
                </div>
                `
            );
        }else{
            $('#familypart').html(
                `
                <label for="">Family Member</label>
                <select name="family" id="family" class="form-control form-control-sm"
                        style="width: 100%;">
                    <?php foreach($data['members'] as $member) : ?>
                        <option value="<?php echo $member->ID;?>"><?php echo $member->memberName;?></option>
                    <?php endforeach; ?>
                </select>
                `
            );
        }
    });

    //add button
    $('#add').click(function(){
        
        //validation
        if ($('#member').val() == '') {
            alert('Select Main Member');
            return false;
        }
        if ($('#family').val() == '' || $('#family').val() == null) {
            alert('Select Family Member');
            return false;
        }
      
        
        let input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("family");

        filter = input.value.toUpperCase();
       
        table = document.getElementById("familyTable");
        tr = table.getElementsByTagName("tr");
        
        for (i = 0; i < tr.length; i++) {
            td = tr[i].getElementsByTagName("td")[0];

            if (td) {
                txtValue = td.textContent || td.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    alert("Member Entered");
                    return false;
                    // tr[i].style.display = "";
                } else {
                    // tr[i].style.backgroundColor = "red";
                }
            }
        }

        var type = $('#type').val();
        if (Number(type) === 1) {
            var mid = $('#family').val();
            var memberName = $('#family').find('option:selected').text(); 
        }else{
            var memberName = $('#family').val();
            var mid = 0;
        }
        
        var rid = $('#rship').val();
        var relations = $('#rship').find('option:selected').text();
        //adding to grid
        $('#familyTable tbody:last-child').append(
                '<tr>'+
                    '<td style="display: none;">'+mid+'</td>'+
                    '<td>'+memberName+'</td>'+
                    '<td style="display: none;">'+rid+'</td>'+
                    '<td>'+relations+'</td>'+
                    '<td style="display: none;">'+type+'</td>'+
                    '<td><button type="button" class="btnRemove btn btn-danger btn-sm">Delete</button></td>'+
                    // '<td><a href="#" class="btnRemove">Remove</a></td>'+
                '</tr>'    
        );
        $('#rship').val(1);
        $("#family").val('').trigger('change');
    });
    $('#save').click(function(e){
        e.preventDefault();
        var rowCount = $('#familyTable >tbody >tr').length;
        // console.log(rowCount);
        if (Number(rowCount) == 0) {
            alert("Nothing Entered");
            return
        }

        var table_data = [];

        var member = $('#member').val();
        var membername = $('#member').find('option:selected').text();

        $('#familyTable tr').each(function(row,tr){

            if ($(tr).find('td:eq(0)').text() == '') {
    
            }
            else{
                var sub = {
                    'mid' : $(tr).find('td:eq(0)').text(),
                    'name' : $(tr).find('td:eq(1)').text(),
                    'rid' : $(tr).find('td:eq(2)').text(),
                    'type' : $(tr).find('td:eq(4)').text(),
                }
                table_data.push(sub);
            } 
            // console.log(table_data);   
        });

        $.ajax({
            url : '<?php echo URLROOT;?>/members/editfamily',
            method : 'POST',
            data : {member : member,membername : membername,table_data : table_data},
            success : function(data){
                // window.location.href = '/members/family';
                const basePath = window.location.pathname.split('/')[1];
                window.location.href = `/${basePath}/members/family`;
            }
        });
    });

    // $('#family').change(function(){
    //     var memberid = $(this).val();
    //     $("#family").val('').trigger('change');
    // });

    $('#member').change(function(){
        var memberid = $(this).val();
        $("#member").val('').trigger('change');
    });


    $('#familyTable').on('click','.btnRemove',function(){
        $(this).closest('tr').remove();          
    });
});


</script>
</body>
</html>  