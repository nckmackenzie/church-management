import {
  formatDate,
  HOST_URL,
  numberWithCommas,
  sendHttpRequest,
  setdatatable,
} from '../utils/utils.js';

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
  const data = await getClearedReport(bank, sdate, edate, type);
  spinnerContainer.innerHTML = '';
  tableArea.classList.remove('d-none');
  if (data && data?.success) {
    const { results } = data;
    appendTable(results);
    setdatatable('clearedTable', columnDefs());
  }
});

function appendTable(data) {
  const tbody = document
    .getElementById('clearedTable')
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

async function getClearedReport(bank, sdate, edate, type) {
  const res = await sendHttpRequest(
    `${HOST_URL}/bankreconcilliations/clearedreport?bank=${bank}&sdate=${sdate}&edate=${edate}&type=${type}`,
    'GET',
    undefined,
    {},
    alertBox
  );

  return res;
}
