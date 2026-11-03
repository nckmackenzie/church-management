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
import { setError, clearError } from '../utils/feedback.js';
const txnDate = document.querySelector('#date');
const bank = document.querySelector('#bank');
const type = document.querySelector('#type');
const transfer = document.querySelector('#transferto');
const cashAccount = document.querySelector('#cashaccount');
const cashAccountLabel = document.querySelector('#cashaccountLabel');
const amount = document.querySelector('#amount');
const reference = document.querySelector('#reference');
const subaccounts = document.querySelector('#deposittosubs');
const form = document.querySelector('#txnform');
const subaccountsContainer = document.querySelector('#subaccountsContainer');
const btn = document.querySelector('.save');
const editing = !!document.querySelector('input[name="isedit"]')?.value;

// the cash account is chosen for deposits (credited) and withdrawals (debited) only
function toggleCashAccount() {
  const t = +type.value;
  if (t === 1 || t === 2) {
    cashAccount.disabled = false;
    cashAccountLabel.textContent =
      t === 1 ? 'Crediting account' : 'Debiting account';
  } else {
    cashAccount.value = '';
    cashAccount.disabled = true;
    cashAccount.classList.remove('is-invalid');
    cashAccount.nextSibling.nextSibling.textContent = '';
    cashAccountLabel.textContent = 'Cash account';
  }
}
toggleCashAccount();

cashAccount.addEventListener('change', function () {
  this.classList.remove('is-invalid');
  this.nextSibling.nextSibling.textContent = '';
});

type.addEventListener('change', e => {
  toggleCashAccount();
  subaccountsContainer.replaceChildren();
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

function renderTable(data, saved = []) {
  // sub account names are typed by an administrator: built with DOM nodes and textContent, never as markup
  const table = document.createElement('table');
  table.className = 'table table-sm';
  table.id = 'subaccounts';
  const head = table.createTHead().insertRow();
  [['d-none', 'AccountId'], ['', 'Sub Account'], ['', 'Amount']].forEach(([cls, text]) => {
    const th = document.createElement('th');
    if (cls) th.className = cls;
    th.textContent = text;
    head.appendChild(th);
  });
  const body = table.createTBody();
  data.forEach(item => {
    const tr = body.insertRow();
    const id = tr.insertCell();
    id.className = 'd-none subaccountid';
    id.textContent = item.ID;
    tr.insertCell().textContent = item.SubAccount;
    const input = document.createElement('input');
    input.type = 'number';
    input.className = 'table-input border w-100 payment';
    input.value = (saved.find(x => +x.id === +item.ID) || {}).amount ?? '';
    input.addEventListener('input', () => clearError(amount));
    tr.insertCell().appendChild(input);
  });
  subaccountsContainer.replaceChildren(table);
}

// editing a deposit that was split over sub accounts: show the saved split again, so that saving keeps it
async function showSavedSplit() {
  if (!subaccounts.checked || !bank.value) return;
  let saved = [];
  try {
    saved = JSON.parse(subaccountsContainer.dataset.saved || '[]');
  } catch (e) {}
  const response = await sendHttpRequest(
    `${HOST_URL}/banktransactions/getsubaccounts?bankid=${bank.value}`
  );
  if (response && response.success) {
    renderTable(response.data, saved);
  }
}
showSavedSplit();

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

  clearError(amount);
  if (+amount.value <= 0) {
    amount.classList.add('is-invalid');
    amount.nextSibling.nextSibling.textContent = 'Invalid amount';
    return;
  }

  if ((+type.value === 1 || +type.value === 2) && cashAccount.value === '') {
    cashAccount.classList.add('is-invalid');
    cashAccount.nextSibling.nextSibling.textContent = 'Select account';
    return;
  }

  if (+type.value === 5 && transfer.value === '') {
    transfer.classList.add('is-invalid');
    transfer.nextSibling.nextSibling.textContent = 'Select account';
    return;
  }

  if (subaccounts.checked) {
    const table = document.getElementById('subaccounts');
    if (!table) {
      setError(amount, 'Enter the amounts for the sub accounts');
      return;
    }
    const amountValue = amount.value;

    const entered = Math.round(getEnteredTotal(table) * 100);
    if (Math.round(+amountValue * 100) !== entered) {
      setError(
        amount,
        `Sub account amounts add up to ${(entered / 100).toFixed(2)}, not ${(+amountValue).toFixed(2)}`
      );
      return;
    }
  }

  setLoadingState(btn, 'Saving...');
  const data = await submitHandler();
  resetLoadingState(btn, 'Save');
  if (data.success && editing) {
    // the list shows the "Saved successfully" message the controller put in the session
    window.location.href = `${HOST_URL}/banktransactions`;
    return;
  }
  if (data.success) {
    clearValues();
    toggleCashAccount();
    subaccountsContainer.replaceChildren();
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
  // console.log(formFields);
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
