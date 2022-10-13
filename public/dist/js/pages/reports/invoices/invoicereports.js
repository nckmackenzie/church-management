//prettier-ignore
import { btnPreview, sdateInput, edateInput,reportTypeSelect,resultsDiv } from '../utils.js';
//prettier-ignore
import {mandatoryFields,validation,clearOnChange,setdatatable,getColumnTotal,numberWithCommas} from '../../utils/utils.js';
import { invoiceReports, getInvoiceNo } from '../ajax.js';
import { withBalancesTable } from './table.js';
const criteriaSelect = document.querySelector('#criteria');
let reportType;
//report type change
reportTypeSelect.addEventListener('change', async function (e) {
  const type = String(e.target.value).trim();
  reportType = type;
  criteriaSelect.innerHTML = '';
  addMandatory();
  if (type === 'balances') {
    sdateInput.value = edateInput.value = criteriaSelect.value = '';
    sdateInput.disabled = true;
    edateInput.disabled = true;
    criteriaSelect.disabled = true;
    removeMandatory();
  } else if (type === 'byinvoice') {
    sdateInput.value = edateInput.value = criteriaSelect.value = '';
    sdateInput.disabled = true;
    edateInput.disabled = true;
    criteriaSelect.disabled = false;
    removeMandatory();
    criteriaSelect.classList.add('mandatory');
    criteriaSelect.innerHTML = await getInvoiceNo();
  } else if (type === 'bysupplier') {
    sdateInput.value = edateInput.value = criteriaSelect.value = '';
    sdateInput.disabled = false;
    edateInput.disabled = false;
    criteriaSelect.disabled = false;
  } else if (type === 'all') {
    sdateInput.value = edateInput.value = criteriaSelect.value = '';
    sdateInput.disabled = false;
    edateInput.disabled = false;
    criteriaSelect.disabled = true;
    criteriaSelect.classList.remove('mandatory');
  }
});

function addMandatory() {
  const formControl = document.querySelectorAll('.form-control');
  formControl.forEach(control => control.classList.add('mandatory'));
}

function removeMandatory() {
  const formControl = document.querySelectorAll('.form-control');
  formControl.forEach(control => control.classList.remove('mandatory'));
}

btnPreview.addEventListener('click', async function () {
  if (validation() > 0) return;
  removeErrorState();
  resultsDiv.innerHTML = '';

  let data;
  if (reportType === 'balances') {
    data = await invoiceReports('balances');
  }
  if (data && data?.success) {
    const { results } = data;
    resultsDiv.innerHTML = withBalancesTable(results);
    const table = document.getElementById('invoicereport');
    if (reportType === 'balances') {
      const invoicevalth = document.getElementById('invoiceval');
      const paid = document.getElementById('paid');
      const bal = document.getElementById('bal');
      invoicevalth.innerText = getColumnTotal(table, 4);
      paid.innerText = getColumnTotal(table, 5);
      bal.innerText = getColumnTotal(table, 6);
    }

    setdatatable('invoicereport', undefined, 50);
  }
});

clearOnChange(mandatoryFields);
function removeErrorState() {
  document.querySelectorAll('.mandatory').forEach(field => {
    if (field.value != '') {
      field.classList.remove('is-invalid');
      field.nextSibling.nextSibling.textContent = '';
    }
  });
}
