// Income statement (congregation and parish) and its drill-down, tasks 9.2 / 9.3.
// Every server string is put on the page with textContent; the URLs carry account IDs only.
import { el, show, ddmmyyyy, getJson, query, amountCell, printButton, excelLink, childrenPanel, linesPanel } from './report-ui-v1.js';

const app = document.getElementById('isapp');
const base = app.dataset.base; // .../reports or .../parishreports
const page = app.dataset.mode; // 'index' | 'drill'
const alertBox = document.getElementById('isalert');
const say = (m, k) => show(alertBox, m, k);

function drillUrl(ctx, row) {
  const p = { account: row.id, start: ctx.start, end: ctx.end, cong: ctx.cong };
  if (row.direct) p.direct = '1';
  else if (row.next === 'lines') p.view = 'lines';
  return base + '/plchildaccount?' + query(p);
}

// ---------------------------------------------------------------- report
function renderReport(d, ctx) {
  const area = document.getElementById('results');
  area.replaceChildren();
  const wrap = el('div', null, 'is-print-area');
  wrap.id = 'printarea';
  wrap.appendChild(el('h5', d.entity, 'mb-0'));
  wrap.appendChild(el('div', 'Income statement for the period ' + ddmmyyyy(d.start) + ' to ' + ddmmyyyy(d.end), 'mb-2'));
  if (d.unclassified.present) {
    const w = el('div', null, 'alert alert-warning');
    w.appendChild(el('div', d.unclassified.message));
    const ul = el('ul', null, 'mb-0');
    d.unclassified.rows.forEach(r => ul.appendChild(el('li', r.name + ' (account ' + r.id + '): ' + r.amount)));
    w.appendChild(ul);
    wrap.appendChild(w);
  }
  const table = el('table', null, 'table table-sm table-bordered');
  table.id = 'table';
  const thead = el('thead', null, 'bg-lightblue');
  const hr = el('tr');
  hr.appendChild(el('th', 'Account'));
  hr.appendChild(el('th', ddmmyyyy(d.start) + ' - ' + ddmmyyyy(d.end), 'text-right'));
  hr.appendChild(el('th', d.cmpStart ? ddmmyyyy(d.cmpStart) + ' - ' + ddmmyyyy(d.cmpEnd) + ' (last year)' : 'Last year', 'text-right'));
  thead.appendChild(hr);
  table.appendChild(thead);
  const tb = el('tbody');
  const header = (t) => { const tr = el('tr', null, 'bg-light'); tr.appendChild(el('th', t, '', { colspan: '3' })); tb.appendChild(tr); };
  const total = (label, a, c, strong) => {
    const tr = el('tr', null, strong ? 'font-weight-bold table-active' : 'font-weight-bold');
    tr.appendChild(el('td', label));
    tr.appendChild(amountCell(a)); tr.appendChild(amountCell(c));
    tb.appendChild(tr);
  };
  const section = (s) => {
    header(s.title);
    s.rows.forEach(r => {
      const tr = el('tr');
      tr.appendChild(el('td', r.name, 'pl-4'));
      const href = drillUrl(ctx, r);
      tr.appendChild(amountCell(r.amount, href)); tr.appendChild(amountCell(r.cmp, r.cmp === null ? null : href));
      tb.appendChild(tr);
    });
    total('Total ' + s.title.toLowerCase(), s.total, s.cmpTotal);
  };
  section(d.income);
  section(d.expense);
  total('Surplus / (deficit) for the period', d.surplus.amount, d.surplus.cmp, true);
  if (d.dividends) {
    section(d.dividends);
    total('Surplus / (deficit) after dividends', d.final.amount, d.final.cmp, true);
  }
  table.appendChild(tb);
  const tw = el('div', null, 'table-responsive');
  tw.appendChild(table);
  wrap.appendChild(tw);
  area.appendChild(printButton());
  area.appendChild(excelLink(base + '/incomestatementrpt?' + query({ start: ctx.start, end: ctx.end, cong: ctx.cong, format: 'xlsx' })));
  area.appendChild(wrap);
}

