import { sendHttpRequest, alertBox } from '../utils/utils.js';
export const btnPreview = document.querySelector('.preview');
export const sdateInput = document.querySelector('#sdate');
export const edateInput = document.querySelector('#edate');
export const resultsDiv = document.querySelector('#results');
export const reportTypeSelect = document.querySelector('#type');
const contentDiv = document.querySelector('.content');

export function createSpinnerContainer() {
  contentDiv.insertAdjacentHTML(
    'afterbegin',
    '<div class="spinner-container d-flex justify-content-center"></div>'
  );
}

export function setLoadingSpinner() {
  const spinnerContainer = document.querySelector('.spinner-container');
  let html = `<div class="spinner md"></div> `;
  spinnerContainer.innerHTML = html;
}

export function removeLoadingSpinner(elm = undefined) {
  if (elm && elm.classList.contains('d-none')) {
    elm.classList.remove('d-none');
  }
  const spinnerContainer = document.querySelector('.spinner-container');
  spinnerContainer.innerHTML = '';
}

export async function getRequest(url) {
  return await sendHttpRequest(url, 'GET', undefined, {}, alertBox);
}
