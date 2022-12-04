//prettier-ignore
import { getSelectedText, getColumnTotal,alertBox,displayAlert, numberFormatter,clearValues } from '../utils/utils.js';
//prettier-ignore
import { table,tbody,accountSlct,typeSlct,amountInput,descInput,
         debitsInput,creditsInput,dateInput, isEditInput, journalNoInput } from './elements.js';
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

export function validate() {
  if (new Date(dateInput.value).getTime() > new Date().getTime()) {
    dateInput.classList.add('is-invalid');
    dateInput.nextSibling.nextSibling.textContent = 'Invalid date selected';
    return false;
  }
  if (tbody.rows.length === 0) {
    displayAlert(alertBox, 'No entries done');
    return false;
  }
  const totalDebits = parseFloat(numberFormatter(debitsInput.value));
  const totalCredits = parseFloat(numberFormatter(creditsInput.value));
  if (totalDebits !== totalCredits) {
    displayAlert(alertBox, "Debits and Credits total doesn't match");
    return false;
  }
  return true;
}

export function formData() {
  const tableData = [];
  const trs = tbody.querySelectorAll('tr');
  trs.forEach(tr => {
    const accountid = tr.querySelector('.accountid').innerText;
    const accountname = tr.querySelector('.accountname').innerText;
    const debit = tr.querySelector('.debit').innerText;
    const credit = tr.querySelector('.credit').innerText;
    const desc = tr.querySelector('.desc').innerText;
    tableData.push({ accountid, accountname, debit, credit, desc });
  });

  return {
    date: dateInput.value || new Date(),
    isEdit: isEditInput.value || false,
    journalNo: journalNoInput.value,
    entries: tableData,
  };
}

export function removeSelected(e) {
  if (!e.target.classList.contains('btndel')) return;
  const btn = e.target;
  btn.closest('tr').remove();
  debitsInput.value = getColumnTotal(table, 2);
  creditsInput.value = getColumnTotal(table, 3);
}

export function clear() {
  clearValues();
  dateInput.value = setTodaysDate();
  tbody.innerHTML = '';
}

export function setTodaysDate() {
  const now = new Date();
  const day = ('0' + now.getDate()).slice(-2);
  const month = ('0' + (now.getMonth() + 1)).slice(-2);
  const today = now.getFullYear() + '-' + month + '-' + day;
  return today;
}
