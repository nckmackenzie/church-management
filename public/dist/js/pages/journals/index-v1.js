//prettier-ignore
import { clearOnChange, mandatoryFields, validation,setLoadingState,resetLoadingState, 
    displayAlert, alertBox,sendHttpRequest,HOST_URL } from '../utils/utils.js';
import { getJournalNo, saveEntries } from './ajax.js';
//prettier-ignore
import { addBtn, journalNoInput, form, saveBtn, table,currJouralInput,firstJouralInput,
    searchInput,searchBtn ,deleteBtn,userTypeInput,resetBtn, tbody} from './elements.js';
//prettier-ignore
import { addToTable, validate, formData ,removeSelected,clear,getJournal} from './functionalities.js';
const validateBtn = document.querySelector('#validate');
let tableData = [];
let tableEntries = [];

validateBtn.disabled = true;

async function reset() {
  validateBtn.disabled = true;
  addBtn.disabled = false;
  clear();
  const data = await getJournalNo();
  if (data && data.success) {
    journalNoInput.value = data.journalno;
    currJouralInput.value = data.journalno;
    firstJouralInput.value = data.firstno;
  }
  resetBtn.classList.add('d-none');
  if (userTypeInput.value && +userTypeInput.value < 3) {
    deleteBtn.classList.add('d-none');
  }
  tbody.innerHTML = '';
}
//add btn click
addBtn.addEventListener('click', addToTable);

//reset
resetBtn.addEventListener('click', reset);

//form submit
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  //validations
  if (validation() > 0) return;
  if (!validate()) return;
  setLoadingState(saveBtn, 'Saving...');
  const res = await saveEntries(formData());
  resetLoadingState(saveBtn, 'Save');
  if (res && res.success) {
    displayAlert(alertBox, 'Saved successfully!', 'success');
    reset();
  }
});

//incase enter is pressed on search key
searchInput.addEventListener('keypress', function (e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    getJournal(+e.target.value);
  }
});

//search
searchBtn.addEventListener('click', function () {
  if (searchInput.value === '') return;
  getJournal(+searchInput.value);
});

//remove clicked row
table.addEventListener('click', function (e) {
  removeSelected(e);
});

if (+userTypeInput.value < 3) {
  deleteBtn.addEventListener('click', function () {
    $('#deleteModalCenter').modal('show');
    document.getElementById('id').value = currJouralInput.value;
  });
}

// import
document
  .getElementById('fileInput')
  .addEventListener('change', function (event) {
    const file = event.target.files[0];

    Papa.parse(file, {
      header: true,
      complete: function (results) {
        if (results.data.length === 0) return;
        const filteredData = results.data.filter(
          row => row.account && row.account.trim() !== ''
        );

        if (filteredData.length > 0) {
          validateBtn.disabled = false;
          tableData = filteredData;
        } else {
          validateBtn.disabled = true;
        }
      },
    });
  });

validateBtn.addEventListener('click', async function () {
  if (tableData.length === 0) return;
  const res = await validateImportedData(tableData);
  if (res && !res.success) {
    salert(
      'Validation unsuccessful. Ensure G/L accounts names are an exact match to the system name.'
    );
    return;
  }
  validateBtn.disabled = true;
  addBtn.disabled = true;
  tableEntries = res.data;

  renderTableEntries();

  updateTotals();
});

async function validateImportedData(data) {
  setLoadingState(validateBtn, 'Validating...');
  const res = await sendHttpRequest(
    `${HOST_URL}/journals/validateimport`,
    'POST',
    JSON.stringify({ data }),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  resetLoadingState(validateBtn, 'Validate');
  return res;
}

function renderTableEntries() {
  const tbody = document.querySelector('#table-entries tbody');
  tbody.innerHTML = ''; // Clear current table body

  tableEntries.forEach((entry, index) => {
    const row = document.createElement('tr');

    row.innerHTML = `
        <td class="d-none accountid">${entry.accountId}</td>
        <td class="accountname">${entry.account.toUpperCase()}</td>
        <td class="debit">${entry.debit}</td>
        <td class="credit">${entry.credit}</td>
        <td class="desc">${entry.narration || ''}</td>
        <td>
          <button class="btn btn-danger btn-sm remove-btn" data-index="${index}">Remove</button>
        </td>
      `;

    tbody.appendChild(row);
  });

  // Attach event listeners to remove buttons
  document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', removeRow);
  });
}

// Function to remove a row
function removeRow(event) {
  const index = event.target.getAttribute('data-index');

  tableEntries.splice(index, 1);

  renderTableEntries();
  updateTotals();
}

function updateTotals() {
  const totalDebit = tableEntries.reduce(
    (sum, entry) => sum + parseFloat(entry.debit || 0),
    0
  );
  const totalCredit = tableEntries.reduce(
    (sum, entry) => sum + parseFloat(entry.credit || 0),
    0
  );

  document.getElementById('debits').value = totalDebit.toFixed(2);
  document.getElementById('credits').value = totalCredit.toFixed(2);
}

reset();
clearOnChange(mandatoryFields);
