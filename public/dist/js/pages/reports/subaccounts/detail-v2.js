// The rows behind one sub account balance, task 9.8 (group and district alike). The page URL carries subaccount and asdate.
import { show } from '../is/report-ui-v1.js';
import { showList } from '../is/list-report-v1.js';

const app = document.getElementById('subdetail');
const alertBox = document.getElementById('report-error');

(async function load() {
  const page = new URLSearchParams(window.location.search);
  const params = new URLSearchParams({ account: page.get('subaccount') || '', asdate: page.get('asdate') || '' });
  try {
    await showList(document.getElementById('results'), app.dataset.endpoint, params, 'Search date, narration, reference or amount');
  } catch (e) {
    alertBox.hidden = false;
    show(alertBox, e.message);
  }
})();
