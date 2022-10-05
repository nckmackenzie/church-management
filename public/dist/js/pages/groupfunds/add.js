import {
  validation,
  clearOnChange,
  HOST_URL,
  sendHttpRequest,
} from '../utils/utils.js';
const form = document.querySelector('form');
const groupSelect = document.getElementById('group');
const mandatoryField = document.querySelectorAll('.mandatory');
const date = document.getElementById('date');
const amountAvailable = document.getElementById('availableamount');

//form submit
form.addEventListener('submit', function (e) {
  e.preventDefault();

  if (validation() > 0) return;
  otherValidation();

  form.submit();
});

//validate fields
function otherValidation() {
  const amount = document.getElementById('amount');

  if (new Date(date.value).getTime() > new Date().getTime()) {
    date.classList.add('is-invalid');
    date.nextSibling.nextSibling = 'Invalid date selected';
    return;
  }

  if (parseFloat(amount.value) > parseFloat(amountAvailable.value)) {
    amount.classList.add('is-invalid');
    amount.nextSibling.nextSibling.textContent =
      'Requesting more than available';
    return;
  }
}

//clear error state on change
clearOnChange(mandatoryField);

//fetch amount available
groupSelect.addEventListener('change', getvalue);
date.addEventListener('change', getvalue);

async function getvalue() {
  if (date.value == '' || groupSelect.value == '') return;
  const data = await sendHttpRequest(
    `${HOST_URL}/groupfunds/getamountavailable?group=${groupSelect.value}&date=${date.value}`
  );
  amountAvailable.value = data;
}
