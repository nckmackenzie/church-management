import { clearErrors } from '../reports/utils.js';
import { createPledge, getPledgers } from './ajax.js';
import {
  clearOnChange,
  displayAlert,
  mandatoryFields,
  clearValues,
  validation,
  setLoadingState,
  resetLoadingState,
  alertBox,
  getSelectedText,
} from '../utils/utils.js';

const paymethodSelect = document.getElementById('paymethod');
const categorySelect = document.getElementById('category');
const bankSelect = document.getElementById('bank');
const pledgerSelect = document.getElementById('pledger');
const dateInput = document.getElementById('date');
const amountPledgedInput = document.getElementById('amountpledged');
const paidInput = document.getElementById('amountpaid');
const referenceInput = document.getElementById('reference');
const form = document.getElementById('pledge-form');
const saveBtn = document.querySelector('.save');

categorySelect.addEventListener('change', async function (e) {
  if (e.target.value === '') return;
  pledgerSelect.innerHTML = '';
  pledgerSelect.innerHTML = await getPledgers(+e.target.value);
});

paidInput.addEventListener('change', function (e) {
  if (e.target.value === '' || +e.target.value === 0) {
    referenceInput.disabled = true;
    bankSelect.disabled = true;
    paymethodSelect.disabled = true;
    paymethodSelect.classList.remove('mandatory');
    bankSelect.classList.remove('mandatory');
    referenceInput.classList.remove('mandatory');
  } else if (e.target.value !== '' && +e.target.value > 0) {
    referenceInput.disabled = false;
    bankSelect.disabled = false;
    paymethodSelect.disabled = false;
    paymethodSelect.classList.add('mandatory');
    bankSelect.classList.add('mandatory');
    referenceInput.classList.add('mandatory');
  }
});

form.addEventListener('submit', async function (e) {
  e.preventDefault();

  const manFields = document.querySelectorAll('.mandatory');
  clearErrors(manFields);
  if (validation() > 0) return;
  if (!validate()) return;
  setLoadingState(saveBtn, 'Saving...');
  const res = await createPledge(getFormData());
  resetLoadingState(saveBtn, 'Save');
  if (res && res.success) {
    displayAlert(alertBox, 'Pledge created successfully', 'success');
    reset();
  }
});

function reset() {
  const now = new Date();
  const day = ('0' + now.getDate()).slice(-2);
  const month = ('0' + (now.getMonth() + 1)).slice(-2);
  const today = now.getFullYear() + '-' + month + '-' + day;
  clearValues();
  dateInput.value = today;
  bankSelect.disabled = true;
  paymethodSelect.disabled = true;
  referenceInput.disabled = true;
  $('#pledger').val('').trigger('change');
}

function validate() {
  if (new Date(dateInput.value).getTime() > new Date().getTime()) {
    dateInput.classList.add('is-invalid');
    dateInput.nextSibling.nextSibling.textContent = 'Invalid date selected';
    return false;
  }
  if (parseFloat(amountPledgedInput.value) < parseFloat(paidInput.value)) {
    paidInput.classList.add('is-invalid');
    paidInput.nextSibling.nextSibling.textContent = 'Paid more than pledged';
    return false;
  }
  return true;
}

function getFormData() {
  return {
    category: categorySelect.value || '',
    pledger: pledgerSelect.value || '',
    date: dateInput.value || '',
    pledged: amountPledgedInput.value || '',
    paid: paidInput.value || '',
    paymethod: paymethodSelect.value || '',
    bank: bankSelect.value || '',
    reference: referenceInput.value || '',
    pledgername: getSelectedText(pledgerSelect),
  };
}

reset();
clearOnChange(mandatoryFields);
