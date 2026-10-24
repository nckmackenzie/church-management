import {
  numberWithCommas,
  displayAlert,
  setLoadingState,
  resetLoadingState,
  sendHttpRequest,
  HOST_URL,
} from '../utils/utils.js';
import { validateRequired, clearOnChange, clearError } from '../utils/feedback.js';
import { tableData, headerData } from './values-v2.js';
export const table = document.getElementById('paymentsTable');
const totalDiv = document.getElementById('total');
const form = document.querySelector('form');
const alertBox = document.getElementById('alertBox');
const btnSave = document.querySelector('.btnsave');
const methodSelect = document.getElementById('paymethod');
const cashSelect = document.getElementById('cashaccount');
const bankSelect = document.getElementById('bank');
const PAYMENT_CELL = 8;

// cash pays from a cash account, cheque / bank from a bank (the server validates the same lists)
function methodState() {
  const cash = +methodSelect.value === 1;
  cashSelect.disabled = !cash;
  bankSelect.disabled = cash;
  cashSelect.classList.toggle('mandatory', cash);
  bankSelect.classList.toggle('mandatory', !cash);
  if (cash) {
    bankSelect.value = '';
    clearError(bankSelect);
  } else {
    cashSelect.value = '';
    clearError(cashSelect);
  }
}
methodSelect.addEventListener('change', methodState);
methodState();

table.addEventListener('click', function (e) {
  if (!e.target.classList.contains('chkbx')) return;
  const checkBox = e.target;
  const tr = checkBox.closest('tr');
  const inputs = tr.querySelectorAll('.table-input');
  inputs.forEach(input => {
    if (checkBox.checked) {
      input.readOnly = false;
      input.classList.add('table-input-custom');
    } else {
      input.readOnly = true;
      input.classList.remove('table-input-custom');
      input.value = '';
    }
  });
  updateSubTotal(this);
});

table.addEventListener('change', function (e) {
  if (!e.target.classList.contains('payment')) return;
  updateSubTotal(this);
});

//get totals
function updateSubTotal(table) {
  let sumVal = 0;
  for (var i = 1; i < table.rows.length; i++) {
    const rowValue =
      parseFloat(table.rows[i].cells[PAYMENT_CELL].children[0].value) || 0;
    sumVal = sumVal + rowValue;
  }

  totalDiv.innerText = numberWithCommas(sumVal.toFixed(2));
}

function selectedCount() {
  return table.querySelectorAll('.chkbx:checked').length;
}

//form submit
form.addEventListener('submit', async function (e) {
  e.preventDefault();

  if (validateRequired() > 0) return;
  if (selectedCount() === 0) {
    displayAlert(alertBox, 'No payments selected for payment');
    return;
  }
  setLoadingState(btnSave, 'Saving...');
  const data = await sendHttpRequest(
    `${HOST_URL}/payments/create`,
    'POST',
    JSON.stringify({ header: headerData(), payments: tableData() }),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  if (data && data.success) {
    window.location.replace(`${HOST_URL}/payments`);
    return;
  }
  resetLoadingState(btnSave, 'Save');
});

clearOnChange(document.querySelectorAll('.mandatory'));
clearOnChange([cashSelect, bankSelect]);
