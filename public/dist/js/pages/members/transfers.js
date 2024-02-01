function activateMultiSelect() {
  $(function () {
    $('#member').multiselect({
      includeSelectAllOption: true,
      buttonWidth: '100%',
    });
  });
}

import { getDistricts, getMembers } from './ajax.js';
const currCongSelect = document.getElementById('congregationfrom');
const currDistrictSelect = document.getElementById('district');
const memberSelect = document.getElementById('member');
const newCongSelect = document.getElementById('newcongregation');
const newDistrictSelect = document.getElementById('newdistrict');
const form = document.getElementById('transferForm');
const dateInput = document.getElementById('date');
const controls = document.querySelectorAll('.form-control');

currCongSelect.addEventListener('change', async function (e) {
  if (!e.target.value || e.target.value === '') return;
  currDistrictSelect.innerHTML = await getDistricts(e.target.value);
});

newCongSelect.addEventListener('change', async function (e) {
  if (!e.target.value || e.target.value === '') return;
  newDistrictSelect.innerHTML = await getDistricts(e.target.value);
});

currDistrictSelect.addEventListener('change', async function (e) {
  $('#member').multiselect('destroy');
  memberSelect.multiple = true;
  memberSelect.innerHTML = '';
  if (!e.target.value || e.target.value === '') return;
  memberSelect.innerHTML = await getMembers(e.target.value);

  activateMultiSelect();
});

controls.forEach(control => {
  control.addEventListener('change', function () {
    control.classList.remove('is-invalid');
    control.nextSibling.nextSibling.textContent = '';
  });
});

function validate() {
  let errorCount = 0;

  controls.forEach(contrl => {
    if (contrl.value === '') {
      contrl.classList.add('is-invalid');
      contrl.nextSibling.nextSibling.textContent = 'This field is required';
      errorCount++;
    }
  });

  if (+newDistrictSelect.value === +currDistrictSelect.value) {
    newCongSelect.classList.add('is-invalid');
    newCongSelect.nextSibling.nextSibling.textContent =
      'Old and new districts cannot be same';
    errorCount++;
  }

  if (new Date(dateInput.value).getTime() > new Date().getTime()) {
    dateInput.classList.add('is-invalid');
    dateInput.nextSibling.nextSibling.textContent = 'Invalid transfer date';
  }

  if (errorCount > 0) return false;
  return true;
}

form.addEventListener('submit', function (e) {
  e.preventDefault();
  if (!validate()) return;
  document.transferForm.submit();
});
