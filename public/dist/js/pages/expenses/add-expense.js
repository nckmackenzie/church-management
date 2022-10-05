import { sendHttpRequest, HOST_URL } from '../utils/utils.js';
const accountSelect = document.getElementById('account');
const expenseDateInput = document.getElementById('date');
const expenseTypeSelect = document.getElementById('expensetype');
const costCenterSelect = document.getElementById('costcentre');
const paymethodSelect = document.getElementById('paymethod');
const cashtypeSelect = document.getElementById('cashtype');
const expenseRoute = `${HOST_URL}/expenses`;

paymethodSelect.addEventListener('change', function (e) {
  if (Number(e.target.value) === 1) {
    cashtypeSelect.value = 'petty cash';
    cashtypeSelect.disabled = false;
    cashtypeSelect.classList.add('mandatory');
  } else {
    cashtypeSelect.value = '';
    cashtypeSelect.disabled = true;
    cashtypeSelect.classList.remove('mandatory');
  }
});

accountSelect.addEventListener('change', async function (e) {
  if (!e.target.value || e.target.value == '') return;
  if (await checkOverSpent()) {
    $('#alertModal').modal('show');
  }
});

async function checkOverSpent() {
  if (
    expenseDateInput.value == '' ||
    accountSelect.value == '' ||
    expenseTypeSelect.value == '' ||
    (+expenseTypeSelect.value === 2 && costCenterSelect.value == '')
  )
    return;
  let url;
  const edate = expenseDateInput.value;
  const type = expenseTypeSelect.value;
  const aid = accountSelect.value;
  if (+expenseTypeSelect.value === 1) {
    url = `${expenseRoute}/checkoverspent?edate=${edate}&type=${type}&aid=${aid}`;
  } else if (+expenseTypeSelect.value === 2) {
    const gid = costCenterSelect.value;
    url = `${expenseRoute}/checkoverspent?edate=${edate}&type=${type}&aid=${aid}&gid=${gid}`;
  }
  const data = await sendHttpRequest(url);
  return data;
}
