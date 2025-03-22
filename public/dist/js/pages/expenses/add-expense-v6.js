import { sendHttpRequest, HOST_URL } from '../utils/utils.js';
const accountSelect = document.getElementById('account');
const expenseDateInput = document.getElementById('date');
const voucherNoInput = document.getElementById('voucher');
const expenseTypeSelect = document.getElementById('expensetype');
const costCenterSelect = document.getElementById('costcentre');
const paymethodSelect = document.getElementById('paymethod');
const cashtypeSelect = document.getElementById('cashtype');
const requisitionSelect = document.getElementById('reqid');
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

expenseDateInput.addEventListener('change', async function (e) {
  const date = e.target.value;
  const data = await sendHttpRequest(
    `${expenseRoute}/voucherno?txndate=${date}`
  );
  voucherNoInput.value = data;
});

expenseTypeSelect.addEventListener('change', async function (e) {
  accountSelect.innerHTML = '';
  removeOptions(requisitionSelect);
  requisitionSelect.disabled = true;
  const selectedValue = e.target.value;
  const data = await sendHttpRequest(
    `${expenseRoute}/getaccounts?type=${
      selectedValue == '2' ? selectedValue : '1'
    }`
  );

  accountSelect.innerHTML =
    '<option value="" selected disabled>Select account</option>';

  data.length &&
    data.forEach(req => {
      let html = '';
      html += `
        <option value="${req.id}" >${req.label}</option>
    `;
      accountSelect.insertAdjacentHTML('beforeend', html);
    });

  if (+e.target.value === 2) {
    // let newOption = new Option('Group Petty Cash', 'group petty cash');
    // cashtypeSelect.add(newOption, undefined);
    // cashtypeSelect.value = 'group petty cash';
  } else {
    if (cashtypeSelect.options[2]) {
      cashtypeSelect.options[2] = null;
      cashtypeSelect.value = 'petty cash';
    }
  }
});

costCenterSelect.addEventListener('change', async function (e) {
  const type = Number(expenseTypeSelect.value?.trim());
  if (!type || type === 1) return;
  getRequisitions(e.target.value, type);
});

async function getRequisitions(group, type) {
  removeOptions(requisitionSelect);
  const data = await sendHttpRequest(
    `${expenseRoute}/getrequisitions?group=${group}&type=${type}`
  );

  if (data.length) {
    requisitionSelect.disabled = false;
    data.forEach(dt => {
      let newOption = new Option(dt.label, dt.id);
      requisitionSelect.add(newOption, undefined);
    });
    cashtypeSelect.value = 'cash holding';
  } else {
    requisitionSelect.disabled = false;
  }
}

function removeOptions(selectElement) {
  var i,
    L = selectElement.options.length - 1;
  for (i = L; i >= 0; i--) {
    selectElement.remove(i);
  }
}

accountSelect.addEventListener('change', async function (e) {
  const type = Number(expenseTypeSelect.value?.trim());
  if (!e.target.value || e.target.value == '' || type === 3) return;
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
