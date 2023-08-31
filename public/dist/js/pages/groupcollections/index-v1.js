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

const typeSelect = document.getElementById('type');
const groupSelect = document.getElementById('groupid');
const subaccountSelect = document.getElementById('subaccount');
const accountInput = document.getElementById('account');
const accountIdInput = document.getElementById('accountid');
const bankIdInput = document.getElementById('bankid');
const form = document.getElementById('form');
const btn = document.getElementById('save');

typeSelect.addEventListener('change', async function (e) {
  if (!e.target.value || !String(e.target.value).trim().length) return;
  groupSelect.innerHTML = '';
  groupSelect.innerHTML =
    '<option value="" selected disabled>Select ' + e.target.value + '</option>';
  const data = await getGroupOrDistrict(e.target.value);
  data.length > 0 &&
    data.forEach(grpdst => {
      let html = '';
      html += `
          <option value="${grpdst.id}" >${grpdst.label}</option>
      `;
      groupSelect.insertAdjacentHTML('beforeend', html);
    });
});

groupSelect.addEventListener('change', async function (e) {
  if (!e.target.value || !String(e.target.value).trim().length) return;
  const type = typeSelect.value;
  subaccountSelect.innerHTML = '';
  subaccountSelect.innerHTML =
    '<option value="" selected disabled>Select sub account</option>';
  const data = await getSubAccounts(type, e.target.value);
  data.length > 0 &&
    data.forEach(account => {
      let html = '';
      html += `
          <option value="${account.id}" >${account.label}</option>
      `;
      subaccountSelect.insertAdjacentHTML('beforeend', html);
    });
});

subaccountSelect.addEventListener('change', async function (e) {
  if (!e.target.value || !String(e.target.value).trim().length) return;
  const data = await getAccountDetails(e.target.value);
  accountIdInput.value = data.accountid;
  accountInput.value = data.accountname.toString().toUpperCase();
  bankIdInput.value = data.bankid;
});

form.addEventListener('submit', async function (e) {
  e.preventDefault();
  if (validation() > 0) return;

  //   console.log(validation());

  setLoadingState(btn, 'Saving...');
  const data = await submitHandler();
  resetLoadingState(btn, 'Save');
  if (data.success) {
    clearValues();
    displayAlert(alertBox, 'Saved successfully', 'success');
    subaccountSelect.innerHTML = '';
  }
});

async function getSubAccounts(type, groupId) {
  const data = await sendHttpRequest(
    `${HOST_URL}/groupcollections/getsubaccounts?type=${type}&groupid=${groupId}`,
    'GET',
    undefined,
    {},
    alertBox
  );

  return data;
}

async function getAccountDetails(subaccount) {
  const data = await sendHttpRequest(
    `${HOST_URL}/groupcollections/getaccountdetails?subaccount=${subaccount}`,
    'GET',
    undefined,
    {},
    alertBox
  );

  return data;
}

async function getGroupOrDistrict(type) {
  const data = await sendHttpRequest(
    `${HOST_URL}/groupcollections/getgroupordistrict?type=${type}`,
    'GET',
    undefined,
    {},
    alertBox
  );

  return data;
}

async function submitHandler() {
  const formdata = Object.fromEntries(new FormData(form).entries());
  const data = await sendHttpRequest(
    `${HOST_URL}/groupcollections/createupdate`,
    'POST',
    JSON.stringify(formdata),
    {},
    alertBox
  );

  return data;
}

clearOnChange(mandatoryFields);
