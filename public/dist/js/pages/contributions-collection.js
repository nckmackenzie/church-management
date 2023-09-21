import {
  mandatoryFields,
  clearOnChange,
  alertBox,
  sendHttpRequest,
  validation,
  HOST_URL,
} from './utils/utils.js';

const typeSelect = document.getElementById('collectiontype');
const groupSelect = document.getElementById('collectiongroupid');
const subaccountSelect = document.getElementById('collectionsubaccount');
const accountInput = document.getElementById('collectionaccount');
const accountIdInput = document.getElementById('collectionaccountid');
const bankIdInput = document.getElementById('collectionbankid');
const amountInput = document.getElementById('collectionamount');
const btn = document.querySelector('.btnaddcollection');
const table = document.getElementById('contributions-table');
const body = table.getElementsByTagName('tbody')[0];

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

function getSelectedText(sel) {
  return sel.options[sel.selectedIndex].text;
}

btn.addEventListener('click', function () {
  if (validation() > 0) return;
  const account = accountIdInput.value;
  const accountName = accountInput.value;
  const amount = +amountInput.value;
  const category = typeSelect.value === 'group' ? 2 : '3';
  const categoryName = typeSelect.value;
  const contributor = groupSelect.value;
  const subaccount = subaccountSelect.value;
  const contributorName = getSelectedText(groupSelect);

  let html = `
      <tr>
        <td class="d-none"><input type="text" name="accountsid[]" value="${account}"></td>
        <td><input type="text" class="table-input" name="accountsname[]" value="${accountName}" readonly></td>
        <td><input type="text" class="table-input" name="amounts[]" value="${amount}" readonly></td>
        <td class="d-none"><input type="text" class="table-input" name="categoriesid[]" value="${category}"></td>
        <td><input type="text" class="table-input" name="categoriesname[]" value="${categoryName.toUpperCase()}" readonly></td>
        <td class="d-none"><input type="text" class="table-input" name="contributorsid[]" value="${contributor}"></td>
        <td><input type="text" class="table-input" name="contributorsname[]" value="${contributorName}" readonly></td>
        <td class="d-none"><input type="text" class="table-input" name="subaccount[]" value="${subaccount}" readonly></td>
        <td><button type="button" class="action-icon btn btn-sm text-danger fs-5 btndel">Remove</button></td>
      </tr>
  `;
  let newRow = body.insertRow(body.rows.length);
  newRow.innerHTML = html;
  $('#groupcollectionSubModalCenter').modal('toggle');
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

clearOnChange(mandatoryFields);
