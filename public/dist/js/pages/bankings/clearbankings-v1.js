// prettier-ignore
import { clearOnChange, validation, mandatoryFields,validateDate, numberWithCommas,
    displayAlert,alertBox,setLoadingState,resetLoadingState } from '../utils/utils.js';
// prettier-ignore
import {clearForm, addBtn,fromDateInput,toDateInput,bankSelect,despositsInput,
    withdrawalsInput,table,balanceInput,saveBtn } from './elements.js';
//prettier-ignore
import { clearBankings, getBankings } from './ajax-requests.js';
//prettier-ignore
import {setLoadingSpinner,removeLoadingSpinner,appendData,updateSubTotal, calculateVariance,
    tableData,
    clear} from './functionalities.js'

let selectedBankings = 0;
let initialDeposits = 0;
let InitialWidthrawals = 0;
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
    const { values, bankings } = data;
    initialDeposits = values.debits;
    InitialWidthrawals = values.credits;
    despositsInput.value = numberWithCommas(values.debits);
    withdrawalsInput.value = numberWithCommas(values.credits);
    appendData(bankings);
    calculateVariance();
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
        input.value = '';
        input.setAttribute('type', 'text');
        input.classList.remove('bg-white', 'form-control', 'form-control-sm');
      }
      selectedBankings--;
    }
  });
  updateSubTotal(this, initialDeposits, InitialWidthrawals);
  calculateVariance();
});

balanceInput.addEventListener('blur', calculateVariance);

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
      'Successfully cleared all selected transactions',
      'success'
    );
    clear();
  }
});

clearOnChange(mandatoryFields);
