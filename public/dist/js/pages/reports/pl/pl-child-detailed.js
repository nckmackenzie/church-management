import {
  formatDate,
  numberWithCommas,
  setdatatable,
  HOST_URL,
} from '../../utils/utils.js';
import {
  removeLoadingSpinner,
  setLoadingSpinner,
  getRequest,
} from '../utils.js';

async function loadReport() {
  const urlSearchParams = new URLSearchParams(window.location.search);
  const params = Object.fromEntries(urlSearchParams.entries());
  const titleHeading = document.querySelector('.text-capitalize');

  const { account, sdate, edate } = params;
  const formatedStartDate = formatDate(new Date(sdate));
  const formatedEndDate = formatDate(new Date(edate));
  titleHeading.textContent = `${account} between ${formatedStartDate} and ${formatedEndDate}`;
  setLoadingSpinner();
  const data = await getPlDetailed(account, sdate, edate);
  removeLoadingSpinner();
  if (data && data.success) {
    bindTable(data.results, data.total);
  }
}

export async function getPlDetailed(account, sdate, edate) {
  const url = `${HOST_URL}/reports/plchildaccountdetailedrpt?account=${account}&sdate=${sdate}&edate=${edate}`;
  return await getRequest(url);
}

function bindTable(data, totals) {
  let html = '';
  const tableContainer = document.querySelector('.table-responsive');
  tableContainer.innerHTML = '';
  html += `
    <table class="table table-bordered table-sm" id="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Account</th>
                <th>Amount</th>
                <th>Narration</th>
                <th>Transaction</th>
                <th>Parent Account</th>
            </tr>
        </thead>
        <tbody>`;
  data.forEach(dt => {
    html += `
        <tr>
            <td>${dt.transactionDate}</td>
            <td>${dt.account}</td>
            <td>${numberWithCommas(dt.amount)}</td>
            <td>${dt.narration}</td>
            <td>${dt.transaction}</td>
            <td>${dt.parentAccount}</td>
        </tr>
    `;
  });
  html += `    
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" style="text-align:center">Total</th>
                <th id="total">${numberWithCommas(totals) || 0}</th>
                <th colspan="3"></th>
            </tr>
        </tfoot>
    </table>
  `;
  tableContainer.innerHTML = html;
  setdatatable('table', [], 100, true, 2);
  // renderTable();
}

loadReport();
