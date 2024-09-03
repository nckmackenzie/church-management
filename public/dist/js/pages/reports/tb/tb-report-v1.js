// import { getTrialBalanceReport } from '../ajax.js';
import {
  numberWithCommas,
  getColumnTotal,
  setdatatable,
  HOST_URL,
} from '../../utils/utils.js';
import { getRequest } from '../utils.js';
const urlSearchParams = new URLSearchParams(window.location.search);
const params = Object.fromEntries(urlSearchParams.entries());
const spinnerContainer = document.querySelector('.spinner-container');
const resultsDiv = document.querySelector('#results');

function loadingState() {
  resultsDiv.innerHTML = '';
  resultsDiv.classList.add('d-none');
  spinnerContainer.innerHTML = '<div class="spinner md"></div>';
}

function resetLoadingState() {
  spinnerContainer.innerHTML = '';
  resultsDiv.classList.remove('d-none');
}

export async function getTrialBalanceReport(type, account, asofdate) {
  const url = `${HOST_URL}/trialbalance/detailedreport?type=${type}&account=${account}&asofdate=${asofdate}`;
  const res = await getRequest(url);

  return res;
}

async function loadData() {
  const { type, account, asofdate } = params;
  loadingState();
  const res = await getTrialBalanceReport(type, account, asofdate);
  resetLoadingState();
  if (res && res.success) {
    resultsDiv.innerHTML = createTable(res.results);
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

loadData();
