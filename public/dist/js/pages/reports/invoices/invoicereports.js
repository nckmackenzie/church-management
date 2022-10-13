//prettier-ignore
import { btnPreview, sdateInput, edateInput,reportTypeSelect,resultsDiv } from '../utils.js';
import {
  mandatoryFields,
  validation,
  clearOnChange,
  setdatatable,
  getColumnTotal,
  numberWithCommas,
} from '../../utils/utils.js';
import { invoiceReports } from '../ajax.js';
const criteriaSelect = document.querySelector('#criteria');
let reportType;
//report type change
reportTypeSelect.addEventListener('change', function (e) {
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
  resultsDiv.innerHTML = '';

  let data;
  if (reportType === 'balances') {
    data = await invoiceReports('balances');
  }
  if (data && data?.success) {
    const { results } = data;
    resultsDiv.innerHTML = withBalancesTable(results);
    const table = document.getElementById('invoicereport');
    const invoicevalth = document.getElementById('invoiceval');
    const paid = document.getElementById('paid');
    const bal = document.getElementById('bal');
    invoicevalth.innerText = getColumnTotal(table, 4);
    paid.innerText = getColumnTotal(table, 5);
    bal.innerText = getColumnTotal(table, 6);

    setdatatable('invoicereport', undefined, 50);
  }
});

function withBalancesTable(data) {
  let html = `
    <table class="table table-striped table-bordered table-sm" id="invoicereport">
       <thead>
          <tr>
            <th>Supplier</th>
            <th>Invoice No</th>
            <th>Invoice Date</th>
            <th>Due Date</th>
            <th>Invoice Amount</th>
            <th>Amount Paid</th>
            <th>Balance</th>
          </tr>
       </thead>
       <tbody>`;
  data.forEach(dt => {
    html += `
            <tr>
              <td>${dt.supplierName}</td>
              <td>${dt.invoiceNo}</td>
              <td>${dt.invoiceDate}</td>
              <td>${dt.dueDate}</td>
              <td>${numberWithCommas(dt.inclusiveVat)}</td>
              <td>${numberWithCommas(dt.amountPaid)}</td>
              <td>${numberWithCommas(dt.Balance)}</td>
            </tr>
         `;
  });

  html += `</tbody>
  <tfoot>
    <tr>
        <th colspan="4" style="text-align:center">Total:</th>
        <th id="invoiceval"></th>
        <th id="paid"></th>
        <th id="bal"></th>
    </tr>
  </tfoot>
    </table>
  `;
  return html;
}

clearOnChange(mandatoryFields);
