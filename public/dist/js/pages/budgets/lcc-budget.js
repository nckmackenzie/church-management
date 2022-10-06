import {
  validation,
  clearOnChange,
  HOST_URL,
  sendHttpRequest,
  getSelectedText,
} from '../utils/utils.js';
const form = document.querySelector('form');
const saveBtn = document.querySelector('.savebtn');
const mandatoryField = document.querySelectorAll('.mandatory');
const yearSelect = document.getElementById('year');
const idInput = document.getElementById('id');
const yearTextInput = document.getElementById('yeartext');

//year change
yearSelect.addEventListener('change', async function (e) {
  if (!e.target.value || e.target.value === '') return;

  const result = await checkYear();
  if (!result) return;
  saveBtn.disabled = false;
  yearTextInput.value = getSelectedText(yearSelect);
});

//form submission
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  if (validation() > 0) return;

  const result = await checkYear();
  if (!result) return;

  form.submit();
});

clearOnChange(mandatoryField);

async function checkYear() {
  if (!yearSelect.value) return;

  const yearVal = yearSelect.value;
  const id = idInput.value;

  const data = await sendHttpRequest(
    `${HOST_URL}/churchbudgets/checkyear?year=${yearVal}&id=${id}`
  );

  if (+data > 0) {
    saveBtn.disabled = true;
    yearSelect.classList.add('is-invalid');
    yearSelect.nextSibling.nextSibling.textContent =
      'Year budget already created';
    return false;
  }
  return true;
}
