import {
  updateSubTotal,
  displayAlert,
  alertBox,
  setLoadingState,
  resetLoadingState,
  sendHttpRequest,
  numberFormatter,
  getSelectedText,
  HOST_URL,
} from '../utils/utils.js';
import {
  setError,
  clearError,
  validateRequired,
  clearOnChange,
  escapeHtml,
} from '../utils/feedback.js';

const $id = id => document.getElementById(id);
const customerSelect = $id('customer');
const invoiceDateInput = $id('idate');
const dueDateInput = $id('duedate');
const vatTypeSelect = $id('vattype');
const vatSelect = $id('vat');
const receivableSelect = $id('receivable');
const productSelect = $id('product');
const lineAccountText = $id('lineaccount');
const qtyInput = $id('qty');
const rateInput = $id('rate');
const grossInput = $id('gross');
const descInput = $id('description');
const totalsInput = $id('totals');
const vatamountInput = $id('vatamount');
const table = $id('details');
const form = $id('invoiceForm');
const saveBtn = $id('save');
const addBtn = $id('add');
const modalForm = $id('productForm');
const modalBtn = $id('newproduct');
const modalAlert = document.querySelector('.modalAlert');
const addControls = document.querySelectorAll('.addcontrol');
const GROSS_CELL = 6;

const get = (url, box = alertBox) => sendHttpRequest(`${HOST_URL}/${url}`, 'GET', undefined, {}, box);
const post = (url, body, box = alertBox) =>
  sendHttpRequest(`${HOST_URL}/${url}`, 'POST', JSON.stringify(body), { 'Content-Type': 'application/json' }, box);

function showLineAccount(account) {
  lineAccountText.dataset.account = account || '';
  lineAccountText.textContent = account ? `Credit account: ${account}` : '';
}

function calcGross() {
  if (!rateInput.value || !qtyInput.value) return;
  const gross = parseFloat(rateInput.value) * parseFloat(qtyInput.value);
  grossInput.value = (Math.round(gross * 100) / 100).toFixed(2);
}

// display only: the server recomputes lines, VAT, total and the invoice number (the VAT rate has always been 16%)
function calculateVat() {
  if (!totalsInput.value || !vatTypeSelect.value) return;
  const total = numberFormatter(totalsInput.value);
  if (+vatTypeSelect.value === 1) vatamountInput.value = 0;
  else if (+vatTypeSelect.value === 2) vatamountInput.value = (total - total / 1.16).toFixed(2);
  else vatamountInput.value = (total * 1.16 - total).toFixed(2);
}

function totals() {
  updateSubTotal(table, GROSS_CELL, 'input', totalsInput);
  calculateVat();
}

function header() {
  return {
    customer: customerSelect.value,
    invoiceDate: invoiceDateInput.value,
    dueDate: dueDateInput.value,
    vatType: vatTypeSelect.value,
    vat: vatSelect.value,
    receivable: receivableSelect.value,
    id: $id('id').value,
  };
}

function tableData() {
  return [...table.querySelectorAll('tbody tr')].map(tr => ({
    pid: tr.querySelector('.pid').innerText,
    desc: tr.querySelector('.desc').innerText,
    qty: tr.querySelector('.qty').innerText,
    rate: tr.querySelector('.rate').innerText,
  }));
}

customerSelect.addEventListener('change', async e => {
  if (!e.target.value) return;
  const details = await get(`invoices/fetchcustomerdetails?cid=${+e.target.value}`);
  if (!details) return;
  $id('pin').value = details.pin;
  $id('email').value = details.email;
});

invoiceDateInput.addEventListener('change', e => {
  if (!e.target.value) return;
  const due = new Date(e.target.value);
  due.setDate(due.getDate() + 30);
  dueDateInput.value = due.toISOString().slice(0, 10);
  clearError(dueDateInput);
});

vatTypeSelect.addEventListener('change', e => {
  if (+e.target.value > 1) {
    vatSelect.disabled = false;
    vatSelect.selectedIndex = 0;
  } else {
    vatSelect.disabled = true;
    vatSelect.value = '';
  }
  calculateVat();
});

