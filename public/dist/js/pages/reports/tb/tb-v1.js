//prettier-ignore
import {
    clearOnChange,
    getColumnTotal,
    HOST_URL,
    mandatoryFields,
    numberWithCommas,
    setdatatable,
    validation
} from '../../utils/utils.js';
import {
  btnPreview,
  createSpinnerContainer,
  getRequest,
  removeLoadingSpinner,
  reportTypeSelect,
  resultsDiv,
  setLoadingSpinner,
} from '../utils.js';
// import { getTrialBalance } from '../ajax.js';

export async function getTrialBalance(type, asofdate) {
  const res = await getRequest(
    `${HOST_URL}/trialbalance/getreport?type=${type}&asofdate=${asofdate}`
  );

  return res;
}

//click handler
btnPreview.addEventListener('click', async function () {
  const asofDate = document.getElementById('asofdate');
  if (validation() > 0) return;
  //   if (!validateDate(sdateInput, edateInput)) return;
  resultsDiv.classList.add('d-none');
  resultsDiv.innerHTML = '';
  // table.getElementsByTagName('tbody')[0].innerHTML = '';
  setLoadingSpinner();
  const asofdateVal = asofDate.value;
  //   const edateVal = edateInput.value;
  //   const sdateVal = sdateInput.value;
  //   const edateVal = edateInput.value;
  const reportType = reportTypeSelect.value;
  const data = await getTrialBalance(reportType, asofdateVal);
  removeLoadingSpinner(resultsDiv);
  if (data && data.success) {
    resultsDiv.innerHTML = appendTbody(data.results, reportType, asofdateVal);
    const table = document.getElementById('table');
    const debitTotal = document.getElementById('debittotal');
    const creditTotal = document.getElementById('credittotal');
    debitTotal.innerText = getColumnTotal(table, 1);
    creditTotal.innerText = getColumnTotal(table, 2);
    setdatatable('table', undefined, 50);
  }
});

function appendTbody(data, type, asofdate) {
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
    if (dt.net_balance !== '') {
      const account = dt.account;
      const debit =
        !isNaN(parseFloat(dt.net_balance)) && parseFloat(dt.net_balance) > 0
          ? parseFloat(dt.net_balance)
          : '';
      const credit =
        !isNaN(parseFloat(dt.net_balance)) && parseFloat(dt.net_balance) < 0
          ? parseFloat(dt.net_balance) * -1
          : '';
      html += `
    <tr>
        <td>${String(account).toUpperCase()}</td>
        <td class="text-center"><a target="_blank" href='${HOST_URL}/trialbalance/report?type=${type}&account=${account}&asofdate=${asofdate}'>
        ${isNaN(parseFloat(debit)) ? '' : numberWithCommas(debit)}</a>
        </td>
        <td class="text-center"><a target="_blank" href='${HOST_URL}/trialbalance/report?type=${type}&account=${account}&asofdate=${asofdate}'>
        ${
          isNaN(parseFloat(credit))
            ? ''
            : numberWithCommas(parseFloat(credit).toFixed(2))
        }</a></td>
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

function formatString(string) {
  return string.replaceAll(' ', '-');
}
