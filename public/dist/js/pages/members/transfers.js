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
