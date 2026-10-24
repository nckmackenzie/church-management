// Trial balance (congregation and parish) and its drill-down, task 9.3 (decisions 129-131, 141-142).
// As Of Date only. Every server string goes on the page with textContent; URLs carry account IDs only.
import { el, show, ddmmyyyy, getJson, query, amountCell, printButton, excelLink, childrenPanel, linesPanel } from './report-ui-v1.js';

const app = document.getElementById('isapp');
const d0 = app.dataset;
const base = d0.base;                     // e.g. .../trialbalance or .../parishreports
const endpoint = (name) => base + '/' + name;
const topUrl = (ctx) => (d0.top ? base + '/' + d0.top : base) + '?' + query({ asof: ctx.asof, cong: ctx.cong });
const alertBox = document.getElementById('isalert');
const say = (m, k) => show(alertBox, m, k);

function drillUrl(ctx, row, bf) {
  const p = { account: row.id, asof: ctx.asof, cong: ctx.cong, bf: bf ? '1' : '' };
  if (row.direct) p.direct = '1';
  else if (row.next === 'lines') p.view = 'lines';
  return endpoint(d0.drillpage) + '?' + query(p);
}

// ---------------------------------------------------------------- report
function renderReport(d, ctx) {
  const area = document.getElementById('results');
  area.replaceChildren();
  const wrap = el('div', null, 'is-print-area');
  wrap.id = 'printarea';
  if (d.entity) wrap.appendChild(el('h5', d.entity, 'mb-0'));   // parish scope line only (decision 147)
  wrap.appendChild(el('div', (d.detailed ? 'Detailed trial balance' : 'Trial balance') + ' as at ' + ddmmyyyy(d.asOf), 'mb-0'));
  if (d.unclassified.present) {
    const w = el('div', null, 'alert alert-warning');
    w.appendChild(el('div', d.unclassified.message));
    wrap.appendChild(w);
  }
  const table = el('table', null, 'table table-sm table-bordered');
  table.id = 'table';
  const thead = el('thead', null, 'bg-lightblue');
  const hr = el('tr');
  ['Account', 'Debit', 'Credit'].forEach((t, i) => hr.appendChild(el('th', t, i ? 'text-right' : '')));
  thead.appendChild(hr);
  table.appendChild(thead);
  const tb = el('tbody');
  let section = null;
  d.rows.forEach(r => {
    if (r.section !== section) {
      section = r.section;
      const tr = el('tr', null, 'bg-light');
      tr.appendChild(el('th', section, '', { colspan: '3' }));
      tb.appendChild(tr);
    }
    const tr = el('tr');
    tr.appendChild(el('td', r.name, 'pl-4'));
    const href = r.next ? drillUrl(ctx, r, false) : null;
    tr.appendChild(amountCell(r.debit, r.debit ? href : null));
    tr.appendChild(amountCell(r.credit, r.credit ? href : null));
    tb.appendChild(tr);
  });
  const tot = el('tr', null, 'font-weight-bold table-active');
  tot.appendChild(el('td', 'Total'));
  tot.appendChild(amountCell(d.totals.debit)); tot.appendChild(amountCell(d.totals.credit));
  tb.appendChild(tot);
  const chk = el('tr');
  const msg = d.totals.balanced ? 'Debits equal credits' : 'OUT OF BALANCE: debits - credits = ' + d.totals.difference;
  chk.appendChild(el('td', msg, d.totals.balanced ? 'text-success font-weight-bold' : 'text-danger font-weight-bold', { colspan: '3' }));
  tb.appendChild(chk);
  table.appendChild(tb);
  const tw = el('div', null, 'table-responsive');
  tw.appendChild(table);
  wrap.appendChild(tw);
  area.appendChild(printButton());
  const x = (detailed, label) => { const a = excelLink(endpoint(d0.report) + '?' + query({ asof: ctx.asof, cong: ctx.cong, detailed: detailed ? '1' : '', format: 'xlsx' })); a.textContent = label; return a; };
  area.appendChild(x(false, 'Excel (summary)'));
  area.appendChild(x(true, 'Excel (detailed, every account)'));
  area.appendChild(wrap);
}

