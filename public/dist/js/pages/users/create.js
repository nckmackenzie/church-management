import {
  mandatoryFields,
  clearOnChange,
  clearValues,
  sendHttpRequest,
  HOST_URL,
  validation,
  alertBox,
} from '../utils/utils.js';

const usertypeSelect = document.getElementById('usertype');
const districtSelect = document.getElementById('district');
const form = document.querySelector('form');

usertypeSelect.addEventListener('change', function (e) {
  if (!e.target.value || e.target.value === '') return;
  if (e.target.value === '4') {
    districtSelect.disabled = false;
    districtSelect.classList.add('mandatory');
  } else {
    districtSelect.value = '';
    districtSelect.disabled = true;
    districtSelect.classList.remove('mandatory');
  }
});

districtSelect.addEventListener('change', function () {
  this.classList.remove('is-invalid');
  this.nextSibling.nextSibling.textContent = '';
});

form.addEventListener('submit', async function (e) {
  e.preventDefault();
  alertBox.innerHTML = '';
  clearOnChange(mandatoryFields);
  if (validation() > 0) return;

  const formdata = Object.fromEntries(new FormData(e.target).entries());
  const response = await sendHttpRequest(
    `${HOST_URL}/users/createupdate`,
    'POST',
    JSON.stringify(formdata),
    { 'Content-Type': 'application/json' },
    alertBox
  );

  if (!response.success) {
    displayAlert(response.message);
    return;
  }

  document.querySelector('#success-msg').classList.remove('d-none');
  document.querySelector('.message').textContent = response.message;
  clearValues();
});

function displayAlert(error) {
  const html = `
    <div class="alert custom-danger" role="alert">
      ${error.isArray() ? error.join('') : error}
    </div>
  `;
  alertBox.insertAdjacentHTML('afterbegin', html);
}

clearOnChange(mandatoryFields);
