import { validation, clearOnChange, numberFormatter } from '../utils/utils.js';

const mandatoryFields = document.querySelectorAll('.mandatory');
const amountApprovedInput = document.getElementById('approved');
const amountRequestedInput = document.getElementById('amount');
const amountAvailInput = document.getElementById('availableamount');
const balanceInput = document.getElementById('balance');
const reqDateInput = document.getElementById('date');
const paymentDateInput = document.getElementById('paydate');
const form = document.querySelector('form');

function getBalance() {
  if (amountAvailInput.value == '' || amountApprovedInput.value == '') return;
  const amountAvailable = numberFormatter(amountAvailInput.value);
  const amountApproved = +amountApprovedInput.value;

  const balance = amountAvailable - amountApproved;
  balanceInput.value = balance;
}

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

  form.submit(); //submit form
});

clearOnChange(mandatoryFields);