async function preview() {
  const asof = document.getElementById('asof').value;
  const congSel = document.getElementById('cong');
  const cong = congSel && congSel.value !== '0' ? congSel.value : '';
  const detailed = document.getElementById('detailed').checked;
  if (!asof) return say('Select the as-of date');
  say('');
  try {
    const d = await getJson(endpoint(d0.report) + '?' + query({ asof, cong, detailed: detailed ? '1' : '' }));
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
  const bf = d.bf || account === 'bf';
  const nav = el('nav', null, 'mb-2');
  nav.appendChild(el('a', 'Trial balance', '', { href: topUrl(ctx) }));
  if (bf && account !== 'bf') {
    nav.appendChild(document.createTextNode(' > '));
    nav.appendChild(el('a', 'Accumulated fund b/f', '', { href: endpoint(d0.drillpage) + '?' + query({ account: 'bf', asof: ctx.asof, cong: ctx.cong }) }));
  }
  if (account === 'bf') { nav.appendChild(document.createTextNode(' > ')); nav.appendChild(el('span', 'Accumulated fund b/f')); }
  (d.crumbs || []).forEach((c, i) => {
    nav.appendChild(document.createTextNode(' > '));
    const last = i === d.crumbs.length - 1 && d.mode === 'children';
    if (last) nav.appendChild(el('span', c.name));
    else nav.appendChild(el('a', c.name, '', { href: endpoint(d0.drillpage) + '?' + query({ account: c.id, asof: ctx.asof, cong: ctx.cong, bf: bf ? '1' : '' }) }));
  });
  if (d.mode === 'lines') { nav.appendChild(document.createTextNode(' > ')); nav.appendChild(el('span', d.direct ? 'Postings on this account' : 'Journal lines')); }
  wrap.appendChild(nav);
  wrap.appendChild(el('div', 'As at ' + ddmmyyyy(d.asOf) + (bf ? ' - result of the financial years before the current one (credit = surplus)' : ''), 'mb-2'));
  const xlParams = { account, asof: ctx.asof, cong: ctx.cong, bf: ctx.bf, direct: ctx.direct ? '1' : '', view: ctx.view };
  area.appendChild(printButton());
  if (d.mode === 'children') {
    area.appendChild(excelLink(endpoint(d0.drillrpt) + '?' + query(Object.assign({}, xlParams, { format: 'xlsx' }))));
    wrap.appendChild(el('h5', d.name + ': debit ' + (d.debit || '0.00') + ' / credit ' + (d.credit || '0.00')));
    const rows = d.rows.map(r => {
      const href = drillUrl(ctx, r, bf);
      return { text: r.name, cells: [el('td', r.name), amountCell(r.debit, r.debit ? href : null), amountCell(r.credit, r.credit ? href : null)] };
    });
    const footer = [el('td', 'Total of the rows (' + (d.sumsExactly ? 'adds up to the figure above' : 'DOES NOT add up') + ')'), amountCell(d.rowsDebit), amountCell(d.rowsCredit)];
    childrenPanel(wrap, ['Account', 'Debit', 'Credit'], rows, footer);
  } else {
    wrap.appendChild(el('h5', d.name + ': trial balance figure debit ' + (d.figureDebit || '0.00') + ' / credit ' + (d.figureCredit || '0.00')));
    linesPanel(wrap, {
      withAccount: true,
      openingLabel: 'Opening balance (before the first line of this basis)',
      first: d,
      load: (p, s, q) => getJson(endpoint(d0.drillrpt) + '?' + query(Object.assign({}, xlParams, { page: p, pageSize: s, search: q }))),
      excel: (q) => endpoint(d0.drillrpt) + '?' + query(Object.assign({}, xlParams, { format: 'xlsx', search: q })),
    });
  }
  area.appendChild(wrap);
}

async function loadDrill() {
  const p = new URLSearchParams(window.location.search);
  const ctx = { asof: p.get('asof') || '', cong: p.get('cong') || '', bf: p.get('bf') === '1' ? '1' : '', direct: p.get('direct') === '1', view: p.get('view') || '' };
  const account = p.get('account') || '';
  try {
    const d = await getJson(endpoint(d0.drillrpt) + '?' + query({ account, asof: ctx.asof, cong: ctx.cong, bf: ctx.bf, direct: ctx.direct ? '1' : '', view: ctx.view }));
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
