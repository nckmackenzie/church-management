//prettier-ignore
import { getSelectedText, getColumnTotal,alertBox,displayAlert } from '../utils/utils.js';
//prettier-ignore
import { table,tbody,accountSlct,typeSlct,amountInput,descInput,debitsInput,creditsInput } from './elements.js';
export function addToTable() {
  if (!validateAdd()) return;
  const accountid = +accountSlct.value;
  const accountName = getSelectedText(accountSlct);
  const type = typeSlct.value;
  const amount = amountInput.value;
  const desc = descInput.value || '';

  let html = `
     <tr>
        <td class="d-none accountid">${accountid}</td>
        <td class="accountname">${accountName}</td>
        <td class="debit">${type === 'debit' ? amount : ''}</td>
        <td class="credit">${type === 'credit' ? amount : ''}</td>
        <td class="desc">${desc}</td>
        <td><button style="outline:0; border: none; background-color:transparent;" type="button" class="text-danger btndel">Remove</button></td>
     </tr>
  `;
  let newRow = tbody.insertRow(tbody.rows.length);
  newRow.innerHTML = html;
  typeSlct.value = amountInput.value = descInput.value = '';
  $('.select2').val('').trigger('change');
  debitsInput.value = getColumnTotal(table, 2);
  creditsInput.value = getColumnTotal(table, 3);
}

function validateAdd() {
  let errorCount = 0;
  const requiredFields = document.querySelectorAll('.table-required');
  alertBox.innerHTML = '';
  requiredFields.forEach(field => {
    if (field.value === '') {
      errorCount++;
    }
  });
  if (errorCount > 0) {
    displayAlert(alertBox, 'Provide all required fields');
    return false;
  }
  return true;
}
