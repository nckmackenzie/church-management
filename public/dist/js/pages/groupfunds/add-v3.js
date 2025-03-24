import {
  validation,
  clearOnChange,
  HOST_URL,
  sendHttpRequest,
} from '../utils/utils.js';
const form = document.querySelector('form');
const groupdistrictSelect = document.getElementById('type');
const groupSelect = document.getElementById('group');
const mandatoryField = document.querySelectorAll('.mandatory');
const date = document.getElementById('date');
const amountAvailable = document.getElementById('availableamount');
const dontDeductChkbox = document.getElementById('dontdeduct');

groupdistrictSelect.addEventListener('change', async function (e) {
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

  if (e.target.value === 'group') {
    dontDeductChkbox.disabled = false;
    dontDeductChkbox.checked = false;
  } else {
    dontDeductChkbox.disabled = true;
    dontDeductChkbox.checked = true;
  }
});

//form submit
form.addEventListener('submit', function (e) {
  e.preventDefault();

  if (validation() > 0) return;
  if (!otherValidation()) return;

  form.submit();
});

//validate fields
function otherValidation() {
  const amount = document.getElementById('amount');

  if (new Date(date.value).getTime() > new Date().getTime()) {
    date.classList.add('is-invalid');
    date.nextSibling.nextSibling = 'Invalid date selected';
    return;
  }

  if (
    parseFloat(amount.value) > parseFloat(amountAvailable.value) &&
    !dontDeductChkbox.checked
  ) {
    amount.classList.add('is-invalid');
    amount.nextSibling.nextSibling.textContent =
      'Requesting more than available';
    return false;
  }
  return true;
}

//clear error state on change
clearOnChange(mandatoryField);

//fetch amount available
groupSelect.addEventListener('change', getvalue);
date.addEventListener('change', getvalue);

async function getvalue() {
  if (
    date.value == '' ||
    groupSelect.value == '' ||
    groupdistrictSelect.value == ''
  )
    return;
  const data = await sendHttpRequest(
    `${HOST_URL}/groupfunds/getamountavailable?group=${groupSelect.value}&date=${date.value}&type=${groupdistrictSelect.value}`
  );
  amountAvailable.value = data;
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
