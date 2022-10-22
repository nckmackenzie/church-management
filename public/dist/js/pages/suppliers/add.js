import {
  clearOnChange,
  validation,
  alertBox,
  sendHttpRequest,
  setLoadingState,
  resetLoadingState,
  displayAlert,
  clearValues,
  HOST_URL,
} from '../utils/utils.js';

const form = document.getElementById('suppliers-form');
const saveBtn = document.querySelector('button[type="submit"]');
const formControls = document.querySelectorAll('.form-control');
const isEditInput = document.querySelector('#isedit');

//form submit
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  if (validation() > 0) return;
  if (!validateAsofDate()) return;
  setLoadingState(saveBtn, 'Saving...');

  //ajax post request
  const res = await saveSupplier();
  resetLoadingState(saveBtn, 'Save');
  if (res && res.success) {
    displayAlert(alertBox, 'Saved successfully!', 'success');
    clearValues();
  }
});

//validate asof date
function validateAsofDate() {
  if (isEditInput.value || isEditInput.value !== '') return true;
  const asofInput = document.querySelector('#asof');
  const balanceInput = document.querySelector('#openingbal');
  //if balance entered
  if (balanceInput.value !== '') {
    if (!asofInput.value || asofInput.value === '') {
      asofInput.classList.add('is-invalid');
      asofInput.nextSibling.nextSibling.textContent = 'Select date';
      return false;
    }
    if (new Date(asofInput.value).getTime() > new Date().getTime()) {
      asofInput.classList.add('is-invalid');
      asofInput.nextSibling.nextSibling.textContent = 'Invalid date selected';
      return false;
    }
  }
  return true;
}

async function saveSupplier() {
  const formData = Object.fromEntries(new FormData(form).entries());
  const response = await sendHttpRequest(
    `${HOST_URL}/suppliers/createupdate`,
    'POST',
    JSON.stringify(formData),
    { 'Content-Type': 'application/json' },
    alertBox
  );

  return response;
}

clearOnChange(formControls);
