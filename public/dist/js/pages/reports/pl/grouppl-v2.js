// Group / district income statement, task 9.7 (decision 155): amounts come from the journal; every figure opens its transactions.
// All server strings are written with textContent.
import { el, show, ddmmyyyy, getJson, amountCell, printButton, excelLink } from '../is/report-ui-v1.js';

const app = document.getElementById('gpl');
const base = app.dataset.base;
const results = document.getElementById('results');
const alertBox = document.getElementById('report-error');
const say = message => { alertBox.hidden = !message; show(alertBox, message); };

function entityParams(value) {
  const [kind, id] = value.split(':');
  return kind === 'district' ? { district: id } : { group: id };
}

function q(obj) {
  const p = new URLSearchParams();
  Object.keys(obj).forEach(k => { if (obj[k] !== undefined && obj[k] !== '') p.set(k, obj[k]); });
  return p.toString();
}

function render(d, params) {
  results.replaceChildren();
  const bar = el('div', null, 'mb-2 no-print');
  bar.appendChild(printButton());
  bar.appendChild(excelLink(base + '/groupincomestatementrpt?' + q(Object.assign({ format: 'xlsx' }, params))));
  const area = el('div', null, 'is-print-area');
  area.appendChild(el('div', 'Income Statement', 'font-weight-bold'));
  area.appendChild(el('div', d.name, 'mb-0'));
  area.appendChild(el('div', ddmmyyyy(d.start) + ' to ' + ddmmyyyy(d.end), 'mb-2'));
  const table = el('table', null, 'table table-striped table-bordered table-sm');
  table.id = 'table';
  const body = el('tbody');
  const section = (title, cls) => { const tr = el('tr', null, cls); tr.appendChild(el('td', title, '', { colspan: '2' })); body.appendChild(tr); };
  const line = (label, amount, href) => {
    const tr = el('tr');
    tr.appendChild(el('td', label));
    tr.appendChild(amountCell(amount, href));
    body.appendChild(tr);
  };
  const total = (label, amount, style) => {
    const tr = el('tr', null, style || '');
    tr.appendChild(el('th', label));
    tr.appendChild(el('th', amount, 'text-right'));
    body.appendChild(tr);
  };
  section('Income', 'bg-olive');
  d.revenue.rows.forEach(r => line(r.label, r.amount, base + '/groupplrevenuedetailed?' + q(Object.assign({ type: r.key, start: d.start, end: d.end }, params))));
  total('Revenue Total', d.revenue.total);
  section('Expenses', 'bg-danger');
  d.expenses.rows.forEach(r => line(r.label, r.amount, base + '/groupplexpensedetailed?' + q(Object.assign({ account: r.account, start: d.start, end: d.end }, params))));
  total('Expense Total', d.expenses.total);
  total('Profit/Loss', d.result.amount, 'table-active');
  table.appendChild(body);
  area.appendChild(el('div', null, 'table-responsive')).appendChild(table);
  results.append(bar, area);
}

async function preview() {
  const entity = document.getElementById('group').value;
  const start = document.getElementById('start').value;
  const end = document.getElementById('end').value;
  if (!entity) return say('Select group');
  if (!start || !end) return say('Select the Start Date and the End Date');
  if (start > end) return say('Start Date Cannot Be Greater Than End Date');
  say('');
  const params = Object.assign(entityParams(entity), { start, end });
  try {
    render(await getJson(base + '/groupincomestatementrpt?' + q(params)), params);
  } catch (e) {
    results.replaceChildren();
    say(e.message);
  }
}

document.getElementById('preview').addEventListener('click', preview);
