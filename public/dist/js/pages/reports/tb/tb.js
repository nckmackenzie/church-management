//prettier-ignore
import {mandatoryFields,clearOnChange,validation,validateDate,numberWithCommas,
        getColumnTotal,setdatatable} from '../../utils/utils.js'
import {
  btnPreview,
  sdateInput,
  edateInput,
  reportTypeSelect,
  resultsDiv,
  setLoadingSpinner,
  removeLoadingSpinner,
  createSpinnerContainer,
} from '../utils.js';
import { getTrialBalance } from '../ajax.js';

//click handler
btnPreview.addEventListener('click', async function () {
  if (validation() > 0) return;
  if (!validateDate(sdateInput, edateInput)) return;
  resultsDiv.classList.add('d-none');
  resultsDiv.innerHTML = '';
  // table.getElementsByTagName('tbody')[0].innerHTML = '';
  setLoadingSpinner();
  const sdateVal = sdateInput.value;
  const edateVal = edateInput.value;
  const reportType = reportTypeSelect.value;
  const data = await getTrialBalance(reportType, sdateVal, edateVal);
  removeLoadingSpinner(resultsDiv);
  if (data && data.success) {
    resultsDiv.innerHTML = appendTbody(data.results, reportType);
    const table = document.getElementById('table');
    const debitTotal = document.getElementById('debittotal');
    const creditTotal = document.getElementById('credittotal');
    debitTotal.innerText = getColumnTotal(table, 1);
    creditTotal.innerText = getColumnTotal(table, 2);
    setdatatable('table', undefined, 50);
  }
});

function appendTbody(data, type) {
  let html = `
  <table id="table" class="table table-striped table-bordered table-sm">
    <thead class="bg-lightblue">
      <tr>
          <th>Account</th>
          <th class="text-center">Debit</th>
          <th class="text-center">Credit</th>
      </tr>
    </thead>
    <tbody>`;
  data.forEach(dt => {
    if (dt.credit !== '' || dt.Debit !== '') {
      html += `
        <tr>
            <td>${String(
              type === 'detailed' ? dt.account : dt.parentaccount
            ).toUpperCase()}</td>
            <td class="text-center">${numberWithCommas(dt.Debit)}</td>
            <td class="text-center">${
              isNaN(parseFloat(dt.credit))
                ? ''
                : numberWithCommas(parseFloat(dt.credit).toFixed(2))
            }</td>
        </tr>`;
    }
  });
  html += `
  </tbody>
  <tfoot>
      <tr>
          <th style="text-align:right">Total</th>
          <th class="text-center" id="debittotal"></th>
          <th class="text-center" id="credittotal"></th>
      </tr>
  </tfoot>
</table>
  `;

  return html;
}

clearOnChange(mandatoryFields);
createSpinnerContainer();
