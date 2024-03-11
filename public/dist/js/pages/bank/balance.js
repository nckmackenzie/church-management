import { clearOnChange, mandatoryFields, validation } from '../utils/utils.js';

const form = document.getElementById('openingbalance');

form.addEventListener('submit', function (e) {
  e.preventDefault();
  if (validation() > 0) return;

  form.submit();
});

clearOnChange(mandatoryFields);
