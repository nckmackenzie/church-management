import { getSubaccountsReport } from '../ajax.js';
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
  const { subaccount, asdate } = params;
  const asOfDate = formatDate(new Date(asdate));

  titleHeading.textContent = `Subaccount detailed breakdown as of ${asOfDate}`;
  setLoadingSpinner();
  const data = await getSubaccountsReport(subaccount, asdate);
  removeLoadingSpinner();
  if (data && data.success) {
    // bindTable(data.results, data.total);
    resultsDiv.innerHTML = createTable(data.results);
    const table = document.getElementById('table');
    const totals = document.getElementById('total');
    totals.innerText = getColumnTotal(table, 1);
    setdatatable('table', undefined, 50);
  }
}

function createTable(data) {
  let html = `
          <table class="table table-sm table-bordered table-striped" id="table">
              <thead>
                  <tr>
                      <th>Date</th>
                      <th>Amount</th>
                      <th>Narration</th>
                      <th>Reference</th>
                  </tr>
              </thead>
              <tbody>`;
  data.forEach(dt => {
    html += `
                        <tr>
                          <td>${dt.transactionDate}</td>
                          <td>${numberWithCommas(dt.amount)}</td>
                          <td>${dt.narration}</td>
                          <td>${dt.reference}</td>
                        </tr>
                     `;
  });
  html += `</tbody>
              <tfoot>
                <tr>
                    <th>Total:</th>
                    <th id="total"></th>
                    <th colspan="2"></th>
                </tr>
              </tfoot>
            </table>`;
  return html;
}

loadReport();
