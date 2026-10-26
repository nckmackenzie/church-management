// Contribution / expense report screens (congregation and parish), task 9.7: validate the filters, ask the server for the list as JSON
// and draw it with textContent. The page is the <div id="listapp"> in the view:
//   data-endpoint    URL of the report data endpoint
//   data-account-key request key of the account list (account / accounts)
//   data-cong-key    request key of the congregation list (congregations), parish screens only
//   data-need-account  "always" (parish) or "type2" (congregation: only for the By Account report type)
import { el, show } from './is/report-ui-v1.js';
import { showList } from './is/list-report-v1.js';

const app = document.getElementById('listapp');
const endpoint = app.dataset.endpoint;
const accountKey = app.dataset.accountKey;
const congKey = app.dataset.congKey || '';
const needAccount = app.dataset.needAccount || 'type2';
const results = document.getElementById('results');

const selected = id => {
  const s = document.getElementById(id);
  return s ? Array.from(s.selectedOptions).map(o => o.value) : [];
};
const feedback = (id, message) => {
  const field = document.getElementById(id);
  const note = document.getElementById(id + '_err');
  if (field) field.classList.toggle('is-invalid', !!message);
  if (note) note.textContent = message || '';
};

async function preview() {
  const start = document.getElementById('start').value;
  const end = document.getElementById('end').value;
  const typeSelect = document.getElementById('type');
  const type = typeSelect ? typeSelect.value : '';
  const accounts = selected('account');
  const congregations = congKey ? selected('congregation') : [];
  let bad = false;
  feedback('start', start ? '' : 'Select Start Date'); bad = bad || !start;
  feedback('end', end ? '' : 'Select End Date'); bad = bad || !end;
  if (start && end && start > end) { feedback('start', 'Start Date Cannot Be Greater Than End Date'); bad = true; }
  const accountNeeded = needAccount === 'always' || type === '2';
  feedback('account', accountNeeded && !accounts.length ? 'Select at least one account' : ''); bad = bad || (accountNeeded && !accounts.length);
  if (congKey) { feedback('congregation', congregations.length ? '' : 'Select at least one congregation'); bad = bad || !congregations.length; }
  if (bad) return;

  const params = new URLSearchParams();
  if (typeSelect) params.set('type', type);
  params.set('start', start);
  params.set('end', end);
  if (accountNeeded) accounts.forEach(a => params.append(accountKey + '[]', a));
  congregations.forEach(c => params.append(congKey + '[]', c));

  results.replaceChildren();
  const area = el('div', null, 'is-print-area');
  results.appendChild(area);
  try {
    await showList(area, endpoint, params, 'Search date, receipt or voucher, account, name, reference or amount');
  } catch (e) {
    show(results, e.message);
  }
}

document.getElementById('preview').addEventListener('click', preview);
