// Balance sheet (congregation and parish) and its drill-down, task 9.4 (decisions 141-142, 144, 147-149).
// As Of Date only. Every server string goes on the page with textContent; URLs carry account IDs only.
import { el, show, ddmmyyyy, getJson, query, amountCell, printButton, excelLink, childrenPanel, linesPanel } from './report-ui-v1.js';

const app = document.getElementById('isapp');
const d0 = app.dataset;
const base = d0.base;                       // .../reports or .../parishreports
const alertBox = document.getElementById('isalert');
const say = (m, k) => show(alertBox, m, k);
const topUrl = (ctx) => base + '/balancesheet?' + query({ asof: ctx.asof, cong: ctx.cong });

function drillUrl(ctx, row, fund) {
  const p = { account: row.id, asof: ctx.asof, cong: ctx.cong, fund: fund || '' };
  if (row.direct) p.direct = '1';
  else if (row.next === 'lines') p.view = 'lines';
  return base + '/balancesheetdrill?' + query(p);
}

// ---------------------------------------------------------------- report
function renderReport(d, ctx) {
  const area = document.getElementById('results');
  area.replaceChildren();
  const wrap = el('div', null, 'is-print-area');
  wrap.id = 'printarea';
  if (d.entity) wrap.appendChild(el('h5', d.entity, 'mb-0'));   // parish scope line only (decision 147)
  wrap.appendChild(el('div', 'Balance sheet as at ' + ddmmyyyy(d.asOf), 'mb-2'));
  if (d.cmpError) wrap.appendChild(el('div', d.cmpError, 'alert alert-info'));
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
  hr.appendChild(el('th', ddmmyyyy(d.asOf), 'text-right'));
  hr.appendChild(el('th', d.cmpAsOf ? ddmmyyyy(d.cmpAsOf) + ' (last year-end)' : 'Last year-end', 'text-right'));
  thead.appendChild(hr);
  table.appendChild(thead);
  const tb = el('tbody');
  const header = (t) => { const tr = el('tr', null, 'bg-light'); tr.appendChild(el('th', t, '', { colspan: '3' })); tb.appendChild(tr); };
  const total = (label, a, c, cls) => {
    const tr = el('tr', null, cls || 'font-weight-bold');
    tr.appendChild(el('td', label));
    tr.appendChild(amountCell(a)); tr.appendChild(amountCell(c));
    tb.appendChild(tr);
  };
  const section = (title, s, totalLabel) => {
    header(title);
    s.rows.forEach(r => {
      const tr = el('tr');
      tr.appendChild(el('td', r.name, 'pl-4'));
      let href;
      if (r.fund && r.next === 'incomestatement') href = d0.isurl + '?' + query({ start: d.yearStart, end: d.asOf, cong: ctx.cong });
      else href = drillUrl(ctx, r, '');
      tr.appendChild(amountCell(r.amount, href)); tr.appendChild(amountCell(r.cmp, r.cmp === null ? null : href));
      tb.appendChild(tr);
    });
    total(totalLabel, s.total, s.cmpTotal);
  };
  section('Assets', d.assets, 'Total assets');
  section('Liabilities', d.liabilities, 'Total liabilities');
  section('Equity and accumulated fund', d.equity, 'Total equity and accumulated fund');
  total('Total liabilities, equity and accumulated fund', d.liabilitiesEquityFund.total, d.liabilitiesEquityFund.cmpTotal, 'font-weight-bold table-active');
  const chk = el('tr');
  if (d.balanced) {
    chk.appendChild(el('td', 'Total assets equal total liabilities, equity and accumulated fund', 'text-success font-weight-bold', { colspan: '3' }));
  } else {
    chk.appendChild(el('td', 'DIFFERENCE (assets - liabilities - equity - fund): ' + d.difference.amount + (d.difference.cmp !== null ? ' (last year-end ' + d.difference.cmp + ')' : ''), 'text-danger font-weight-bold', { colspan: '3' }));
  }
  tb.appendChild(chk);
  table.appendChild(tb);
  const tw = el('div', null, 'table-responsive');
  tw.appendChild(table);
  wrap.appendChild(tw);
  area.appendChild(printButton());
  area.appendChild(excelLink(base + '/balancesheetrpt?' + query({ asof: ctx.asof, cong: ctx.cong, format: 'xlsx' })));
  area.appendChild(wrap);
}

