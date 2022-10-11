import {
  mandatoryFields,
  clearOnChange,
  updateSubTotal,
} from '../utils/utils.js';
import {
  getSupplierDetails,
  saveProduct,
  getProductRate,
} from './ajax-requests.js';
import {
  formatDate,
  addDays,
  validateModal,
  calcGrossValue,
  addToTable,
} from './functionalities.js';

const supplierSelect = document.getElementById('supplier');
const invoiceDateInput = document.getElementById('idate');
const dueDateInput = document.getElementById('duedate');
const invoiceNoInput = document.getElementById('invoiceno');
const pinInput = document.getElementById('pin');
const emailInput = document.getElementById('email');
const vatTypeSelect = document.getElementById('vattype');
const vatSelect = document.getElementById('vat');
export const productSelect = document.getElementById('product');
const modalForm = document.getElementById('productForm');
const modalBtn = document.getElementById('newproduct');
export const modalRequired = document.querySelectorAll('.modalrequired');
export const modalAlert = document.querySelector('.modalAlert');
export const rateInput = document.getElementById('rate');
export const qtyInput = document.getElementById('qty');
export const grossInput = document.getElementById('gross');
const addBtn = document.getElementById('add');
const totalsInput = document.getElementById('totals');
export const table = document.getElementById('details');
const addInputControls = document.querySelectorAll('.addcontrol');
const invoiceForm = document.getElementById('invoiceForm');
// export const descriptionInput = document.getElementById('description');

//fetch supplier details
supplierSelect.addEventListener('change', async function (e) {
  if (e.target.value == '' || !e.target.value) return;
  const fetchedDetails = await getSupplierDetails(+e.target.value);
  pinInput.value = fetchedDetails.pin;
  emailInput.value = fetchedDetails.email;
});

invoiceDateInput.addEventListener('change', function (e) {
  if (e.target.value == '' || !e.target.value) return;
  dueDateInput.value = formatDate(addDays(e.target.value, 30));
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
});

productSelect.addEventListener('change', async function (e) {
  if (e.target.value == '' || !e.target.value) return;
  if (+e.target.value === 0) {
    $('#addModal').modal('show');
    this.value = '';
    return;
  }
  rateInput.value = await getProductRate(+e.target.value);
  if (rateInput.value != '') {
    rateInput.classList.remove('is-invalid');
    rateInput.nextSibling.nextSibling.textContent = '';
  }
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
  const rate = document.getElementById('sellingprice').value;

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
    rateInput.value = rate;
  }
});

qtyInput.addEventListener('blur', calcGrossValue);
rateInput.addEventListener('blur', calcGrossValue);

addBtn.addEventListener('click', function () {
  let error = 0;
  addInputControls.forEach(cntrl => {
    if (cntrl.value === '') {
      cntrl.classList.add('is-invalid');
      cntrl.nextSibling.nextSibling.textContent = 'Field is required';
      error++;
    }
  });
  if (error > 0) return;
  addToTable();
  updateSubTotal(table, 4, 'input', totalsInput);
  productSelect.value =
    qtyInput.value =
    rateInput.value =
    grossInput.value =
      '';
});

//remove button clicked
table.addEventListener('click', function (e) {
  if (!e.target.classList.contains('text-danger')) return;
  const btn = e.target;
  btn.closest('tr').remove();
  updateSubTotal(table, 4, 'input', totalsInput);
});

clearOnChange(mandatoryFields);
clearOnChange(addInputControls);
vatSelect.value = '';
