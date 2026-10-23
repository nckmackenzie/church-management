import { clearErrors } from '../reports/utils.js';
import { createPledge, getPledgers } from './ajax.js';
import { clearOnChange,displayAlert,mandatoryFields,validation,setLoadingState,resetLoadingState,alertBox } from '../utils/utils.js';

const form=document.getElementById('pledge-form'),category=document.getElementById('category'),pledger=document.getElementById('pledger'),paid=document.getElementById('amountpaid'),method=document.getElementById('paymethod'),cash=document.getElementById('cashaccount'),bank=document.getElementById('bank'),reference=document.getElementById('reference'),save=document.querySelector('.save');
category.addEventListener('change',async e=>{pledger.innerHTML=await getPledgers(+e.target.value);});
function accountState(){const has=Number(paid.value)>0,m=Number(method.value);method.disabled=!has;cash.disabled=!has||m!==1;bank.disabled=!has||m<=1;reference.disabled=!has||m<=1;method.required=has;cash.required=has&&m===1;bank.required=has&&m>1;reference.required=has&&m>1;if(m===1){bank.value='';reference.value='';}else{cash.value='';}}
paid.addEventListener('input',accountState);method.addEventListener('change',accountState);accountState();
form.addEventListener('submit',async e=>{e.preventDefault();clearErrors(document.querySelectorAll('.mandatory'));if(validation()>0)return;setLoadingState(save,'Saving...');const res=await createPledge({category:category.value,pledger:pledger.value,date:document.getElementById('date').value,pledged:document.getElementById('amountpledged').value,paid:paid.value,incomeaccount:document.getElementById('incomeaccount').value,paymethod:method.value,cashaccount:cash.value,bank:bank.value,reference:reference.value});resetLoadingState(save,'Save');if(res&&res.success){displayAlert(alertBox,'Pledge created successfully','success');form.reset();accountState();$('#pledger').val('').trigger('change');}});
clearOnChange(mandatoryFields);
