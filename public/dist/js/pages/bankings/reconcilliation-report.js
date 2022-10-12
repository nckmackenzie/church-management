import { formatDate, numberWithCommas, setdatatable } from '../utils/utils.js';
import { getUnclearedReport } from './ajax-requests.js';
const urlSearchParams = new URLSearchParams(window.location.search);
const params = Object.fromEntries(urlSearchParams.entries());

window.addEventListener('load', async function () {
  const { type, bank, sdate, edate } = params;
  const titleHeading = document.querySelector('.title');
  const tableArea = document.querySelector('#results');
  const spinnerContainer = document.querySelector('.spinner-container');
  tableArea.classList.add('d-none');
  spinnerContainer.innerHTML = '<div class="spinner md"></div>';
  titleHeading.textContent = `${type}s between ${formatDate(
    sdate
  )} and ${formatDate(edate)}`;

  //ajax
  const data = await getUnclearedReport(bank, sdate, edate, type);
  spinnerContainer.innerHTML = '';
  tableArea.classList.remove('d-none');
  if (data && data?.success) {
    const { results } = data;
    appendTable(results);
    setdatatable('unclearedTable', columnDefs());
  }
});

function appendTable(data) {
  const tbody = document
    .getElementById('unclearedTable')
    .getElementsByTagName('tbody')[0];

  data.forEach(dt => {
    let html = `
        <tr>
            <td>${dt.transactionDate}</td>
            <td>${numberWithCommas(dt.amount)}</td>
            <td>${dt.reference}</td>
        </tr>
    `;
    tbody.insertAdjacentHTML('beforeend', html);
  });
}

function columnDefs() {
  return [
    { width: '15%', targets: 0 },
    { width: '20%', targets: 1 },
  ];
}
