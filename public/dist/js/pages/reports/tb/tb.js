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
const table = document.getElementById('table');

//click handler
btnPreview.addEventListener('click', async function () {
  if (validation() > 0) return;
  if (!validateDate(sdateInput, edateInput)) return;
  resultsDiv.classList.add('d-none');
  table.getElementsByTagName('tbody')[0].innerHTML = '';
  setLoadingSpinner();
  const sdateVal = sdateInput.value;
  const edateVal = edateInput.value;
  const reportType = reportTypeSelect.value;
  const data = await getTrialBalance(reportType, sdateVal, edateVal);
  removeLoadingSpinner(resultsDiv);
  if (data && data.success) {
    appendTbody(data.results, reportType);
    const debitTotal = document.getElementById('debittotal');
    const creditTotal = document.getElementById('credittotal');
    debitTotal.innerText = getColumnTotal(table, 1);
    creditTotal.innerText = getColumnTotal(table, 2);
    setdatatable('table', undefined, 50);
  }
});

function appendTbody(data, type) {
  const tbody = table.getElementsByTagName('tbody')[0];
  data.forEach(dt => {
    let html = `
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
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', html);
  });
}

clearOnChange(mandatoryFields);
createSpinnerContainer();
