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

  // every server value goes on the page as text (the reference is typed by users)
  data.forEach(dt => {
    const tr = document.createElement('tr');
    [dt.transactionDate, numberWithCommas(dt.amount), dt.reference].forEach(v => {
      const td = document.createElement('td');
      td.textContent = v === null || v === undefined ? '' : String(v);
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
}

function columnDefs() {
  return [
    { width: '15%', targets: 0 },
    { width: '20%', targets: 1 },
  ];
}
