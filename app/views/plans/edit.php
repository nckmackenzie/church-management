<?php require APPROOT . '/views/inc/header.php';?>
<?php require APPROOT . '/views/inc/topNav.php';?>
<?php require APPROOT . '/views/inc/sideNav.php';?>
<!-- Modal -->
<div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Add Activity</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
          <form action="<?php echo URLROOT;?>/activitys/create" method="post">
               <div class="row">
                   <div class="col-md-12">
                        <div class="form-group">
                            <label for="name">Activity Name</label>
                            <input type="text" name="name" id="name"
                                   class="form-control form-control-sm mandatory"
                                   placeholder="Enter Activity Name"
                                   autocomplete="off">
                            <span class="invalid-feedback" id="name_err"></span>
                        </div>
                   </div>
               </div> 
               <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" id="close" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="newactivity">Save</button>
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
        <div class="row mb-2">
          <div class="col-sm-6">

          </div>
          <div class="col-sm-6">

          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
        
        <form action="<?php echo URLROOT;?>/plans/update" method="post" enctype="multipart/form-data">    
            <div class="row">
                <div class="col-md-2 mb-3">
                    <label for="level">Level <sup>*</sup></label>
                    <select name="level" id="level"
                            class="form-control form-control-sm
                            <?php echo (!empty($data['level_err'])) ? 'is-invalid' : '' ?>">
                        <option value="0" selected disabled>Select Level</option>
                        <option value="1" <?php selectdCheckEdit($data['level'],$data['plan']->Level,1) ?>>Parish</option>
                        <option value="2" <?php selectdCheckEdit($data['level'],$data['plan']->Level,2) ?>>LCC</option>
                        <option value="3" <?php selectdCheckEdit($data['level'],$data['plan']->Level,3) ?>>Group</option>
                        <option value="4" <?php selectdCheckEdit($data['level'],$data['plan']->Level,4) ?>>District</option>
                    </select>
                    <span class="invalid-feedback"><?php echo $data['level_err'];?></span>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="year">Financial Year <sup>*</sup></label>
                    <select name="year" id="year" 
                            class="form-control form-control-sm
                            <?php echo (!empty($data['year_err'])) ? 'is-invalid' : '' ?>">
                        <?php foreach($data['years'] as $year) : ?>
                            <option value="<?php echo $year->ID;?>" <?php selectdCheckEdit($data['year'],$data['plan']->FiscalYearId,$year->ID) ?>>
                                <?php echo $year->yearName;?>
                            </option>
                        <?php endforeach;?>    
                    </select>
                    <span class="invalid-feedback"><?php echo $data['year_err'];?></span>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="planname">Work Plan Name</label>
                    <input type="text" name="planname" id="planname" 
                        class="form-control form-control-sm" 
                        value="<?php echo !(empty($data['planname'])) ? $data['planname'] : strtoupper($data['plan']->WorkPlanName) ;?>"
                        readonly>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="theme">Theme / Outcome Area <sup>*</sup></label>
                    <select name="theme" id="theme"
                            class="form-control form-control-sm
                            <?php echo (!empty($data['theme_err'])) ? 'is-invalid' : '' ?>">
                        <option value="0" selected disabled>Select Theme</option>
                        <option value="1" <?php selectdCheckEdit($data['theme'],$data['plan']->Theme,1) ?>>Benchmark Implementation</option>    
                        <option value="2" <?php selectdCheckEdit($data['theme'],$data['plan']->Theme,2) ?>>Implementation Of Core Activities (Spirirual Grwoth and Evangelism)</option>    
                        <option value="3" <?php selectdCheckEdit($data['theme'],$data['plan']->Theme,3) ?>>Financial Perfomance/Stability</option>   
                        <option value="4" <?php selectdCheckEdit($data['theme'],$data['plan']->Theme,4) ?>>Church Social Responsibility Strengthened</option>   
                        <option value="5" <?php selectdCheckEdit($data['theme'],$data['plan']->Theme,5) ?>>Physical Growth and development</option>   
                        <option value="6" <?php selectdCheckEdit($data['theme'],$data['plan']->Theme,6) ?>>Governance (Leadership, Administration and Management)</option>   
                    </select>
                    <span class="invalid-feedback"><?php echo $data['theme_err'];?></span>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="meetingdate">Meeting Date</label>
                    <input type="date" name="meetingdate" id="meetingdate" 
                        class="form-control form-control-sm
                        <?php echo (!empty($data['mdate_err'])) ? 'is-invalid' : '' ?>" 
                        value="<?php echo !empty($data['meetingdate']) ? $data['meetingdate'] : $data['plan']->MeetingDate ;?>"
                        disabled>
                    <span class="invalid-feedback"><?php echo $data['mdate_err'];?></span>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="activity">Activity <sup>*</sup></label>
                    <select name="activity" id="activity" 
                            class="form-control form-control-sm mandatory 
                            <?php echo (!empty($data['activity_err'])) ? 'is-invalid' : '' ?>">
                        <option value="0"><strong>Add New</strong></option>
                        <?php foreach($data['activities'] as $activity) : ?>
                            <option value="<?php echo $activity->ID;?>" <?php selectdCheckEdit($data['activity'],$data['plan']->Activty,$activity->ID) ?>>
                                <?php echo $activity->ActivityName;?>
                            </option>
                        <?php endforeach;?>
                    </select>
                    <span class="invalid-feedback"><?php echo $data['activity_err'];?></span>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="reason">Reason / Purpose Of Activity <sup>*</sup></label>
                    <input type="text" name="reason" id="reason" 
                        class="form-control form-control-sm
                        <?php echo (!empty($data['reason_err'])) ? 'is-invalid' : '' ?>"
                        value="<?php echo !empty($data['reason']) ? $data['reason'] : strtoupper($data['plan']->Reason);?>">
                    <span class="invalid-feedback"><?php echo $data['reason_err'];?></span>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="costestimate">Estimated Cost <sup>*</sup></label>
                    <input type="number" name="costestimate" id="costestimate"
                           value="<?php echo !empty($data['costestimate']) ? $data['costestimate'] : $data['plan']->EstimatedCost ;?>"
                           class="form-control form-control-sm
                           <?php echo (!empty($data['estimate_err'])) ? 'is-invalid' : '' ;?>">
                    <span class="invalid-feedback"><?php echo $data['estimate_err'];?></span>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="fromdate">From Date <sup>*</sup></label>
                    <input type="date" name="fromdate" id="fromdate" 
                        class="form-control form-control-sm
                        <?php echo (!empty($data['fdate_err'])) ? 'is-invalid' : '' ?>"
                        value="<?php echo !empty($data['fromdate']) ? $data['fromdate'] : $data['plan']->FromDate ;?>">
                    <span class="invalid-feedback"><?php echo $data['fdate_err'];?></span>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="todate">To Date <sup>*</sup></label>
                    <input type="date" name="todate" id="todate" 
                           class="form-control form-control-sm
                           <?php echo (!empty($data['tdate_err'])) ? 'is-invalid' : '' ?>"
                           value="<?php echo !empty($data['todate']) ? $data['todate'] : $data['plan']->ToDate ;?>">
                    <span class="invalid-feedback"><?php echo $data['tdate_err'];?></span>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="actualdate">Actual Implementation Date</label>
                    <input type="date" name="actualdate" id="actualdate"
                           value="<?php echo !empty($data['actualdate']) ? $data['actualdate'] : $data['plan']->ImplementaionDate ;?>"
                           class="form-control form-control-sm
                           <?php echo (!empty($data['adate_err'])) ? 'is-invalid' : '' ?>">
                    <span class="invalid-feedback"><?php echo $data['adate_err'];?></span>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="account">Budget Account <sup>*</sup></label>
                    <select name="account" id="account" 
                            class="form-control form-control-sm
                            <?php echo (!empty($data['account_err'])) ? 'is-invalid' : '' ?>">
                        <?php foreach($data['accounts'] as $account) : ?>
                            <option value="<?php echo $account->ID;?>" <?php selectdCheckEdit($data['account'],$data['plan']->AccountId,$account->ID) ?>>
                            <?php echo $account->accountType;?>
                            </option>
                        <?php endforeach;?>
                    </select>
                    <span class="invalid-feedback"><?php echo $data['account_err'];?></span>
                </div>
                <div class="col-md-2 mb-3">
                    <label for="office">Office Responsible <sup>*</sup></label>
                    <select name="office" id="office"
                            class="form-control form-control-sm
                            <?php echo (!empty($data['office_err'])) ? 'is-invalid' : '' ?>">
                        <option value="0" selected disabled>Select Office</option>
                        <option value="1" <?php selectdCheckEdit($data['office'],$data['plan']->OfficeResponsible,1)?>>Chairperson</option>
                        <option value="2" <?php selectdCheckEdit($data['office'],$data['plan']->OfficeResponsible,2)?>>Secretary</option>
                        <option value="3" <?php selectdCheckEdit($data['office'],$data['plan']->OfficeResponsible,3)?>>Treasurer</option>
                        <option value="4" <?php selectdCheckEdit($data['office'],$data['plan']->OfficeResponsible,4)?>>Parish Minister</option>
                        <option value="5" <?php selectdCheckEdit($data['office'],$data['plan']->OfficeResponsible,5)?>>Other</option>
                    </select>
                    <span class="invalid-feedback"><?php echo $data['office_err'];?></span>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="officeother">Other</label>
                    <input type="text" name="officeother" id="officeother" 
                           class="form-control form-control-sm
                           <?php echo (!empty($data['other_err'])) ? 'is-invalid' : '' ?>" 
                           value="<?php echo !empty($data['officeother']) ? $data['officeother'] : $data['plan']->Other;?>"
                           disabled>
                    <span class="invalid-feedback"><?php echo $data['other_err'];?></span>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="collaborator">Collaborator</label>
                    <select name="collaborator" id="collaborator" 
                            class="form-control form-control-sm">
                        <?php foreach($data['collaborators'] as $collaborator) : ?>
                            <option value="<?php echo $collaborator->ID;?>" 
                            <?php selectdCheckEdit($data['collaboratorName'],$data['plan']->Collaborator,$collaborator->categoryName)?>><?php echo $collaborator->categoryName;?></option>
                        <?php endforeach;?>
                        <?php if($data['plan']->Collaborator == null) : ?>
                            <option value="" selected disabled>Select Collaborator</option>
                        <?php endif;?>
                    </select>
                    <input type="hidden" name="collobatorName" id="collobatorName"
                           value="<?php echo !empty($data['collaboratorName']) ? $data['collaboratorName'] : $data['plan']->Collaborator;?>">
                </div>
                <hr size="8" width="100%" color="#999">
                <div class="col-md-3 mb-3">
                    <label for="results">Results</label>
                    <input type="number" name="results" id="results" 
                           class="form-control form-control-sm"
                           value="<?php echo !empty($data['results']) ? $data['results'] : $data['plan']->Results;?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="actualcost">Actual Cost <sup>*</sup></label>
                    <input type="number" name="actualcost" id="actualcost" 
                           class="form-control form-control-sm
                           <?php echo !(empty($data['acost_err'])) ? 'is-invalid' : '';?>"
                           value="<?php echo !empty($data['actualcost']) ? $data['actualcost'] : $data['plan']->ActualCost;?>">
                    <span class="invalid-feedback"><?php echo $data['acost_err'];?></span>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="evidence">Evidence Of Activity <sup>*</sup></label>
                    <input type="text" name="evidence" id="evidence" 
                           class="form-control form-control-sm
                           <?php echo !(empty($data['evidence_err'])) ? 'is-invalid' : '';?>"
                           value="<?php echo !empty($data['evidence']) ? $data['evidence'] : $data['plan']->EvidenceOfActivity;?>"
                           placeholder="eg Attendance List">
                    <span class="invalid-feedback"><?php echo $data['evidence_err'];?></span>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="remarks">Remarks</label>
                    <input type="text" name="remarks" id="remarks" 
                        class="form-control form-control-sm">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="attachment">Attachment</label>
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="attachment" name="file">
                        <label class="custom-file-label" for="customFile">Choose file (less than 1mb)</label>
                    </div>
                </div>
                <?php if(!$data['plan']->FileName == null) : ?>
                    <div class="col-md-2">
                        <label for="" style="color: #F4F6F9">link</label>
                        <a class="btn btn-success form-control" target="_blank" href="<?php echo URLROOT;?>/img/<?php echo $data['plan']->FileName;?>">View Attachment</a>
                    </div>
                <?php endif;?>
            </div>
            <input type="hidden" name="id" value="<?php echo $data['plan']->ID;?>">   
            <div class="row">
                <div class="col-md-6 mb-3">
                    <input type="submit" class="btn btn-sm bg-navy custom-font" value="Save" name="save"></input>
                    <input type="submit" class="btn btn-sm btn-primary custom-font" value="Submit" name="submit"></input>
                </div>
            </div> 
        </form>
    </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<?php require APPROOT . '/views/inc/footer.php'?>
