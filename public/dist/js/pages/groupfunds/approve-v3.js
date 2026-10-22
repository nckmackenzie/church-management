import { validation, clearOnChange, numberFormatter } from '../utils/utils.js';

const mandatoryFields = document.querySelectorAll('.mandatory');
const amountApprovedInput = document.getElementById('approved');
const amountRequestedInput = document.getElementById('amount');
const amountAvailInput = document.getElementById('availableamount');
const balanceInput = document.getElementById('balance');
const reqDateInput = document.getElementById('date');
const deductInput = document.getElementById('dontdeduct');
const paymentDateInput = document.getElementById('paydate');
const paymethodSelect = document.getElementById('paymethod');
const bankSelect = document.getElementById('bank');
const form = document.querySelector('form');
const submitButton = form.querySelector('button[type="submit"]');
let submitting = false;

function getBalance() {
  if (parseInt(deductInput.value) === 1) {
    balanceInput.value = amountAvailInput.value;
    return;
  }
  if (amountAvailInput.value == '' || amountApprovedInput.value == '') return;
  const amountAvailable = numberFormatter(amountAvailInput.value);
  const amountApproved = +amountApprovedInput.value;

  const balance = amountAvailable - amountApproved;
  balanceInput.value = balance;
}

paymethodSelect.addEventListener('change', function (e) {
  if (+e.target.value < 3) {
    bankSelect.classList.remove('mandatory');
    bankSelect.disabled = true;
  } else {
    bankSelect.classList.add('mandatory');
    bankSelect.disabled = false;
  }
});

//get balance
amountApprovedInput.addEventListener('change', getBalance);

//form submission
form.addEventListener('submit', function (e) {
  e.preventDefault();
  if (validation() > 0) return;

  //validate amount
  if (
    parseFloat(amountApprovedInput.value) >
    parseFloat(numberFormatter(amountRequestedInput.value))
  ) {
    amountApprovedInput.classList.add('is-invalid');
    amountApprovedInput.nextSibling.nextSibling.textContent =
      'Approved more than requested amount';
    return;
  }

  if (submitting) return; //already submitted: a second click must not post again
  submitting = true;
  submitButton.disabled = true;
  form.submit(); //submit form
});

clearOnChange(mandatoryFields);