productSelect.addEventListener('change', async function (e) {
  if (!e.target.value) return;
  if (+e.target.value === 0) {
    $('#addModal').modal('show');
    this.value = '';
    showLineAccount('');
    return;
  }
  const product = await get(`invoices/getproduct?pid=${+e.target.value}`);
  if (!product) {
    this.value = '';
    showLineAccount('');
    return;
  }
  rateInput.value = product.rate;
  showLineAccount(product.account);
  clearError(rateInput);
  calcGross();
});

modalForm.addEventListener('submit', async function (e) {
  e.preventDefault();
  let errors = 0;
  document.querySelectorAll('.modalrequired').forEach(field => {
    clearError(field);
    if (!field.value) {
      setError(field, 'Field is required');
      errors++;
    }
  });
  if (errors) return;
  modalBtn.disabled = true;
  modalBtn.textContent = 'Saving...';
  const data = await post(
    'invoices/newproduct',
    { name: $id('name').value, desc: $id('desc').value, sp: $id('sellingprice').value, account: $id('account').value },
    modalAlert
  );
  modalBtn.disabled = false;
  modalBtn.textContent = 'Save';
  if (data) {
    ['name', 'desc', 'sellingprice', 'account'].forEach(id => ($id(id).value = ''));
    $('#addModal').modal('toggle');
    productSelect.innerHTML = data.products;
    productSelect.value = data.productid;
    productSelect.dispatchEvent(new Event('change'));
  }
});

qtyInput.addEventListener('blur', calcGross);
rateInput.addEventListener('blur', calcGross);

addBtn.addEventListener('click', () => {
  let error = 0;
  addControls.forEach(control => {
    clearError(control);
    if (control.value === '') {
      setError(control, 'Field is required');
      error++;
    }
  });
  if (error) return;
  calcGross();
  const row = table.getElementsByTagName('tbody')[0].insertRow();
  row.innerHTML = `
    <td class="d-none pid">${escapeHtml(productSelect.value)}</td>
    <td>${escapeHtml(getSelectedText(productSelect))}</td>
    <td class="account">${escapeHtml(lineAccountText.dataset.account || '')}</td>
    <td class="desc">${escapeHtml(descInput.value)}</td>
    <td class="qty">${escapeHtml(qtyInput.value)}</td>
    <td class="rate">${escapeHtml(rateInput.value)}</td>
    <td class="gross">${escapeHtml(grossInput.value)}</td>
    <td class="btnremove"><button type="button" class="tablebtn text-danger btnremove">Remove</button></td>`;
  totals();
  productSelect.value = qtyInput.value = rateInput.value = grossInput.value = descInput.value = '';
  showLineAccount('');
});

table.addEventListener('click', e => {
  if (!e.target.classList.contains('text-danger')) return;
  e.target.closest('tr').remove();
  totals();
});

form.addEventListener('submit', async e => {
  e.preventDefault();
  if (form.dataset.norec) {
    displayAlert(alertBox, 'No receivables account is set up. Ask the accountant to create one');
    return;
  }
  if (validateRequired() > 0) return;
  if (table.getElementsByTagName('tbody')[0].rows.length === 0) {
    displayAlert(alertBox, 'Enter products');
    return;
  }
  setLoadingState(saveBtn, 'Saving...');
  const isEdit = $id('isedit').value;
  const res = await post(isEdit ? 'invoices/update' : 'invoices/create', { header: header(), table: tableData() });
  if (res && res.success) {
    window.location.replace(`${HOST_URL}/invoices`);
    return;
  }
  resetLoadingState(saveBtn, 'Save');
});

clearOnChange(document.querySelectorAll('.mandatory'));
clearOnChange(addControls);
if (+vatTypeSelect.value <= 1) vatSelect.value = '';

// an invoice that has payments: only the due date may change (the server enforces it)
if (form.dataset.locked) {
  [customerSelect, invoiceDateInput, vatTypeSelect, vatSelect, receivableSelect, productSelect, qtyInput, rateInput, addBtn].forEach(
    field => (field.disabled = true)
  );
  table.querySelectorAll('.btnremove').forEach(btn => (btn.style.display = 'none'));
}

if (table.getElementsByTagName('tbody')[0].rows.length > 0) totals();
