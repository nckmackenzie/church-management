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
const paidFromSelect = document.getElementById('paidfrom');
const receivingSelect = document.getElementById('receiving');
const CASH_METHOD = 1; // tblpaymentmethods: 1 = cash (paid from a cash account); 2, 3, 4 = paid from a bank account
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

// the paid-from list follows the pay method: cash accounts for cash, bank accounts otherwise (the server checks it again)
function fillPaidFrom(keepSelected) {
  const accounts = JSON.parse(
    paidFromSelect.dataset[+paymethodSelect.value === CASH_METHOD ? 'cash' : 'bank']
  );
  // first load: the posted / default account; later switches: cash goes back to the default (98 petty cash), a bank list starts empty
  const isCash = +paymethodSelect.value === CASH_METHOD;
  const selected = keepSelected
    ? paidFromSelect.value || paidFromSelect.dataset.selected
    : isCash
      ? paidFromSelect.dataset.selected
      : '';
  paidFromSelect.innerHTML = '<option value="" disabled selected>Select account...</option>';
  accounts.forEach(a => {
    // the receiving and the paid-from account cannot be the same (the server refuses it too)
    if (receivingSelect && receivingSelect.value && String(a.id) === String(receivingSelect.value)) return;
    const opt = document.createElement('option');
    opt.value = a.id;
    opt.textContent = a.label;
    if (String(a.id) === String(selected)) opt.selected = true;
    paidFromSelect.appendChild(opt);
  });
  if (!paidFromSelect.value) paidFromSelect.selectedIndex = 0;
}

paymethodSelect.addEventListener('change', function () {
  fillPaidFrom(false);
});
receivingSelect?.addEventListener('change', function () {
  fillPaidFrom(true);
});
fillPaidFrom(true);

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
