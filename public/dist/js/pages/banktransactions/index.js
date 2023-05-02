import {
  mandatoryFields,
  clearOnChange,
  validation,
  alertBox,
  setLoadingState,
  resetLoadingState,
  sendHttpRequest,
  HOST_URL,
  clearValues,
  displayAlert,
} from '../utils/utils.js';
const txnDate = document.querySelector('#date');
const bank = document.querySelector('#bank');
const type = document.querySelector('#type');
const transfer = document.querySelector('#transferto');
const amount = document.querySelector('#amount');
const reference = document.querySelector('#reference');
const form = document.querySelector('#txnform');
const btn = document.querySelector('.save');

type.addEventListener('change', e => {
  if (+e.target.value === 5) {
    transfer.disabled = false;
  } else {
    transfer.value = '';
    transfer.disabled = true;
  }
});

transfer.addEventListener('change', () => {
  this.classList.remove('is-invalid');
  this.nextSibling.nextSibling.textContent = '';
});

form.addEventListener('submit', async e => {
  e.preventDefault();
  if (validation() > 0) return;

  if (new Date(txnDate.value).getTime() > new Date().getTime()) {
    txnDate.classList.add('is-invalid');
    txnDate.nextSibling.nextSibling.textContent = 'Invalid date';
    return;
  }

  if (+amount.value < 0) {
    amount.classList.add('is-invalid');
    amount.nextSibling.nextSibling.textContent = 'Invalid amount';
    return;
  }

  if (+type.value === 5 && transfer.value === '') {
    transfer.classList.add('is-invalid');
    transfer.nextSibling.nextSibling.textContent = 'Select account';
    return;
  }

  setLoadingState(btn, 'Saving...');
  const data = await submitHandler();
  resetLoadingState(btn, 'Save');
  if (data.success) {
    clearValues();
    displayAlert(alertBox, 'Saved successfully', 'success');
  }
});

async function submitHandler() {
  const formdata = Object.fromEntries(new FormData(form).entries());
  const response = await sendHttpRequest(
    `${HOST_URL}/banktransactions/createupdate`,
    'POST',
    JSON.stringify(formdata),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  return response;
}

clearOnChange(mandatoryFields);
