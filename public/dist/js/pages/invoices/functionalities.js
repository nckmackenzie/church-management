import {
  modalRequired,
  rateInput,
  qtyInput,
  grossInput,
  table,
  productSelect,
  vatTypeSelect,
  totalsInput,
  supplierSelect,
  invoiceDateInput,
  dueDateInput,
  invoiceNoInput,
  vatSelect,
} from './supplier.js';

import { getSelectedText, numberFormatter } from '../utils/utils.js';
const vatamountInput = document.getElementById('vatamount');

export function addDays(date, days) {
  const result = new Date(date);
  result.setDate(result.getDate() + days);
  return result;
}

export function formatDate(date) {
  const d = new Date(date),
    month = '' + (d.getMonth() + 1),
    day = '' + d.getDate(),
    year = d.getFullYear();

  if (month.length < 2) month = '0' + month;
  if (day.length < 2) day = '0' + day;

  return [year, month, day].join('-');
}

export function validateModal() {
  let errorCount = 0;
  modalRequired.forEach(field => {
    if (field.value === '') {
      field.classList.add('is-invalid');
      field.nextSibling.nextSibling.textContent = 'Field is required';
      errorCount++;
    }
  });

  return errorCount;
}

export function calcGrossValue() {
  if (!rateInput.value || !qtyInput.value) return;
  const gross = parseFloat(rateInput.value) * parseFloat(qtyInput.value);
  grossInput.value = gross;
}

export function addToTable() {
  const html = `
    <tr>
      <td class="d-none pid">${productSelect.value}</td>
      <td>${getSelectedText(productSelect)}</td>
      <td class="qty">${qtyInput.value}</td>
      <td class="rate">${rateInput.value}</td>
      <td class="gross">${grossInput.value}</td>
      <td class="btnremove"><button type="button" class="tablebtn text-danger btnremove">Remove</button></td>
    </tr>
  `;
  const tbody = table.getElementsByTagName('tbody')[0];
  let newRow = tbody.insertRow(tbody.rows.length);
  newRow.innerHTML = html;
}

export function calculateVat() {
  if (!totalsInput.value || !vatTypeSelect.value) return;
  if (+vatTypeSelect.value === 1) {
    vatamountInput.value = 0;
  } else if (+vatTypeSelect.value === 2) {
    const total = numberFormatter(totalsInput.value);
    const excAmount = total / 1.16;
    vatamountInput.value = (total - excAmount).toFixed(2);
  } else {
    const total = numberFormatter(totalsInput.value);
    const amountInc = total * ((100 + 16) / 100);
    vatamountInput.value = (amountInc - total).toFixed(2);
  }
}

export function header() {
  return {
    supplier: supplierSelect.value,
    invoiceDate: invoiceDateInput.value,
    dueDate: dueDateInput.value,
    vatType: vatTypeSelect.value,
    vat: vatSelect.value,
    invoiceNo: invoiceNoInput.value,
  };
}

export function tableData() {
  const tableData = [];
  const trs = table.getElementsByTagName('tbody')[0].querySelectorAll('tr');
  trs.forEach(tr => {
    const pid = tr.querySelector('pid');
    const qty = tr.querySelector('qty');
    const rate = tr.querySelector('rate');
    const gross = tr.querySelector('gross');
    tableData.push({ pid, qty, rate, gross });
  });
  return tableData;
}
