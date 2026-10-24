// Ledger Statement, task 9.3: one account (optionally with every sub account) between two dates, from the journal.
import { el, show, ddmmyyyy, getJson, query, printButton, linesPanel } from './report-ui-v1.js';

const base = document.getElementById('isapp').dataset.base;
const alertBox = document.getElementById('isalert');
const say = (m, k) => show(alertBox, m, k);

async function preview() {
  const account = document.getElementById('account').value;
  const from = document.getElementById('sdate').value;
  const to = document.getElementById('edate').value;
  const subledgers = document.getElementById('subledgers').checked ? '1' : '0';
  if (!account) return say('Please select the account');
  if (!from || !to) return say('Please select the start and end dates');
  if (from > to) return say('Start date cannot be after the end date');
  say('');
  const results = document.getElementById('results');
  results.replaceChildren();
  const params = { account, from, to, subledgers };
  try {
    const first = await getJson(base + '/ledgerstatementrpt?' + query(params));
    const wrap = el('div', null, 'is-print-area');
    wrap.id = 'printarea';
    wrap.appendChild(el('h5', first.entity, 'mb-0'));
    wrap.appendChild(el('div', 'Ledger statement: ' + first.name, 'mb-0'));
    wrap.appendChild(el('div', first.path, 'small'));
    wrap.appendChild(el('div', ddmmyyyy(first.from) + ' to ' + ddmmyyyy(first.to) + ' - ' + first.directionNote, 'mb-2'));
    results.appendChild(printButton());
    results.appendChild(wrap);
    linesPanel(wrap, {
      withAccount: first.subledgers,
      openingLabel: 'Opening balance (before ' + ddmmyyyy(first.from) + ')',
      first,
      load: (p, s, q) => getJson(base + '/ledgerstatementrpt?' + query(Object.assign({}, params, { page: p, pageSize: s, search: q }))),
      excel: (q) => base + '/ledgerstatementrpt?' + query(Object.assign({}, params, { format: 'xlsx', search: q })),
    });
  } catch (e) {
    say(e.message);
  }
}

document.querySelector('.preview').addEventListener('click', preview);
