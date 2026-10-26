import { HOST_URL } from '../utils/utils.js';
import { getJournalNo,saveEntries } from './ajax.js';
import { addBtn,journalNoInput,form,saveBtn,table,currJouralInput,searchInput,searchBtn,deleteBtn,userTypeInput,resetBtn,tbody,dateInput } from './elements.js';
import { addToTable,appendEntry,validate,formData,removeSelected,clear,getJournal,updateTotals,showAlert } from './functionalities.js';

const validateBtn=document.querySelector('#validate'),fileInput=document.querySelector('#fileInput');
validateBtn.disabled=true;
async function reset(){validateBtn.disabled=true;addBtn.disabled=false;fileInput.value='';clear();const data=await getJournalNo();if(data&&data.success){journalNoInput.value=data.journalno;currJouralInput.value=data.journalno;}resetBtn.classList.add('d-none');if(userTypeInput.value&&+userTypeInput.value<3)deleteBtn.classList.add('d-none');tbody.replaceChildren();updateTotals();}
addBtn.addEventListener('click',addToTable);resetBtn.addEventListener('click',reset);
form.addEventListener('submit',async e=>{e.preventDefault();if(!validate())return;saveBtn.disabled=true;saveBtn.textContent='Saving...';const res=await saveEntries(formData());saveBtn.textContent='Save';if(res&&res.success){showAlert(res.message||'Saved successfully!','success');reset();}else updateTotals();});
searchInput.addEventListener('keypress',e=>{if(e.key==='Enter'){e.preventDefault();getJournal(+e.target.value);}});searchBtn.addEventListener('click',()=>{if(searchInput.value!=='')getJournal(+searchInput.value);});table.addEventListener('click',removeSelected);
if(+userTypeInput.value<3)deleteBtn.addEventListener('click',()=>{$('#deleteModalCenter').modal('show');document.getElementById('id').value=currJouralInput.value;});
fileInput.addEventListener('change',()=>{const f=fileInput.files[0];validateBtn.disabled=!f;if(f)fileInput.nextElementSibling.textContent=f.name;});
validateBtn.addEventListener('click',async e=>{
  e.preventDefault();const file=fileInput.files[0];if(!file)return;const body=new FormData();body.append('file',file);body.append('date',dateInput.value);
  validateBtn.disabled=true;validateBtn.textContent='Validating...';
  try{const response=await fetch(`${HOST_URL}/journals/validateimport`,{method:'POST',body});const res=await response.json();if(!response.ok)throw new Error(res.message||'Import validation failed');tbody.replaceChildren();res.entries.forEach(row=>appendEntry(row,res.accounts));if(res.journalDate)dateInput.value=res.journalDate;addBtn.disabled=true;updateTotals();showAlert(res.invalid?`${res.invalid} imported row(s) need attention`:'Import is ready to save',res.invalid?'warning':'success');}
  catch(error){showAlert(error.message||'The import could not be read');validateBtn.disabled=false;}
  finally{validateBtn.textContent='Validate';}
});
dateInput.addEventListener('change',()=>{dateInput.classList.remove('is-invalid');const f=dateInput.parentElement.querySelector('.invalid-feedback');if(f)f.textContent='';});
reset();
