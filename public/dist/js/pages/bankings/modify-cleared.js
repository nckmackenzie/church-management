// prettier-ignore
import { clearOnChange, validation, mandatoryFields,validateDate, numberWithCommas,
    displayAlert,alertBox,setLoadingState,resetLoadingState,sendHttpRequest,HOST_URL } from '../utils/utils.js';
// prettier-ignore
import {clearForm, addBtn,fromDateInput,toDateInput,bankSelect,
    table,saveBtn } from './elements.js';
//prettier-ignore
import {setLoadingSpinner,removeLoadingSpinner,clear} from './functionalities.js'

let selectedBankings = 0;

//fetch details
addBtn.addEventListener('click', async function () {
  if (validation() > 0) return;

  if (!validateDate(fromDateInput, toDateInput)) return;
  table.getElementsByTagName('tbody')[0].innerHTML = '';
  const fromValue = fromDateInput.value;
  const toValue = toDateInput.value;
  const bankValue = bankSelect.value;
  setLoadingSpinner();
  const data = await getBankings(bankValue, fromValue, toValue);
  removeLoadingSpinner();
  if (data) {
    const { bankings } = data;
    appendData(bankings);
  }
});

table.addEventListener('click', function (e) {
  if (!e.target.classList.contains('chkbx')) return;
  const checkBox = e.target;
  const tr = checkBox.closest('tr');
  const inputs = tr.querySelectorAll('.table-input');
  inputs.forEach(input => {
    if (checkBox.checked) {
      if (input.classList.contains('cleardate')) {
        input.readOnly = false;
        input.setAttribute('type', 'date');
        input.classList.add('bg-white', 'form-control', 'form-control-sm');
      }
      selectedBankings++;
    } else {
      if (input.classList.contains('cleardate')) {
        input.readOnly = false;
        input.setAttribute('type', 'text');
        input.classList.remove('bg-white', 'form-control', 'form-control-sm');
      }
      selectedBankings--;
    }
  });
});

clearForm.addEventListener('submit', async function (e) {
  e.preventDefault();

  if (selectedBankings === 0) {
    displayAlert(alertBox, 'No transactions selected for clearing');
    return;
  }

  const formData = { table: tableData() };
  setLoadingState(saveBtn, 'Saving...');
  const data = await clearBankings(formData);
  resetLoadingState(saveBtn, 'Clear selected');
  if (data && data?.success) {
    displayAlert(
      alertBox,
      'Successfully modified selected transactions',
      'success'
    );
    clear();
  }
});

export async function getBankings(bank, start, end) {
  const res = await sendHttpRequest(
    `${HOST_URL}/clearbankings/getbankings?bank=${bank}&from=${start}&to=${end}&status=cleared`,
    'GET',
    undefined,
    {},
    alertBox
  );

  return res;
}

function appendData(data) {
  const tbody = table.getElementsByTagName('tbody')[0];
  data.forEach(dt => {
    let html = `
          <tr>
              <td class="d-none bid">${dt.id}</td>
              <td>
                  <div class="check-group">
                      <input type="checkbox" class="chkbx" id="${dt.id}">
                      <label for="${dt.id}"></label>
                  </div>  
              </td>
              <td class="txndate">${dt.transactionDate}</td>
              <td><input type="" class="table-input w-100 cleardate" value="${
                dt.clearedDate
              }" readonly></td>
              <td>${numberWithCommas(dt.amount)}</td>
              <td>${dt.reference}></td>
          </tr>
      `;
    tbody.insertAdjacentHTML('beforeend', html);
  });
}

function tableData() {
  const tableData = [];

  const trs = table.getElementsByTagName('tbody')[0].querySelectorAll('tr');

  trs.forEach(tr => {
    if (tr.querySelector('.chkbx').checked) {
      const clearDate = tr.querySelector('.cleardate').value;
      const id = tr.querySelector('.bid').innerText;
      const txnDate = tr.querySelector('.txndate').innerText;
      tableData.push({ id, txnDate, clearDate });
    }
  });

  return tableData;
}

async function clearBankings(data) {
  const res = await sendHttpRequest(
    `${HOST_URL}/clearbankings/update`,
    'POST',
    JSON.stringify(data),
    { 'Content-Type': 'application/json' },
    alertBox
  );

  return res;
}

clearOnChange(mandatoryFields);
