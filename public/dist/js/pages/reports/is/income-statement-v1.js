// Income statement (congregation and parish) and its drill-down, task 9.2.
// Every server string is put on the page with textContent; the URLs carry account IDs only.
const app = document.getElementById('isapp');
const base = app.dataset.base; // .../reports or .../parishreports
const page = app.dataset.mode; // 'index' | 'drill'
const alertBox = document.getElementById('isalert');

function el(tag, text, cls, attrs) {
  const e = document.createElement(tag);
  if (text !== undefined && text !== null) e.textContent = text;
  if (cls) e.className = cls;
  if (attrs) Object.keys(attrs).forEach(k => e.setAttribute(k, attrs[k]));
  return e;
}
function show(message, kind) {
  alertBox.replaceChildren();
  if (message) alertBox.appendChild(el('div', message, 'alert alert-' + (kind || 'danger')));
}
function ddmmyyyy(d) {
  const p = String(d).split('-');
  return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : String(d);
}
async function getJson(url) {
  const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  const text = await res.text();
  let data = null;
  try { data = JSON.parse(text); } catch (e) { /* plain text message */ }
  if (!res.ok || !data || data.success !== true) {
    throw new Error((data && data.message) || text || 'The report could not be loaded');
  }
  return data;
}
function query(params) {
  const q = new URLSearchParams();
  Object.keys(params).forEach(k => { if (params[k] !== undefined && params[k] !== null && params[k] !== '') q.set(k, params[k]); });
  return q.toString();
}
function drillUrl(ctx, row) {
  const p = { account: row.id, start: ctx.start, end: ctx.end, cong: ctx.cong };
  if (row.direct) p.direct = '1';
  else if (row.next === 'lines') p.view = 'lines';
  return base + '/plchildaccount?' + query(p);
}
function amountCell(text, href, cls) {
  const td = el('td', null, 'text-right ' + (cls || ''));
  if (text === null || text === undefined) return td;
  if (href) { const a = el('a', text, '', { href, target: '_blank', rel: 'noopener' }); td.appendChild(a); } else { td.textContent = text; }
  return td;
}
function printButton() {
  const b = el('button', 'Print', 'btn btn-sm btn-secondary no-print', { type: 'button' });
  b.addEventListener('click', () => window.print());
  return b;
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
  area.appendChild(wrap);
}

async function preview() {
  const start = document.getElementById('start').value;
  const end = document.getElementById('end').value;
  const congSel = document.getElementById('cong');
  const cong = congSel ? congSel.value : '';
  if (!start || !end) return show('Select the start and end dates');
  if (start > end) return show('Start date cannot be after the end date');
  show('');
  try {
    const d = await getJson(base + '/incomestatementrpt?' + query({ start, end, cong: cong === '0' ? '' : cong }));
    renderReport(d, { start, end, cong: cong === '0' ? '' : cong });
  } catch (e) {
    document.getElementById('results').replaceChildren();
    show(e.message);
  }
}

