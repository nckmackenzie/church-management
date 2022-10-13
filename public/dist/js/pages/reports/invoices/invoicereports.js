//prettier-ignore
import { btnPreview, sdateInput, edateInput,reportTypeSelect,resultsDiv } from '../utils.js';
//prettier-ignore
import {mandatoryFields,validation,clearOnChange,setdatatable,getColumnTotal,validateDate} from '../../utils/utils.js';
import { invoiceReports, getSelectOptions } from '../ajax.js';
import {
  withBalancesTable,
  paymentByInvoice,
  paymentBySupplier,
  allPayments,
} from './table.js';
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
    criteriaSelect.innerHTML = await getSelectOptions('invoiceno');
  } else if (type === 'bysupplier') {
    sdateInput.value = edateInput.value = criteriaSelect.value = '';
    sdateInput.disabled = false;
    edateInput.disabled = false;
    criteriaSelect.disabled = false;
    criteriaSelect.innerHTML = await getSelectOptions('supplier');
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
  if (!validateDate(sdateInput, edateInput)) return;
  removeErrorState();
  resultsDiv.innerHTML = '';

  let data;
  let criteriaValue;
  let sdate;
  let edate;
  //fetch data
  if (reportType === 'balances') {
    data = await invoiceReports('balances');
  } else if (reportType === 'byinvoice') {
    criteriaValue = criteriaSelect.value;
    data = await invoiceReports('byinvoice', criteriaValue);
  } else if (reportType === 'bysupplier') {
    criteriaValue = criteriaSelect.value;
    sdate = sdateInput.value;
    edate = edateInput.value;
    data = await invoiceReports('bysupplier', criteriaValue, sdate, edate);
  } else if (reportType === 'all') {
    sdate = sdateInput.value;
    edate = edateInput.value;
    data = await invoiceReports('all', null, sdate, edate);
  }

  //records found
  if (data && data?.success) {
    const { results } = data;

    if (reportType === 'balances') {
      resultsDiv.innerHTML = withBalancesTable(results);
      const table = document.getElementById('invoicereport');
      const invoicevalth = document.getElementById('invoiceval');
      const paid = document.getElementById('paid');
      const bal = document.getElementById('bal');
      invoicevalth.innerText = getColumnTotal(table, 4);
      paid.innerText = getColumnTotal(table, 5);
      bal.innerText = getColumnTotal(table, 6);
    } else if (reportType === 'byinvoice') {
      resultsDiv.innerHTML = paymentByInvoice(results);
      const table = document.getElementById('invoicereport');
      const paid = document.getElementById('paid');
      paid.innerText = getColumnTotal(table, 2);
    } else if (reportType === 'bysupplier') {
      resultsDiv.innerHTML = paymentBySupplier(results);
      const table = document.getElementById('invoicereport');
      const paid = document.getElementById('paid');
      paid.innerText = getColumnTotal(table, 3);
    } else if (reportType === 'all') {
      resultsDiv.innerHTML = allPayments(results);
      const table = document.getElementById('invoicereport');
      const paid = document.getElementById('paid');
      paid.innerText = getColumnTotal(table, 4);
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