async function preview() {
  const start = document.getElementById('start').value;
  const end = document.getElementById('end').value;
  const congSel = document.getElementById('cong');
  const cong = congSel && congSel.value !== '0' ? congSel.value : '';
  if (!start || !end) return say('Select the start and end dates');
  if (start > end) return say('Start date cannot be after the end date');
  say('');
  try {
    const d = await getJson(base + '/incomestatementrpt?' + query({ start, end, cong }));
    renderReport(d, { start, end, cong });
  } catch (e) {
    document.getElementById('results').replaceChildren();
    say(e.message);
  }
}

// ---------------------------------------------------------------- drill
function renderDrill(d, ctx, account) {
  const area = document.getElementById('results');
  area.replaceChildren();
  const wrap = el('div', null, 'is-print-area');
  wrap.id = 'printarea';
  const nav = el('nav', null, 'mb-2');
  nav.appendChild(el('a', 'Income statement', '', { href: base + '/incomestatement?' + query({ start: ctx.start, end: ctx.end, cong: ctx.cong }) }));
  d.crumbs.forEach((c, i) => {
    nav.appendChild(document.createTextNode(' > '));
    const last = i === d.crumbs.length - 1 && d.mode === 'children';
    if (last) nav.appendChild(el('span', c.name));
    else nav.appendChild(el('a', c.name, '', { href: base + '/plchildaccount?' + query({ account: c.id, start: ctx.start, end: ctx.end, cong: ctx.cong }) }));
  });
  if (d.mode === 'lines') { nav.appendChild(document.createTextNode(' > ')); nav.appendChild(el('span', d.direct ? 'Postings on this account' : 'Journal lines')); }
  wrap.appendChild(nav);
  wrap.appendChild(el('div', 'Period ' + ddmmyyyy(d.start) + ' to ' + ddmmyyyy(d.end), 'mb-2'));
  const xlParams = { account, start: ctx.start, end: ctx.end, cong: ctx.cong, direct: ctx.direct ? '1' : '', view: ctx.view };
  area.appendChild(printButton());
  if (d.mode === 'children') {
    area.appendChild(excelLink(base + '/plchildaccountrpt?' + query(Object.assign({}, xlParams, { format: 'xlsx' }))));
    wrap.appendChild(el('h5', d.name + ': ' + d.amount + (d.cmp !== null ? '  (last year ' + d.cmp + ')' : '')));
    const rows = d.rows.map(r => {
      const href = drillUrl(ctx, r);
      return { text: r.name, cells: [el('td', r.name), amountCell(r.amount, href), amountCell(r.cmp, r.cmp === null ? null : href)] };
    });
    const footer = [el('td', 'Total of the rows (' + (d.sumsExactly ? 'adds up to' : 'DOES NOT add up to') + ' ' + d.amount + ')'), amountCell(d.rowsTotal), amountCell('')];
    childrenPanel(wrap, ['Account', 'Amount', 'Last year'], rows, footer);
  } else {
    wrap.appendChild(el('h5', d.name + ': movement ' + d.movement));
    linesPanel(wrap, {
      withAccount: true,
      openingLabel: 'Opening balance (before ' + ddmmyyyy(d.start) + ')',
      first: d,
      load: (p, s, q) => getJson(base + '/plchildaccountrpt?' + query(Object.assign({}, xlParams, { page: p, pageSize: s, search: q }))),
      excel: (q) => base + '/plchildaccountrpt?' + query(Object.assign({}, xlParams, { format: 'xlsx', search: q })),
    });
  }
  area.appendChild(wrap);
}

async function loadDrill() {
  const p = new URLSearchParams(window.location.search);
  const ctx = { start: p.get('start') || '', end: p.get('end') || '', cong: p.get('cong') || '', direct: p.get('direct') === '1', view: p.get('view') || '' };
  const account = p.get('account') || '';
  try {
    const d = await getJson(base + '/plchildaccountrpt?' + query({ account, start: ctx.start, end: ctx.end, cong: ctx.cong, direct: ctx.direct ? '1' : '', view: ctx.view }));
    renderDrill(d, ctx, account);
  } catch (e) {
    say(e.message);
  }
}

if (page === 'drill') {
  loadDrill();
} else {
  document.getElementById('preview').addEventListener('click', preview);
  const p = new URLSearchParams(window.location.search);
  if (p.get('start') && p.get('end')) {
    document.getElementById('start').value = p.get('start');
    document.getElementById('end').value = p.get('end');
    const c = document.getElementById('cong');
    if (c && p.get('cong')) c.value = p.get('cong');
    preview();
  }
}
