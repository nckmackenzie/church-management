import {
  mandatoryFields,
  clearOnChange,
  validation,
  alertBox,
  setLoadingState,
  resetLoadingState,
  sendHttpRequest,
  HOST_URL,
  clearValues,
  displayAlert,
} from '../utils/utils.js';

const nameInput = document.getElementById('name');
const bankSelect = document.getElementById('bank');
const btn = document.querySelector('.save');
const districtgroupSelect = document.getElementById('districtgroup');
const paramSelect = document.getElementById('param');
const accountSelect = document.getElementById('glaccount');
const paramLabel = document.getElementById('paramLabel');
const form = document.getElementById('subaccount');

form.addEventListener('submit', async function (e) {
  e.preventDefault();

  //   console.log('first');
  if (validation() > 0) return;

  setLoadingState(btn, 'Saving...');
  const data = await submitHandler();
  resetLoadingState(btn, 'Save');
  if (data.success) {
    clearValues();
    displayAlert(alertBox, 'Saved successfully', 'success');
  }
});

districtgroupSelect.addEventListener('change', async function (e) {
  const data = await getdistrictorgroup(e.target.value);
  paramLabel.textContent = 'Select ' + e.target.value;
  paramSelect.innerHTML = '';
  paramSelect.innerHTML = '<option>Select ' + e.target.value + '</option>';
  data.length &&
    data.forEach(dt => {
      let html = '';
      html += `
        <option value="${dt.id}" >${dt.label}</option>
    `;
      paramSelect.insertAdjacentHTML('beforeend', html);
    });
});

async function getdistrictorgroup(type) {
  const data = await sendHttpRequest(
    `${HOST_URL}/banks/getdistrictorgroup?type=${type}`,
    'GET',
    undefined,
    {},
    alertBox
  );
  return data;
}

async function submitHandler() {
  const formdata = Object.fromEntries(new FormData(form).entries());

  const response = await sendHttpRequest(
    `${HOST_URL}/banks/createupdatesubaccount`,
    'POST',
    JSON.stringify(formdata),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  return response;
}

clearOnChange(mandatoryFields);
