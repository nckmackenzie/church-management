import {
  displayAlert,
  alertBox,
  sendHttpRequest,
  HOST_URL,
  setLoadingState,
  resetLoadingState,
} from '../utils/utils.js';

const messageTextArea = document.querySelector('#message');
const filterSelect = document.querySelector('#filter');
const paramLabel = document.querySelector('#label');
const districtSelect = document.querySelector('#district');
const memberSelect = document.querySelector('#members');
const form = document.querySelector('#send-form');
const saveBtn = document.querySelector('.save');

function activateMultiSelect() {
  $(function () {
    $('#members').multiselect({
      includeSelectAllOption: true,
      buttonWidth: '100%',
      maxHeight: 200,
    });
  });
}

filterSelect.addEventListener('change', async function (e) {
  $('#members').multiselect('destroy');
  memberSelect.innerHTML = '';
  if (e.target.value === 'all') {
    districtSelect.innerHTML = '';
    districtSelect.disabled = true;
    districtSelect.value = '';
    const data = await getmembers('all', null);
    loadMembers(data);
    return;
  }
  districtSelect.disabled = false;
  const data = await getdistrictorgroup(e.target.value);
  paramLabel.textContent = 'Select ' + e.target.value;
  districtSelect.innerHTML = '';
  districtSelect.innerHTML = '<option>Select ' + e.target.value + '</option>';
  data.length &&
    data.forEach(dt => {
      let html = '';
      html += `
        <option value="${dt.id}" >${dt.label}</option>
    `;
      districtSelect.insertAdjacentHTML('beforeend', html);
    });
});

districtSelect.addEventListener('change', async function (e) {
  if (!e.target.value || !String(e.target.value).trim().length) return;
  $('#members').multiselect('destroy');
  memberSelect.multiple = true;
  const type = filterSelect.value;
  const data = await getmembers(type, e.target.value);
  loadMembers(data);
});

function loadMembers(data) {
  memberSelect.innerHTML = '';
  data.data.length &&
    data.data.forEach(dt => {
      let html = '';
      html += `
        <option value="${dt.id}" >${dt.label}</option>
    `;

      memberSelect.insertAdjacentHTML('beforeend', html);
    });
  activateMultiSelect();
}

//form submit
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  if (!validation()) {
    displayAlert(alertBox, 'Provide all required fields');
    return;
  }

  setLoadingState(saveBtn, 'Sending...');
  const res = await submitForm();
  resetLoadingState(saveBtn, 'Send');

  if (res && res.success) {
    const {
      data: { SMSMessageData },
    } = res.result;
    displayAlert(alertBox, SMSMessageData.Message, 'success');
    reset();
  }
});

function validation() {
  let errorCount = 0;
  const required = document.querySelectorAll('.required');
  required.forEach(field => {
    if (field.value === '') {
      errorCount++;
    }
  });

  if (errorCount > 0) return false;
  return true;
}

function getFormData() {
  const allSelectedOptions = new Array();
  $('#members option:selected').each(function () {
    allSelectedOptions.push($(this).val());
  });
  return {
    members: allSelectedOptions,
    message: messageTextArea.value || '',
  };
}

function reset() {
  $('option', $('#members')).each(function (element) {
    $(this).removeAttr('selected').prop('selected', false);
  });
  $('#members').multiselect('refresh');
  messageTextArea.value = '';
}

async function submitForm() {
  const url = `${HOST_URL}/members/sendmessageaction`;
  const data = getFormData();
  const res = await sendHttpRequest(
    url,
    'POST',
    JSON.stringify(data),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  return res;
}

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

async function getmembers(type, value) {
  const data = await sendHttpRequest(
    `${HOST_URL}/members/getmembers?type=${type}&value=${value}`,
    'GET',
    undefined,
    {},
    alertBox
  );
  return data;
}
activateMultiSelect();