<script>
    $(function(){
        // $('.select2').select2();

        // $(".select2").val('').trigger('change');

       
        $('#activity').on('change',function(){
            // console.log('asda');
            var activity = Number($(this).val());
            if (activity === 0) {
               $('#addModal').modal('show');
            }
        });

        function getPlanName(){
            var planName = '';
            if ($('#year').val() !== '' && $('#level').val() !== '') {
                var yearName = $('#year').find('option:selected').text().trim().toUpperCase();
                var levelName = $('#level').find('option:selected').text().trim().toUpperCase();
                planName = `${levelName}-${yearName}`;
                $('#planname').val(planName);
            }
        }

        $('#collaborator').change(function(){
            // console.log('here');
            var collaboratorName = $('#collaborator').find('option:selected').text().trim();
            $('#collobatorName').val(collaboratorName)
        });

        $('#level').change(function(){
            getPlanName();
        });

        $('#year').change(function(){
            getPlanName();
        })

        $('#close').click(function(){
            $("#activity").val('').trigger('change');
        });

        //reload products
        function reloadActivities(){
            $.ajax({
                url : '<?php echo URLROOT;?>/plans/reloadactivities',
                method : 'GET',
                data : {},
                success : function(html){
                    $('#activity').html(html);
                }
            });
        }

        $('#newactivity').click(function(e){
            e.preventDefault();
            var error ='';
            var checkNameResult = '';
            var activity = '';

            if($('#name').val() == ''){
                $('#name').addClass('is-invalid');
                error = 'Enter Activity Name'
                $('#name_err').text(error);
                return
            }
            else{
                $('#name').removeClass('is-invalid');
                $('#name_err').text();
                activity = $('#name').val();
            }
            
            $.ajax({
                url : '<?php echo URLROOT;?>/plans/checkname',
                method : 'GET',
                data : {activity : activity},
                success : function(dataReceived){
                    checkNameResult = dataReceived
                }
            });

            if (+checkNameResult === 1) {
                $('#name').addClass('is-invalid');
                error = 'Activity Name Exists'
                $('#name_err').text(error);
                return
            }else{
                $('#name').removeClass('is-invalid');
                $('#name_err').text();
            }

            $.ajax({
                url : '<?php echo URLROOT;?>/plans/createactivity',
                method : 'POST',
                data : {activity : activity},
                success : function(data){
                    reloadActivities();
                    $('#name').val('');
                    $('#addModal').modal('toggle');
                }
            });
        });

        $('#fromdate').on('change',function(){
            var selectedDate = $('#fromdate').val();
            var selectedToDate = $('#todate').val();
           
            if (selectedToDate == '') {
                // console.log('Changed');
                $('#todate').val(selectedDate);
            }
        });

        function enableDisableControls(num,cntrl){
            var office = Number($(cntrl).val());
            
            if (office === num) {
                $(cntrl).attr('disabled',false);
                $(cntrl).focus();
            }else{
                $(cntrl).attr('disabled',true);
            }
            $(cntrl).val('');
        }

        $('#office').change(function(){
            var office = Number($(this).val());
            
            if (office === 5) {
                $('#officeother').attr('disabled',false);
                $('#officeother').focus();
            }else{
                $('#officeother').attr('disabled',true);
            }
            $('#officeother').val('');
        });

        $('#theme').change(function(){
            var theme = Number($(this).val());
            
            if (theme === 6) {
                $('#meetingdate').attr('disabled',false);
                $('#meetingdate').focus();
            }else{
                $('#meetingdate').attr('disabled',true);
            }
            $('#meetingdate').val('');
        });

        $('#level').change(function(){
            var level = $(this).val();
            $.ajax({
                url : '<?php echo URLROOT;?>/plans/getcollaborator',
                method : 'GET',
                data : {level : level},
                success : function(html){
                    $('#collaborator').html(html);
                    $("#collaborator").val('').trigger('change');
                }
            });
            
        });
    });
</script>
</body>
</html>  