async function preview() {
  const asof = document.getElementById('asof').value;
  const congSel = document.getElementById('cong');
  const cong = congSel && congSel.value !== '0' ? congSel.value : '';
  if (!asof) return say('Select the as-of date');
  say('');
  try {
    const d = await getJson(base + '/balancesheetrpt?' + query({ asof, cong }));
    renderReport(d, { asof, cong });
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
  const fund = d.fund || '';
  const nav = el('nav', null, 'mb-2');
  nav.appendChild(el('a', 'Balance sheet', '', { href: topUrl(ctx) }));
  const fundName = fund === 'bf' ? 'Accumulated fund b/f' : (fund === 'div' ? 'Dividends' : '');
  if (fund && account !== fund) {
    nav.appendChild(document.createTextNode(' > '));
    nav.appendChild(el('a', fundName, '', { href: base + '/balancesheetdrill?' + query({ account: fund, asof: ctx.asof, cong: ctx.cong }) }));
  }
  if (account === 'bf' || account === 'div') { nav.appendChild(document.createTextNode(' > ')); nav.appendChild(el('span', fundName)); }
  (d.crumbs || []).forEach((c, i) => {
    nav.appendChild(document.createTextNode(' > '));
    const last = i === d.crumbs.length - 1 && d.mode === 'children';
    if (last) nav.appendChild(el('span', c.name));
    else nav.appendChild(el('a', c.name, '', { href: base + '/balancesheetdrill?' + query({ account: c.id, asof: ctx.asof, cong: ctx.cong, fund }) }));
  });
  if (d.mode === 'lines') { nav.appendChild(document.createTextNode(' > ')); nav.appendChild(el('span', d.direct ? 'Postings on this account' : 'Journal lines')); }
  wrap.appendChild(nav);
  wrap.appendChild(el('div', 'As at ' + ddmmyyyy(d.asOf) + (fund === 'bf' ? ' - result of the financial years before the current one (credit = surplus)' : ''), 'mb-2'));
  const xlParams = { account, asof: ctx.asof, cong: ctx.cong, fund, direct: ctx.direct ? '1' : '', view: ctx.view };
  area.appendChild(printButton());
  if (d.mode === 'children') {
    area.appendChild(excelLink(base + '/balancesheetdrillrpt?' + query(Object.assign({}, xlParams, { format: 'xlsx' }))));
    wrap.appendChild(el('h5', d.name + ': ' + d.amount + (d.cmp ? '  (last year-end ' + d.cmp + ')' : '')));
    const rows = d.rows.map(r => {
      const href = drillUrl(ctx, r, fund);
      return { text: r.name, cells: [el('td', r.name), amountCell(r.amount, href), amountCell(r.cmp, r.cmp === null ? null : href)] };
    });
    const footer = [el('td', 'Total of the rows (' + (d.sumsExactly ? 'adds up to the figure above' : 'DOES NOT add up') + ')'), amountCell(d.rowsTotal), amountCell('')];
    childrenPanel(wrap, ['Account', 'As at ' + ddmmyyyy(d.asOf), 'Last year-end'], rows, footer);
  } else {
    wrap.appendChild(el('h5', d.name + ': closing balance ' + d.closing + ' (' + d.direction + ')'));
    linesPanel(wrap, {
      withAccount: true,
      openingLabel: 'Opening balance',
      first: d,
      load: (p, s, q) => getJson(base + '/balancesheetdrillrpt?' + query(Object.assign({}, xlParams, { page: p, pageSize: s, search: q }))),
      excel: (q) => base + '/balancesheetdrillrpt?' + query(Object.assign({}, xlParams, { format: 'xlsx', search: q })),
    });
  }
  area.appendChild(wrap);
}

async function loadDrill() {
  const p = new URLSearchParams(window.location.search);
  const ctx = { asof: p.get('asof') || '', cong: p.get('cong') || '', direct: p.get('direct') === '1', view: p.get('view') || '' };
  const account = p.get('account') || '';
  const fund = p.get('fund') || '';
  try {
    const d = await getJson(base + '/balancesheetdrillrpt?' + query({ account, asof: ctx.asof, cong: ctx.cong, fund, direct: ctx.direct ? '1' : '', view: ctx.view }));
    renderDrill(d, ctx, account);
  } catch (e) {
    say(e.message);
  }
}

if (d0.mode === 'drill') {
  loadDrill();
} else {
  document.getElementById('preview').addEventListener('click', preview);
  const p = new URLSearchParams(window.location.search);
  if (p.get('asof')) {
    document.getElementById('asof').value = p.get('asof');
    const c = document.getElementById('cong');
    if (c && p.get('cong')) c.value = p.get('cong');
    preview();
  }
}
