import { getAccountsReport } from '../ajax.js';
import { removeLoadingSpinner, setLoadingSpinner } from '../utils.js';
import {
  numberWithCommas,
  getColumnTotal,
  setdatatable,
  formatDate,
} from '../../utils/utils.js';
async function loadReport() {
  const urlSearchParams = new URLSearchParams(window.location.search);
  const params = Object.fromEntries(urlSearchParams.entries());

  const resultsDiv = document.querySelector('.table-responsive');
  const titleHeading = document.querySelector('.text-capitalize');
  const { account, asdate } = params;
  const asOfDate = formatDate(new Date(asdate));

  titleHeading.textContent = `${account} as of ${asOfDate}`;
  setLoadingSpinner();
  const data = await getAccountsReport(account, asdate);
  removeLoadingSpinner();
  if (data && data.success) {
    // bindTable(data.results, data.total);
    resultsDiv.innerHTML = createTable(data.results);
    const table = document.getElementById('table');
    const debits = document.getElementById('totaldebits');
    const credits = document.getElementById('totalcredits');
    debits.innerText = getColumnTotal(table, 2);
    credits.innerText = getColumnTotal(table, 3);
    setdatatable('table', undefined, 50);
  }
}

function createTable(data) {
  let html = `
          <table class="table table-sm table-bordered table-striped" id="table">
              <thead>
                  <tr>
                      <th>Date</th>
                      <th>G/L Account</th>
                      <th>Debit</th>
                      <th>Credit</th>
                      <th>Narration</th>
                      <th>Transaction</th>
                  </tr>
              </thead>
              <tbody>`;
  data.forEach(dt => {
    html += `
                        <tr>
                          <td>${dt.transactionDate}</td>
                          <td>${dt.account}</td>
                          <td>${numberWithCommas(dt.debit)}</td>
                          <td>${numberWithCommas(dt.credit)}</td>
                          <td>${dt.narration}</td>
                          <td>${dt.transactionType}</td>
                        </tr>
                     `;
  });
  html += `</tbody>
              <tfoot>
                <tr>
                    <th colspan="2" style="text-align:center">Total:</th>
                    <th id="totaldebits"></th>
                    <th id="totalcredits"></th>
                    <th colspan="2"></th>
                </tr>
              </tfoot>
                </table>`;
  return html;
}

loadReport();
