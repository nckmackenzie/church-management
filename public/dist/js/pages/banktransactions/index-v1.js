import {
  mandatoryFields,
  clearOnChange,
  validation,
  alertBox,
  setLoadingState,
  resetLoadingState,
  sendHttpRequest,
  HOST_URL,
  clearValues,
  displayAlert,
} from '../utils/utils.js';
const txnDate = document.querySelector('#date');
const bank = document.querySelector('#bank');
const type = document.querySelector('#type');
const transfer = document.querySelector('#transferto');
const amount = document.querySelector('#amount');
const reference = document.querySelector('#reference');
const subaccounts = document.querySelector('#deposittosubs');
const form = document.querySelector('#txnform');
const subaccountsContainer = document.querySelector('#subaccountsContainer');
const btn = document.querySelector('.save');

type.addEventListener('change', e => {
  subaccountsContainer.innerHTML = '';
  if (+e.target.value === 5) {
    transfer.disabled = false;
  } else {
    transfer.value = '';
    transfer.disabled = true;
  }

  if (+e.target.value === 1) {
    subaccounts.disabled = false;
  } else {
    subaccounts.disabled = true;
  }
  subaccounts.checked = false;
});

transfer.addEventListener('change', function () {
  this.classList.remove('is-invalid');
  this.nextSibling.nextSibling.textContent = '';
});

subaccounts.addEventListener('change', async function (e) {
  // console.log(e.target.checked);
  const bankValue = bank.value;
  if (!bankValue || bankValue == '') return;

  const response = await sendHttpRequest(
    `${HOST_URL}/banktransactions/getsubaccounts?bankid=${bankValue}`
  );

  if (response && response.success) {
    renderTable(response.data);
  }
});

function renderTable(data) {
  let html = '';
  html += `
    <table class="table table-sm" id="subaccounts">
      <thead>
        <tr>
          <th class="d-none">AccountId</th>
          <th>Sub Account</th>
          <th>Amount</th>
        </tr>
      </thead>
      <tbody>`;
  data.forEach(data => {
    html += `
      <tr>
        <td class="d-none subaccountid">${data.ID}</td>
        <td>${data.SubAccount}</td>
        <td><input type="number" class="table-input border w-100 payment" /></td>
      </tr>`;
  });
  html += `  
      </tbody>
    </table>`;
  subaccountsContainer.innerHTML = html;
}

function getEnteredTotal(table) {
  let sumVal = 0;
  for (var i = 1; i < table.rows.length; i++) {
    const rowValue = parseFloat(table.rows[i].cells[2].children[0].value) || 0;
    sumVal = sumVal + rowValue;
  }
  return sumVal;
  // totalDiv.innerText = numberWithCommas(sumVal.toFixed(2));
}

form.addEventListener('submit', async e => {
  e.preventDefault();
  if (validation() > 0) return;

  if (new Date(txnDate.value).getTime() > new Date().getTime()) {
    txnDate.classList.add('is-invalid');
    txnDate.nextSibling.nextSibling.textContent = 'Invalid date';
    return;
  }

  if (+amount.value < 0) {
    amount.classList.add('is-invalid');
    amount.nextSibling.nextSibling.textContent = 'Invalid amount';
    return;
  }

  if (+type.value === 5 && transfer.value === '') {
    transfer.classList.add('is-invalid');
    transfer.nextSibling.nextSibling.textContent = 'Select account';
    return;
  }

  if (subaccounts.checked) {
    const table = document.getElementById('subaccounts');
    const amountValue = amount.value;

    if (+amountValue !== getEnteredTotal(table)) {
      alert("Amounts Don't match");
      return;
    }
  }

  setLoadingState(btn, 'Saving...');
  const data = await submitHandler();
  resetLoadingState(btn, 'Save');
  if (data.success) {
    clearValues();
    displayAlert(alertBox, 'Saved successfully', 'success');
  }
});

export const tableData = () => {
  const table = document.getElementById('subaccounts');
  const tableData = [];
  const trs = table.getElementsByTagName('tbody')[0].querySelectorAll('tr');
  trs.forEach(tr => {
    const accountid = tr.querySelector('.subaccountid').textContent;
    const amount = tr.querySelector('.payment').value;
    tableData.push({ accountid, amount });
  });

  return tableData;
};

async function submitHandler() {
  const formdata = Object.fromEntries(new FormData(form).entries());
  const formFields = {
    ...formdata,
    subaccounts: subaccounts.checked ? tableData() : [],
  };
  console.log(formFields);
  const response = await sendHttpRequest(
    `${HOST_URL}/banktransactions/createupdate`,
    'POST',
    JSON.stringify(formFields),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  return response;
}

clearOnChange(mandatoryFields);
