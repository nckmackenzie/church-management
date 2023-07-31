import { getRequisitions } from '../ajax.js';

const groupSelect = document.getElementById('group');
const requisitionSelect = document.getElementById('requisition');

groupSelect.addEventListener('change', async function (e) {
  requisitionSelect.innerHTML = '';
  requisitionSelect.innerHTML =
    '<option value="" selected disabled>Select requisition</option>';
  const data = await getRequisitions(e.target.value);
  data.length &&
    data.forEach(req => {
      let html = '';
      html += `
        <option value="${req.id}" >${req.label}</option>
    `;
      requisitionSelect.insertAdjacentHTML('beforeend', html);
    });
});
