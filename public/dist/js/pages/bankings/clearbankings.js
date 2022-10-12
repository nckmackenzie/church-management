// prettier-ignore
import { clearOnChange, validation, mandatoryFields,validateDate, numberWithCommas } from '../utils/utils.js';
// prettier-ignore
import { addBtn,fromDateInput,toDateInput,bankSelect,despositsInput,withdrawalsInput,table,balanceInput } from './elements.js';
//prettier-ignore
import { getBankings } from './ajax-requests.js';
//prettier-ignore
import {setLoadingSpinner,removeLoadingSpinner,appendData,updateSubTotal, calculateVariance} from './functionalities.js'

let selectedBankings = 0;
let initialDeposits = 0;
let InitialWidthrawals = 0;
//fetch details
addBtn.addEventListener('click', async function () {
  if (validation() > 0) return;

  if (!validateDate(fromDateInput, toDateInput)) return;
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

clearOnChange(mandatoryFields);
