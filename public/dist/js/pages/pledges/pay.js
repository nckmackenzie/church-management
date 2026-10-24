const method=document.getElementById('paymethod'),cash=document.getElementById('cashaccount'),bank=document.getElementById('bank'),reference=document.getElementById('reference');
function state(){const m=Number(method.value);cash.disabled=m!==1;bank.disabled=m<=1;reference.disabled=m<=1;cash.required=m===1;bank.required=m>1;reference.required=m>1;if(m===1){bank.value='';reference.value='';}else{cash.value='';}}
method.addEventListener('change',state);state();
