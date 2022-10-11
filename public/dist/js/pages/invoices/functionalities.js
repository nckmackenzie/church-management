import {
  modalRequired,
  rateInput,
  qtyInput,
  grossInput,
  table,
  productSelect,
} from './supplier.js';

import { getSelectedText } from '../utils/utils.js';

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
