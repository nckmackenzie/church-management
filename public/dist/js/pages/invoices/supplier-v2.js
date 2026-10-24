import {
  updateSubTotal,
  displayAlert,
  alertBox,
  setLoadingState,
  resetLoadingState,
  HOST_URL,
} from '../utils/utils.js';
import {
  getSupplierDetails,
  saveProduct,
  getProduct,
  saveForm,
} from './ajax-requests-v2.js';
import {
  formatDate,
  addDays,
  validateModal,
  calcGrossValue,
  addToTable,
  calculateVat,
  header,
  tableData,
} from './functionalities-v2.js';
import {
  setError,
  clearError,
  validateRequired,
  clearOnChange,
} from '../utils/feedback.js';

export const supplierSelect = document.getElementById('supplier');
export const invoiceDateInput = document.getElementById('idate');
export const dueDateInput = document.getElementById('duedate');
export const invoiceNoInput = document.getElementById('invoiceno');
const pinInput = document.getElementById('pin');
const emailInput = document.getElementById('email');
export const vatTypeSelect = document.getElementById('vattype');
export const vatSelect = document.getElementById('vat');
export const payableSelect = document.getElementById('payable');
export const productSelect = document.getElementById('product');
export const lineAccountText = document.getElementById('lineaccount');
const modalForm = document.getElementById('productForm');
const modalBtn = document.getElementById('newproduct');
export const modalRequired = document.querySelectorAll('.modalrequired');
export const modalAlert = document.querySelector('.modalAlert');
export const rateInput = document.getElementById('rate');
export const qtyInput = document.getElementById('qty');
export const grossInput = document.getElementById('gross');
const addBtn = document.getElementById('add');
export const totalsInput = document.getElementById('totals');
export const table = document.getElementById('details');
const addInputControls = document.querySelectorAll('.addcontrol');
const invoiceForm = document.getElementById('invoiceForm');
const saveBtn = document.getElementById('save');
const GROSS_CELL = 5;

function showLineAccount(account) {
  lineAccountText.dataset.account = account || '';
  lineAccountText.textContent = account ? `Debit account: ${account}` : '';
}

//fetch supplier details
supplierSelect.addEventListener('change', async function (e) {
  if (e.target.value == '' || !e.target.value) return;
  const fetchedDetails = await getSupplierDetails(+e.target.value);
  if (!fetchedDetails) return;
  pinInput.value = fetchedDetails.pin;
  emailInput.value = fetchedDetails.email;
});

invoiceDateInput.addEventListener('change', function (e) {
  if (e.target.value == '' || !e.target.value) return;
  dueDateInput.value = formatDate(addDays(e.target.value, 30));
  clearError(dueDateInput);
});

vatTypeSelect.addEventListener('change', function (e) {
  if (e.target.value == '' || !e.target.value) return;
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
  if (e.target.value == '' || !e.target.value) return;
  if (+e.target.value === 0) {
    $('#addModal').modal('show');
    this.value = '';
    showLineAccount('');
    return;
  }
  const product = await getProduct(+e.target.value);
  if (!product) {
    this.value = '';
    showLineAccount('');
    return;
  }
  rateInput.value = product.rate;
  showLineAccount(product.account);
  if (rateInput.value != '') clearError(rateInput);
  calcGrossValue();
});

//product save
modalForm.addEventListener('submit', async function (e) {
  e.preventDefault();
  if (validateModal() > 0) return;
  const productDetails = {
    productName: document.getElementById('name').value,
    description: document.getElementById('desc').value,
    rate: document.getElementById('sellingprice').value,
    account: document.getElementById('account').value,
  };

  modalBtn.disabled = true;
  modalBtn.textContent = 'Saving...';
  const data = await saveProduct(productDetails);
  modalBtn.disabled = false;
  modalBtn.textContent = 'Save';
  if (data) {
    document.getElementById('name').value =
      document.getElementById('desc').value =
      document.getElementById('sellingprice').value =
      document.getElementById('account').value =
        '';
    $('#addModal').modal('toggle');
    productSelect.innerHTML = '';
    productSelect.innerHTML = data.products;
    productSelect.value = data.productid;
    // the new product's rate and account come from the server like any other product
    productSelect.dispatchEvent(new Event('change'));
  }
});

qtyInput.addEventListener('blur', calcGrossValue);
rateInput.addEventListener('blur', calcGrossValue);

addBtn.addEventListener('click', function () {
  let error = 0;
  addInputControls.forEach(cntrl => {
    clearError(cntrl);
    if (cntrl.value === '') {
      setError(cntrl, 'Field is required');
      error++;
    }
  });
  if (error > 0) return;
  calcGrossValue();
  addToTable();
  updateSubTotal(table, GROSS_CELL, 'input', totalsInput);
  calculateVat();
  productSelect.value =
    qtyInput.value =
    rateInput.value =
    grossInput.value =
      '';
  showLineAccount('');
});

//remove button clicked
table.addEventListener('click', function (e) {
  if (!e.target.classList.contains('text-danger')) return;
  const btn = e.target;
  btn.closest('tr').remove();
  updateSubTotal(table, GROSS_CELL, 'input', totalsInput);
  calculateVat();
});

invoiceForm.addEventListener('submit', async function (e) {
  e.preventDefault();
  if (validateRequired() > 0) return;
  const tbodyRow = table.getElementsByTagName('tbody')[0];
  if (+tbodyRow.rows.length === 0) {
    displayAlert(alertBox, 'Enter products');
    return;
  }
  const formData = { header: header(), table: tableData() };
  setLoadingState(saveBtn, 'Saving...');
  const res = await saveForm(formData);
  if (res && res?.success) {
    window.location.replace(`${HOST_URL}/supplierinvoices`);
    return;
  }
  resetLoadingState(saveBtn, 'Save');
});

clearOnChange(document.querySelectorAll('.mandatory'));
clearOnChange(addInputControls);
if (+vatTypeSelect.value <= 1) vatSelect.value = '';

// an invoice that has payments: only the due date and the invoice number may change (the server enforces it)
if (invoiceForm.dataset.locked) {
  [supplierSelect, invoiceDateInput, vatTypeSelect, vatSelect, payableSelect, productSelect, qtyInput, rateInput, addBtn].forEach(
    field => (field.disabled = true)
  );
  table.querySelectorAll('.btnremove').forEach(btn => (btn.style.display = 'none'));
}

// edit page: show the totals of the lines already on the invoice
if (table.getElementsByTagName('tbody')[0].rows.length > 0) {
  updateSubTotal(table, GROSS_CELL, 'input', totalsInput);
  calculateVat();
}