// ---------------------------------------------------------------- drill
function renderDrill(d, ctx) {
  const area = document.getElementById('results');
  area.replaceChildren();
  const wrap = el('div', null, 'is-print-area');
  wrap.id = 'printarea';
  const nav = el('nav', null, 'mb-2');
  const top = el('a', 'Income statement', '', { href: base + '/incomestatement?' + query({ start: ctx.start, end: ctx.end, cong: ctx.cong }) });
  nav.appendChild(top);
  d.crumbs.forEach((c, i) => {
    nav.appendChild(document.createTextNode(' > '));
    const last = i === d.crumbs.length - 1 && !ctx.direct && ctx.view !== 'lines' && d.mode === 'children';
    if (last) { nav.appendChild(el('span', c.name)); }
    else { nav.appendChild(el('a', c.name, '', { href: base + '/plchildaccount?' + query({ account: c.id, start: ctx.start, end: ctx.end, cong: ctx.cong }) })); }
  });
  if (d.mode === 'lines') { nav.appendChild(document.createTextNode(' > ')); nav.appendChild(el('span', d.direct ? 'Postings on this account' : 'Journal lines')); }
  wrap.appendChild(nav);
  wrap.appendChild(el('div', 'Period ' + ddmmyyyy(d.start) + ' to ' + ddmmyyyy(d.end), 'mb-2'));

  const table = el('table', null, 'table table-sm table-bordered');
  table.id = 'table';
  const thead = el('thead', null, 'bg-lightblue');
  const tr0 = el('tr');
  const tb = el('tbody');
  if (d.mode === 'children') {
    wrap.appendChild(el('h5', d.name + ': ' + d.amount + (d.cmp !== null ? '  (last year ' + d.cmp + ')' : '')));
    ['Account', 'Amount', 'Last year'].forEach((t, i) => tr0.appendChild(el('th', t, i ? 'text-right' : '')));
    d.rows.forEach(r => {
      const tr = el('tr');
      tr.appendChild(el('td', r.name));
      const href = drillUrl(ctx, r);
      tr.appendChild(amountCell(r.amount, href));
      tr.appendChild(amountCell(r.cmp, r.cmp === null ? null : href));
      tb.appendChild(tr);
    });
    const tr = el('tr', null, 'font-weight-bold table-active');
    tr.appendChild(el('td', 'Total of the rows (' + (d.sumsExactly ? 'adds up to' : 'DOES NOT add up to') + ' ' + d.amount + ')'));
    tr.appendChild(amountCell(d.rowsTotal));
    tr.appendChild(amountCell(''));
    tb.appendChild(tr);
  } else {
    wrap.appendChild(el('h5', d.name + ': movement ' + d.movement));
    ['Date', 'Entry no', 'Journal no', 'Reference', 'Narration', 'Type', 'Account', 'Debit', 'Credit', 'Balance', 'Source'].forEach((t, i) => tr0.appendChild(el('th', t, i >= 7 && i <= 9 ? 'text-right' : '')));
    const op = el('tr', null, 'font-italic');
    op.appendChild(el('td', 'Opening balance (before ' + ddmmyyyy(d.start) + ')', '', { colspan: '9' }));
    op.appendChild(amountCell(d.opening));
    op.appendChild(el('td'));
    tb.appendChild(op);
    d.rows.forEach(r => {
      const tr = el('tr');
      [r.date, r.entryId, r.journalNo === null ? '' : r.journalNo, r.reference, r.memo, r.type, r.account].forEach(v => tr.appendChild(el('td', v)));
      tr.appendChild(amountCell(r.debit)); tr.appendChild(amountCell(r.credit)); tr.appendChild(amountCell(r.balance));
      const src = el('td');
      if (r.link) src.appendChild(el('a', 'Open', '', { href: r.link, target: '_blank', rel: 'noopener' }));
      tr.appendChild(src);
      tb.appendChild(tr);
    });
    const tot = el('tr', null, 'font-weight-bold table-active');
    tot.appendChild(el('td', 'Total of the period', '', { colspan: '7' }));
    tot.appendChild(amountCell(d.debits)); tot.appendChild(amountCell(d.credits)); tot.appendChild(amountCell(d.closing));
    tot.appendChild(el('td'));
    tb.appendChild(tot);
  }
  thead.appendChild(tr0);
  table.appendChild(thead);
  table.appendChild(tb);
  const tw = el('div', null, 'table-responsive');
  tw.appendChild(table);
  wrap.appendChild(tw);
  area.appendChild(printButton());
  area.appendChild(wrap);
}

async function loadDrill() {
  const p = new URLSearchParams(window.location.search);
  const ctx = { start: p.get('start') || '', end: p.get('end') || '', cong: p.get('cong') || '', direct: p.get('direct') === '1', view: p.get('view') || '' };
  const account = p.get('account') || '';
  try {
    const d = await getJson(base + '/plchildaccountrpt?' + query({ account, start: ctx.start, end: ctx.end, cong: ctx.cong, direct: ctx.direct ? '1' : '', view: ctx.view }));
    renderDrill(d, ctx);
  } catch (e) {
    show(e.message);
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
