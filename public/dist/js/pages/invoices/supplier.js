import { mandatoryFields, clearOnChange } from '../utils/utils.js';
import { getSupplierDetails, saveProduct } from './ajax-requests.js';
import { formatDate, addDays, validateModal } from './functionalities.js';

const supplierSelect = document.getElementById('supplier');
const invoiceDateInput = document.getElementById('idate');
const dueDateInput = document.getElementById('duedate');
const invoiceNoInput = document.getElementById('invoiceno');
const pinInput = document.getElementById('pin');
const emailInput = document.getElementById('email');
const vatTypeSelect = document.getElementById('vattype');
const vatSelect = document.getElementById('vat');
const productSelect = document.getElementById('product');
const modalForm = document.getElementById('productForm');
const modalBtn = document.getElementById('newproduct');
export const modalRequired = document.querySelectorAll('.modalrequired');
export const modalAlert = document.querySelector('.modalAlert');

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

productSelect.addEventListener('change', function (e) {
  console.log(e.target.value);
  if (e.target.value == '' || !e.target.value) return;
  if (+e.target.value === 0) {
    $('#addModal').modal('show');
    this.value = '';
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
    // console.log(data);
    document.getElementById('name').value =
      document.getElementById('desc').value =
      document.getElementById('sellingprice').value =
      document.getElementById('account').value =
        '';
    $('#addModal').modal('toggle');
    productSelect.innerHTML = '';
    productSelect.innerHTML = data.products;
    productSelect.value = data.productid;
    document.getElementById('rate').value = rate;
  }
});

clearOnChange(mandatoryFields);
vatSelect.value = '';
