// Sub account report, task 9.8: balances of every sub account (As of) or the statement of one. All server text via textContent.
import { show } from '../is/report-ui-v1.js';
import { showList } from '../is/list-report-v1.js';

const app = document.getElementById('subapp');
const results = document.getElementById('results');
const alertBox = document.getElementById('report-error');
const say = message => { alertBox.hidden = !message; show(alertBox, message); };
const select = document.getElementById('subaccounts');
const to = document.getElementById('to');

select.addEventListener('change', () => {
  const all = select.value === 'all';
  document.getElementById('fromLabel').textContent = all ? 'As of' : 'From';
  to.disabled = all;
});

async function preview() {
  const account = select.value;
  const from = document.getElementById('from').value;
  const end = to.value;
  if (!account) return say('Select Sub account');
  if (!from) return say(account === 'all' ? 'Select the As of date' : 'Select Start Date');
  if (account !== 'all' && !end) return say('Select End Date');
  if (account !== 'all' && from > end) return say('Start Date Cannot Be Greater Than End Date');
  say('');
  const params = new URLSearchParams({ account, from });
  if (account !== 'all') params.set('to', end);
  results.replaceChildren();
  try {
    await showList(results, app.dataset.endpoint, params, 'Search date, reference, description or amount');
  } catch (e) {
    say(e.message);
  }
}

document.getElementById('preview').addEventListener('click', preview);
