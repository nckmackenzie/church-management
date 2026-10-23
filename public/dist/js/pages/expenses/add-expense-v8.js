import { sendHttpRequest, HOST_URL } from '../utils/utils.js';
const form = document.getElementById('expenseform');
const accountSelect = document.getElementById('account');
const expenseDateInput = document.getElementById('date');
const voucherNoInput = document.getElementById('voucher');
const expenseTypeSelect = document.getElementById('expensetype');
const costCenterSelect = document.getElementById('costcentre');
const paymethodSelect = document.getElementById('paymethod');
const paidFromSelect = document.getElementById('paidfrom');
const requisitionSelect = document.getElementById('reqid');
const expenseRoute = `${HOST_URL}/expenses`;

const isEdit = form.dataset.mode === 'edit';
const expenseId = form.dataset.expenseId || '';
const CASH_METHOD = '1';
// the account a new-style requisition is paid from (set by the server)
const CASH_HOLDING = form.dataset.cashHolding || '';

function esc(text) {
  const d = document.createElement('div');
  d.textContent = text;
  return d.innerHTML;
}

function fillSelect(select, items, placeholder, selected) {
  select.innerHTML = placeholder
    ? `<option value="" selected disabled>${esc(placeholder)}</option>`
    : '';
  items.forEach(item => {
    const opt = new Option(item.label, item.id);
    if (item.style) opt.dataset.style = item.style;
    select.add(opt, undefined);
  });
  if (selected !== undefined && selected !== null && selected !== '') {
    select.value = String(selected);
    if (select.value !== String(selected)) select.selectedIndex = 0;
  }
}

// ---- paid from: cash list for cash, bank list for M-Pesa / cheque / bank
async function loadPaidFrom(selected) {
  const data = await sendHttpRequest(
    `${expenseRoute}/paidfrom?method=${paymethodSelect.value}`
  );
  const wanted = selected || data.default || '';
  fillSelect(
    paidFromSelect,
    data.accounts,
    'Select account',
    wanted
  );
}

paymethodSelect.addEventListener('change', async function () {
  if (paymethodSelect.value !== CASH_METHOD) requisitionSelect.value = '';
  await loadPaidFrom();
});

// ---- voucher number: shown on new expenses only (kept on edit)
if (!isEdit) {
  expenseDateInput.addEventListener('change', async function (e) {
    const data = await sendHttpRequest(
      `${expenseRoute}/voucherno?txndate=${e.target.value}`
    );
    voucherNoInput.value = data;
  });
}

// ---- expense type: accounts, cost centres, requisitions
async function loadAccounts() {
  const type = expenseTypeSelect.value;
  const extra = isEdit ? `&expense=${expenseId}` : '';
  const data = await sendHttpRequest(
    `${expenseRoute}/getaccounts?type=${type}${extra}`
  );
  fillSelect(accountSelect, data, 'Select account');
}

function loadCostCentres() {
  return new Promise(resolve => {
    $.ajax({
      url: `${expenseRoute}/getcostcentre`,
      method: 'POST',
      data: { category: expenseTypeSelect.value },
      success: function (html) {
        $('#costcentre').html(html);
        resolve();
      },
      error: () => resolve(),
    });
  });
}

async function loadRequisitions(selected) {
  const type = Number(expenseTypeSelect.value);
  const centre = costCenterSelect.value;
  requisitionSelect.innerHTML = '';
  if ((type !== 2 && type !== 3) || !centre) {
    requisitionSelect.disabled = true;
    return;
  }
  const extra = isEdit ? `&expense=${expenseId}` : '';
  const data = await sendHttpRequest(
    `${expenseRoute}/requisitionoptions?type=${type}&centre=${centre}${extra}`
  );
  requisitionSelect.disabled = false;
  fillSelect(requisitionSelect, data, null, null);
  requisitionSelect.insertAdjacentHTML(
    'afterbegin',
    '<option value="">No requisition</option>'
  );
  requisitionSelect.value = selected ? String(selected) : '';
  if (requisitionSelect.value !== String(selected || ''))
    requisitionSelect.value = '';
}

expenseTypeSelect.addEventListener('change', async function () {
  await Promise.all([loadAccounts(), loadCostCentres()]);
  await loadRequisitions();
});

$('#costcentre').on('change', function () {
  loadRequisitions();
});

// picking a requisition: it is paid in cash; a new-style one is paid from the cash holding account
requisitionSelect.addEventListener('change', async function () {
  if (!requisitionSelect.value) return;
  const style = requisitionSelect.selectedOptions[0]?.dataset.style;
  if (paymethodSelect.value !== CASH_METHOD) {
    paymethodSelect.value = CASH_METHOD;
    await loadPaidFrom();
  }
  if (style === 'new' && CASH_HOLDING) {
    paidFromSelect.value = CASH_HOLDING;
  }
});

// ---- budget warning
$('#account').on('change', async function () {
  const type = Number(expenseTypeSelect.value?.trim());
  if (!accountSelect.value || type === 3) return;
  if (await checkOverSpent()) {
    $('#alertModal').modal('show');
  }
});

async function checkOverSpent() {
  if (
    expenseDateInput.value == '' ||
    accountSelect.value == '' ||
    expenseTypeSelect.value == '' ||
    (+expenseTypeSelect.value === 2 && costCenterSelect.value == '')
  )
    return;
  let url;
  const edate = expenseDateInput.value;
  const type = expenseTypeSelect.value;
  const aid = accountSelect.value;
  if (+expenseTypeSelect.value === 1) {
    url = `${expenseRoute}/checkoverspent?edate=${edate}&type=${type}&aid=${aid}`;
  } else if (+expenseTypeSelect.value === 2) {
    const gid = costCenterSelect.value;
    url = `${expenseRoute}/checkoverspent?edate=${edate}&type=${type}&aid=${aid}&gid=${gid}`;
  }
  const data = await sendHttpRequest(url);
  return data;
}

// ---- one click saves once
const saveBtn = document.getElementById('save-btn');
form.addEventListener('submit', function () {
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving...';
});
