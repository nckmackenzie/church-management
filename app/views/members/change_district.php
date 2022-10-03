<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
    
        <div class="row">
            <div class="col-md-6 mx-auto">
                <div class="msg mt-1"></div>
                <div class="card bg-light mt-5">
                    <div class="card-header">
                        Change Member District
                    </div>
                    <div class="card-body">
                        <div class="col-md-9">
                            <div class="form-group">
                                <label for="member">Member</label>
                                <select name="member" id="member" 
                                class="form-control form-control-sm select2">
                                    <?php foreach($data['members'] as $member) :?>
                                        <option value="<?php echo $member->ID;?>"><?php echo $member->memberName;?></option>    
                                    <?php endforeach; ?>
                                </select>
                                
                            </div>
                            <div class="form-group">
                                <label for="olddistrict">Current District</label>
                                <select name="olddistrict" id="olddistrict" class="form-control form-control-sm">
                                
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="newdistrict">New District</label>
                                <select name="newdistrict" id="newdistrict" 
                                        class="form-control form-control-sm">
                                    <?php foreach($data['districts'] as $district) :?>
                                        <option value="<?php echo $district->ID;?>"><?php echo $district->districtName;?></option>    
                                    <?php endforeach; ?>    
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-3">
                                    <button type="submit" id="save" class="btn btn-sm bg-navy custom-font">Save</button>
                                    <input type="hidden" name="oldname" id="oldname">
                                    <input type="hidden" name="newname" id="newname">
                                    <input type="hidden" name="name" id="name">
                                </div>
                            </div>
                        </div>
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

        function getDistrict(){
            var member = $('#member').val();
            $.ajax({
                url : '<?php echo URLROOT;?>/members/districtchange',
                method : 'POST',
                data : {member : member},
                success : function(html){
                    $('#olddistrict').html(html);
                }
            });
           
        }

        $(window).on('load', function() {
            getDistrict();
        });

        $('#member').change(function(){
            getDistrict();
        });

        $('#save').click(function(e){
            e.preventDefault();
             
            var oldname =  $('#olddistrict').find('option:selected').text();
            var newname =  $('#newdistrict').find('option:selected').text();
            var name =  $('#member').find('option:selected').text();
            $('#oldname').val(oldname);
            $('#newname').val(newname);
            $('#name').val(name);
            var old = $('#olddistrict').val();
            var newd = $('#newdistrict').val();
            var member = $('#member').val();
            
            $.ajax({
                url : '<?php echo URLROOT;?>/members/updatedistrict',
                method : 'POST',
                data : {member : member, 
                        old : old, 
                        newd : newd, 
                        oldname : oldname,
                        newname : newname,
                        name : name},
                success : function(res){
                    $('.msg').html(res);
                }
            });
        });
    });
</script>
</body>
</html>