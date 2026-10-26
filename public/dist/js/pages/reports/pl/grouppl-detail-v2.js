// Transactions behind one figure of the group / district income statement, task 9.7. The page URL carries the same parameters
// as the data endpoint (type or account, group or district, start, end).
import { el, show } from '../is/report-ui-v1.js';
import { showList } from '../is/list-report-v1.js';

const app = document.getElementById('gpldetail');
const area = document.getElementById('results');
const alertBox = document.getElementById('report-error');

(async function load() {
  try {
    await showList(area, app.dataset.endpoint, new URLSearchParams(window.location.search), 'Search date, source no, narration or amount');
  } catch (e) {
    alertBox.hidden = false;
    show(alertBox, e.message);
  }
})();
