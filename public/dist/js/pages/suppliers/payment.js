import {
  numberWithCommas,
  displayAlert,
  validation,
  clearOnChange,
  mandatoryFields,
  setLoadingState,
  resetLoadingState,
  sendHttpRequest,
  HOST_URL,
} from '../utils/utils.js';
import { tableData, headerData } from './values.js';
export const table = document.getElementById('paymentsTable');
const totalDiv = document.getElementById('total');
const form = document.querySelector('form');
const alertBox = document.getElementById('alertBox');
const btnSave = document.querySelector('.btnsave');
let selectedInvoices = 0;

table.addEventListener('click', function (e) {
  if (!e.target.classList.contains('chkbx')) return;
  const checkBox = e.target;
  const tr = checkBox.closest('tr');
  const inputs = tr.querySelectorAll('.table-input');
  inputs.forEach(input => {
    if (checkBox.checked) {
      input.readOnly = false;
      input.classList.add('table-input-custom');
      selectedInvoices++;
    } else {
      input.readOnly = true;
      input.classList.remove('table-input-custom');
      input.value = '';
      selectedInvoices--;
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
    const rowValue = parseFloat(table.rows[i].cells[7].children[0].value) || 0;
    sumVal = sumVal + rowValue;
  }

  totalDiv.innerText = numberWithCommas(sumVal.toFixed(2));
}

//form submit
form.addEventListener('submit', async function (e) {
  e.preventDefault();

  if (validation() > 0) return;
  if (selectedInvoices === 0) {
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
  resetLoadingState(btnSave, 'Save');
  if (data && data.success) {
    window.location.replace(`${HOST_URL}/payments`);
  }
});

clearOnChange(mandatoryFields);
