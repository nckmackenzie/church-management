import { mandatoryFields, clearOnChange } from '../utils/utils.js';
import { getSupplierDetails } from './ajax-requests.js';
import { formatDate, addDays } from './functionalities.js';

const supplierSelect = document.getElementById('supplier');
const invoiceDateInput = document.getElementById('idate');
const dueDateInput = document.getElementById('duedate');
const invoiceNoInput = document.getElementById('invoiceno');
const pinInput = document.getElementById('pin');
const emailInput = document.getElementById('email');
const vatTypeSelect = document.getElementById('vattype');
const vatSelect = document.getElementById('vat');
const productSelect = document.getElementById('product');

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
  if (e.target.value == '' || !e.target.value) return;
  if (+e.target.value === 0) {
    $('#addModal').modal('show');
    this.value = '';
  }
});

clearOnChange(mandatoryFields);
vatSelect.value = '';
