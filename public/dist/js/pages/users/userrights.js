import {
  sendHttpRequest,
  HOST_URL,
  mandatoryFields,
  clearOnChange,
  validation,
  setLoadingState,
  resetLoadingState,
  displayAlert,
} from '../utils/utils.js';
const spinnerContainer = document.querySelector('.spinner-container');
const tableArea = document.querySelector('#table-area');
const alertBox = document.querySelector('#alertBox');
const table = document.querySelector('#rights-table');
const form = document.querySelector('#rights-form');
const btnSave = document.querySelector('.btnsave');
let user;

$(function () {
  $('.select2').select2();
  var table = $('#rights-table').DataTable();
  $('.select2').on('select2:select', async function (e) {
    user = +e.target.value;
    setLoadingSpinner();
    const data = await sendHttpRequest(
      `${HOST_URL}/users/loadrights?userid=${user}`,
      'GET',
      undefined,
      {},
      alertBox
    );

    removeLoadingSpinner();
    if (data && data.length > 0) {
      table.destroy();
      setTable(data);
      table = $('#rights-table').DataTable({
        ordering: false,
        pageLength: 50,
        bLengthChange: false,
        bPaginate: false,
      });
    }
  });
});

function setLoadingSpinner() {
  tableArea.classList.add('d-none');
  let html = `<div class="spinner md"></div> `;
  spinnerContainer.innerHTML = html;
}

function removeLoadingSpinner() {
  tableArea.classList.remove('d-none');
  table.getElementsByTagName('tbody')[0].innerHTML = '';
  spinnerContainer.innerHTML = '';
}

function setTable(forms) {
  const tbody = table.getElementsByTagName('tbody')[0];
  forms.forEach(form => {
    let html = `
        <tr>
            <td class="d-none formid">${form.ID}</td>
            <td>
                <div class="check-group">
                    <input type="checkbox" class="chkbx" id="${form.ID}" ${
      +form.access === 1 && 'checked'
    }>
                    <label for="${form.ID}"></label>
                </div> 
            </td>
            <td>${form.FormName}</td>
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', html);
  });
}

function getTableData() {
  const tableData = [];
  const trs = table.getElementsByTagName('tbody')[0].querySelectorAll('tr');
  trs.forEach(tr => {
    const formId = tr.querySelector('.formid').textContent;
    const access = tr.querySelector('.chkbx').checked;
    tableData.push({ formId, access });
  });
  return tableData;
}

function reset() {
  $('.select2').val('val').trigger('change');
  tableArea.classList.add('d-none');
  table.getElementsByTagName('tbody')[0].innerHTML = '';
}

//submit form handler
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  if (validation() > 0) return;
  setLoadingState(btnSave, 'Saving...');
  const response = await sendHttpRequest(
    `${HOST_URL}/users/assignrights`,
    'POST',
    JSON.stringify({ user: user, tableData: getTableData() }),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  resetLoadingState(btnSave, 'Save');
  if (response.success) {
    displayAlert(alertBox, 'Saved Successfully', 'success');
    reset();
  }
});

clearOnChange(mandatoryFields);
