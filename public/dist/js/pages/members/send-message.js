import {
  displayAlert,
  alertBox,
  sendHttpRequest,
  HOST_URL,
  setLoadingState,
  resetLoadingState,
} from '../utils/utils.js';
const membersSelect = document.querySelector('#members');
const messageTextArea = document.querySelector('#message');
const form = document.querySelector('#send-form');
const saveBtn = document.querySelector('.save');

$(function () {
  $('#members').multiselect({
    includeSelectAllOption: true,
    buttonWidth: '100%',
    maxHeight: 200,
  });
});

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
    displayAlert(alertBox, res.message, 'success');
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
  $('#members option').each(function () {
    allOptions.push($(this).val());
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
