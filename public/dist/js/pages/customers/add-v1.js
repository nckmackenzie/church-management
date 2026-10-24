import {
  alertBox,
  sendHttpRequest,
  setLoadingState,
  resetLoadingState,
  displayAlert,
  HOST_URL,
} from '../utils/utils.js';
import { clearOnChange, validateRequired, setError, clearError } from '../utils/feedback.js';

const form = document.getElementById('customers-form');
const saveBtn = form.querySelector('button[type="submit"]');
const controls = form.querySelectorAll('.form-control');
const email = document.getElementById('email');

form.addEventListener('submit', async event => {
  event.preventDefault();
  if (validateRequired('#customers-form .mandatory') > 0 || !validateEmail()) return;
  setLoadingState(saveBtn, 'Saving...');
  const response = await sendHttpRequest(
    form.dataset.url,
    'POST',
    JSON.stringify(Object.fromEntries(new FormData(form).entries())),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  resetLoadingState(saveBtn, 'Save');
  if (!response?.success) return;
  if (+document.getElementById('id').value > 0) {
    window.location.replace(`${HOST_URL}/customers`);
    return;
  }
  displayAlert(alertBox, 'Saved successfully!', 'success');
  form.reset();
});

function validateEmail() {
  clearError(email);
  if (email.value && !email.validity.valid) {
    setError(email, 'Enter a valid email');
    return false;
  }
  return true;
}

clearOnChange(controls);
