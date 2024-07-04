import {
  formatDate,
  numberWithCommas,
  setdatatable,
} from '../../utils/utils.js';
import { getGroupPlRevenueDetailed } from '../ajax.js';
import { removeLoadingSpinner, setLoadingSpinner } from '../utils.js';

async function loadReport() {
  const urlSearchParams = new URLSearchParams(window.location.search);
  const params = Object.fromEntries(urlSearchParams.entries());
  const titleHeading = document.querySelector('.text-capitalize');
  const { type, sdate, group, edate } = params;
  const formatedStartDate = formatDate(new Date(sdate));
  const formatedEndDate = formatDate(new Date(edate));
  titleHeading.textContent = `${type} between ${formatedStartDate} and ${formatedEndDate}`;
  setLoadingSpinner();
  const data = await getGroupPlRevenueDetailed(type, sdate, edate, group);
  removeLoadingSpinner();
  if (data && data.success) {
    bindTable(data.results, data.total);
  }
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
                  <th>Amount</th>
                  <th>Narration</th>
              </tr>
          </thead>
          <tbody>`;
  data.forEach(dt => {
    html += `
          <tr>
              <td>${dt.transactionDate}</td>
              <td>${numberWithCommas(dt.amount)}</td>
              <td>${dt.narration}</td>
          </tr>
      `;
  });
  html += `
          </tbody>
          <tfoot>
              <tr>
                  <th style="text-align:center">Total</th>
                  <th id="total">${numberWithCommas(totals) || 0}</th>
                  <th></th>
                  <th></th>
              </tr>
          </tfoot>
      </table>
    `;
  tableContainer.innerHTML = html;
  setdatatable('table', [], 50);
}

loadReport();
