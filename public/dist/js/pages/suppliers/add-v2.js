import {
  alertBox,
  sendHttpRequest,
  setLoadingState,
  resetLoadingState,
  displayAlert,
  HOST_URL,
} from '../utils/utils.js';
import {
  clearOnChange,
  validateRequired,
  setError,
  clearError,
} from '../utils/feedback.js';

const form = document.getElementById('suppliers-form');
const saveBtn = document.querySelector('button[type="submit"]');
const formControls = document.querySelectorAll('.form-control');
const isEditInput = document.querySelector('#isedit');
const balanceInput = document.querySelector('#openingbal');
const asofInput = document.querySelector('#asof');
const payableSelect = document.querySelector('#payable');

//form submit
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  if (validateRequired() > 0) return;
  if (!validateBalance()) return;
  setLoadingState(saveBtn, 'Saving...');

  //ajax post request
  const res = await saveSupplier();
  resetLoadingState(saveBtn, 'Save');
  if (res && res.success) {
    if (isEditInput.value) {
      window.location.replace(`${HOST_URL}/suppliers`);
      return;
    }
    displayAlert(alertBox, 'Saved successfully!', 'success');
    form.reset();
    payableSelect.selectedIndex = payableSelect.dataset.initial || 0;
  }
});

// a non-zero opening balance needs a date (not in the future) and the crediting account
function validateBalance() {
  [balanceInput, asofInput, payableSelect].forEach(clearError);
  const balance = parseFloat(balanceInput.value);
  if (balanceInput.value === '' || !balance) return true;
  let ok = true;
  if (!asofInput.value) {
    setError(asofInput, 'Select date');
    ok = false;
  } else if (new Date(asofInput.value).getTime() > new Date().getTime()) {
    setError(asofInput, 'Invalid date selected');
    ok = false;
  }
  if (!payableSelect.value) {
    setError(payableSelect, 'Select the crediting account');
    ok = false;
  }
  return ok;
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

payableSelect.dataset.initial = payableSelect.selectedIndex;
clearOnChange(formControls);
