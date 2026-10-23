import { clearError, setError, clearOnChange } from '../utils/feedback.js';

const form = document.getElementById('payForm');
const method = document.getElementById('paymethod');
const cash = document.getElementById('cashaccount');
const bank = document.getElementById('bank');
const reference = document.getElementById('reference');

// cash pays into a cash account, every other method into a bank (the server validates the same lists); a reference is
// needed for everything but cash
function methodState() {
  const isCash = +method.value === 1;
  cash.disabled = !isCash;
  bank.disabled = isCash;
  cash.classList.toggle('mandatory', isCash);
  bank.classList.toggle('mandatory', !isCash);
  reference.classList.toggle('mandatory', !isCash);
  if (isCash) {
    bank.value = '';
    clearError(bank);
  } else {
    cash.value = '';
    clearError(cash);
  }
}
method.addEventListener('change', methodState);
methodState();

form.addEventListener('submit', e => {
  let errors = 0;
  document.querySelectorAll('.mandatory').forEach(field => {
    clearError(field);
    if (!field.value) {
      setError(field, 'Field is required');
      errors++;
    }
  });
  if (errors) e.preventDefault();
});

clearOnChange(document.querySelectorAll('.form-control'));
