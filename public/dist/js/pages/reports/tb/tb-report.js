import { getTrialBalanceReport } from '../ajax.js';
const urlSearchParams = new URLSearchParams(window.location.search);
const params = Object.fromEntries(urlSearchParams.entries());
const spinnerContainer = document.querySelector('.spinner-container');
const resultsDiv = document.querySelector('#results');
console.log(params);

function loadingState() {
  resultsDiv.innerHTML = '';
  resultsDiv.classList.add('d-none');
  spinnerContainer.innerHTML = '<div class="spinner md"></div>';
}

function resetLoadingState() {
  spinnerContainer.innerHTML = '';
  resultsDiv.classList.remove('d-none');
}

async function loadData() {
  const { type, account, sdate, edate } = params;
  loadingState();
  const res = await getTrialBalanceReport(type, account, sdate, edate);
  resetLoadingState();
  console.log(res);
}

loadData();
