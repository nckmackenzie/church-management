import { numberWithCommas, numberFormatter } from '../utils/utils.js';
import {
  tableArea,
  spinnerContainer,
  table,
  withdrawalsInput,
  despositsInput,
  balanceInput,
  varianceInput,
  bankSelect,
} from './elements.js';
export function setLoadingSpinner() {
  tableArea.classList.add('d-none');
  let html = `<div class="spinner md"></div> `;
  spinnerContainer.innerHTML = html;
}

export function removeLoadingSpinner() {
  tableArea.classList.remove('d-none');
  spinnerContainer.innerHTML = '';
}

export function appendData(data) {
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
            <td><input type="date" class="table-input w-100 tdate" value="${
              dt.transactionDate
            }" readonly></td>
            <td><input type="" class="table-input w-100 cleardate" readonly></td>
            <td><input type="text" class="table-input w-100 amount" value="${numberWithCommas(
              dt.amount
            )}" readonly></td>
            <td><input type="text" class="table-input w-100 reference" value="${
              dt.reference
            }" readonly></td>
            <td><button class="tablebtn text-danger">Remove</button></td>
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', html);
  });
}

//get totals
export function updateSubTotal(table, initialDeposits, InitialWidthrawals) {
  let depositsTotal = 0;
  let withdrawalTotal = 0;
  for (var i = 1; i < table.rows.length; i++) {
    if (table.rows[i].cells[1].children[0].children[0].checked) {
      if (
        parseFloat(numberFormatter(table.rows[i].cells[4].children[0].value)) >
        0
      ) {
        const rowValue =
          parseFloat(
            numberFormatter(table.rows[i].cells[4].children[0].value)
          ) || 0;
        depositsTotal = depositsTotal + rowValue;
      } else if (
        parseFloat(numberFormatter(table.rows[i].cells[4].children[0].value)) <
        0
      ) {
        const rowValue =
          parseFloat(
            numberFormatter(table.rows[i].cells[4].children[0].value)
          ) || 0;
        withdrawalTotal = withdrawalTotal + rowValue;
      }
    }
  }

  const totalDeposits = depositsTotal + initialDeposits;
  const totalWithdrawals = withdrawalTotal * -1 + InitialWidthrawals;
  despositsInput.value = numberWithCommas(totalDeposits.toFixed(2));
  withdrawalsInput.value = numberWithCommas(totalWithdrawals.toFixed(2));
}

export function calculateVariance() {
  let balance = balanceInput.value || 0;
  let withdrawals = numberFormatter(withdrawalsInput.value) || 0;
  let deposits = numberFormatter(despositsInput.value) || 0;
  const runningVariance = balance - (deposits - withdrawals);
  varianceInput.value = numberWithCommas(runningVariance.toFixed(2));
}

export function tableData() {
  const tableData = [];

  const trs = table.getElementsByTagName('tbody')[0].querySelectorAll('tr');

  trs.forEach(tr => {
    if (tr.querySelector('.chkbx').checked) {
      const txnDate = tr.querySelector('.tdate').value;
      const clearDate = tr.querySelector('.cleardate').value;
      const id = tr.querySelector('.bid').innerText;
      tableData.push({ id, txnDate, clearDate });
    }
  });

  return tableData;
}

export function clear() {
  table.getElementsByTagName('tbody')[0].innerHTML = '';
  tableArea.classList.add('d-none');
  bankSelect.value = '';
  document
    .querySelector('.card-body')
    .querySelectorAll('input')
    .forEach(input => {
      input.value = '';
    });
}
