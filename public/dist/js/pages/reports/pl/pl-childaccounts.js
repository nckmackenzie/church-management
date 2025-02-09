import {
  formatDate,
  numberWithCommas,
  setdatatable,
  HOST_URL,
} from '../../utils/utils.js';
import {
  getRequest,
  removeLoadingSpinner,
  setLoadingSpinner,
} from '../utils.js';

async function loadReport() {
  const urlSearchParams = new URLSearchParams(window.location.search);
  const params = Object.fromEntries(urlSearchParams.entries());
  const titleHeading = document.querySelector('.text-capitalize');

  const { account, sdate, edate } = params;
  const formatedStartDate = formatDate(new Date(sdate));
  const formatedEndDate = formatDate(new Date(edate));
  titleHeading.textContent = `${account} subaccounts between ${formatedStartDate} and ${formatedEndDate}`;
  setLoadingSpinner();
  const data = await getPlChildAccounts(account, sdate, edate);
  removeLoadingSpinner();
  if (data && data.success) {
    bindTable(data.results, sdate, edate);
  }
}

export async function getPlChildAccounts(account, sdate, edate) {
  const url = `${HOST_URL}/reports/plchildaccountrpt?account=${account}&sdate=${sdate}&edate=${edate}`;
  return await getRequest(url);
}

function bindTable(data, sdate, edate) {
  let html = '';
  const tableContainer = document.querySelector('.table-responsive');
  const totals = data.reduce((acc, curr) => acc + parseFloat(curr.amount), 0);
  console.log(totals);
  tableContainer.innerHTML = '';
  html += `
    <table class="table table-bordered table-sm" id="table">
        <thead>
            <tr>
                <th>Account</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>`;
  data.forEach(dt => {
    html += `
        <tr>
            <td>${dt.account}</td>
            <td>
              <a target="_blank" href="${HOST_URL}/reports/plchildaccountdetailed?account=${dt.account.toLowerCase()}&sdate=${sdate}&edate=${edate}">
              ${numberWithCommas(dt.amount)}
              </a>
            </td>
        </tr>
    `;
  });
  html += `    
        </tbody>
        <tfoot>
            <tr>
                <th style="text-align:center">Total</th>
                <th id="total">${numberWithCommas(totals) || 0}</th>
            </tr>
        </tfoot>
    </table>
  `;
  tableContainer.innerHTML = html;
  setdatatable('table', [], 100);
  // renderTable();
}
loadReport();
