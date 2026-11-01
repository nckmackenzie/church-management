import { alertBox, sendHttpRequest, HOST_URL } from './utils/utils.js';
const addButton = document.querySelector('.btnadd');
const accountSelect = document.getElementById('account');
const receiptInput = document.getElementById('receipt');
const dateInput = document.getElementById('date');
const amountInput = document.getElementById('amount');
const rowReferenceInput = document.getElementById('rowreference');
const MAX_REFERENCE = 50;
// same wording as the server (Contribution::prepare)
function referenceError(value, line) {
  const v = String(value).trim();
  if (v === '') return `Line ${line}: enter the reference`;
  if (v.length > MAX_REFERENCE)
    return `Line ${line}: reference cannot be longer than ${MAX_REFERENCE} characters`;
  return '';
}
function escapeAttr(v) {
  return String(v)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
const categorySelect = document.getElementById('category');
const paymethodSelect = document.getElementById('paymethod');
const contributorSelect = document.getElementById('contributor');
const table = document.getElementById('contributions-table');
const body = table.getElementsByTagName('tbody')[0];
const form = document.querySelector('form');

function getSelectedText(sel) {
  return sel.options[sel.selectedIndex].text;
}


//add to table
addButton.addEventListener('click', () => {
  const account = Number(accountSelect.value);
  const accountName = $('#account').find('option:selected').text().trim();
  const amount = Number(amountInput.value);
  const category = Number(categorySelect.value);
  const categoryName = getSelectedText(categorySelect);
  const contributor = Number(contributorSelect.value);
  const contributorName = $('#contributor')
    .find('option:selected')
    .text()
    .trim()
    .toUpperCase();

  if (!amount || !account || !category || !contributor) {
    alert('Select all required fields');
    return;
  }
  // new rows start empty (no copy of the row above); the reference is required for every row
  const reference = rowReferenceInput.value.trim();
  const refMessage = referenceError(reference, body.rows.length + 1);
  if (refMessage) {
    alert(refMessage);
    rowReferenceInput.focus();
    return;
  }
  let html = `
      <tr>
        <td class="d-none"><input type="text" name="accountsid[]" value="${account}"></td>
        <td><input type="text" class="table-input" name="accountsname[]" value="${escapeAttr(accountName)}" readonly></td>
        <td><input type="text" class="table-input" name="amounts[]" value="${amount}" readonly></td>
        <td class="d-none"><input type="text" class="table-input" name="categoriesid[]" value="${category}"></td>
        <td><input type="text" class="table-input" name="categoriesname[]" value="${escapeAttr(categoryName)}" readonly></td>
        <td class="d-none"><input type="text" class="table-input" name="contributorsid[]" value="${contributor}"></td>
        <td><input type="text" class="table-input" name="contributorsname[]" value="${escapeAttr(contributorName)}" readonly></td>
        <td class="d-none"><input type="text" class="table-input" name="subaccount[]" value="" readonly></td>
        <td><input type="text" class="table-input" name="references[]" maxlength="${MAX_REFERENCE}" value="${escapeAttr(reference)}"></td>
        <td><button type="button" class="action-icon btn btn-sm text-danger fs-5 btndel">Remove</button></td>
      </tr>
  `;
  let newRow = body.insertRow(body.rows.length);
  newRow.innerHTML = html;
  amountInput.value = '';
  rowReferenceInput.value = '';
  $('#contributor').val('1'); // Select the option with a value of '1'
  $('#contributor').trigger('change');
});

table.addEventListener('click', function (e) {
  if (!e.target.classList.contains('btndel')) return;
  const btn = e.target;
  btn.closest('tr').remove();
});

dateInput.addEventListener('change', async function (e) {
  const date = e.target.value;

  // on edit the receipt keeps its number unless the date moves it to another fiscal year
  const id = form.querySelector('input[name="id"]').value;
  const receiptNo = await sendHttpRequest(
    `${HOST_URL}/contributions/receiptno?txndate=${date}&id=${encodeURIComponent(id)}`,
    'GET',
    undefined,
    {},
    alertBox
  );

  receiptInput.value = receiptNo;
});

form.addEventListener('submit', e => {
  e.preventDefault();
  if (Number(body.rows.length) === 0) {
    alert('No contributions added');
    return false;
  }
  const refInputs = body.querySelectorAll('input[name="references[]"]');
  for (let i = 0; i < refInputs.length; i++) {
    const m = referenceError(refInputs[i].value, i + 1);
    if (m) {
      alert(m);
      refInputs[i].focus();
      return false;
    }
  }
  document.form.submit();
});
