// Shared pieces of the report screens (income statement, trial balance, ledger statement), task 9.3.
// Every server string goes on the page with textContent; money arrives already formatted as text.

export function el(tag, text, cls, attrs) {
  const e = document.createElement(tag);
  if (text !== undefined && text !== null) e.textContent = text;
  if (cls) e.className = cls;
  if (attrs) Object.keys(attrs).forEach(k => e.setAttribute(k, attrs[k]));
  return e;
}

export function show(box, message, kind) {
  box.replaceChildren();
  if (message) box.appendChild(el('div', message, 'alert alert-' + (kind || 'danger')));
}

export function ddmmyyyy(d) {
  const p = String(d).split('-');
  return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : String(d);
}

export async function getJson(url) {
  const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
  const text = await res.text();
  let data = null;
  try { data = JSON.parse(text); } catch (e) { /* plain text message */ }
  if (!res.ok || !data || data.success !== true) {
    throw new Error((data && data.message) || text || 'The report could not be loaded');
  }
  return data;
}

export function query(params) {
  const q = new URLSearchParams();
  Object.keys(params).forEach(k => {
    if (params[k] !== undefined && params[k] !== null && params[k] !== '') q.set(k, params[k]);
  });
  return q.toString();
}

export function amountCell(text, href, cls) {
  const td = el('td', null, 'text-right ' + (cls || ''));
  if (text === null || text === undefined) return td;
  if (href) td.appendChild(el('a', text, '', { href, target: '_blank', rel: 'noopener' }));
  else td.textContent = text;
  return td;
}

export function printButton() {
  const b = el('button', 'Print', 'btn btn-sm btn-secondary no-print mr-1', { type: 'button' });
  b.addEventListener('click', () => window.print());
  return b;
}

/** a link that downloads the .xlsx of the current view (all its rows) */
export function excelLink(href) {
  return el('a', 'Excel', 'btn btn-sm btn-success no-print mr-1', { href });
}

/**
 * A short table (accounts of one level) with a search box and client-side paging (decision 142: child levels are short).
 * rows: [{ cells: [Node...], text: 'searchable text' }]; footer: [Node...] (always shown).
 */
export function childrenPanel(container, headers, rows, footer, pageSize) {
  const size = pageSize || 50;
  let page = 1;
  let filter = '';
  const bar = el('div', null, 'd-flex align-items-center mb-1 no-print');
  const search = el('input', null, 'form-control form-control-sm w-25 mr-2', { type: 'search', placeholder: 'Search accounts' });
  const info = el('span', '', 'mx-2 small');
  const prev = el('button', 'Prev', 'btn btn-sm btn-light mr-1', { type: 'button' });
  const next = el('button', 'Next', 'btn btn-sm btn-light', { type: 'button' });
  bar.append(search, prev, next, info);
  const table = el('table', null, 'table table-sm table-bordered');
  table.id = 'table';
  const thead = el('thead', null, 'bg-lightblue');
  const hr = el('tr');
  headers.forEach((h, i) => hr.appendChild(el('th', h, i ? 'text-right' : '')));
  thead.appendChild(hr);
  const tb = el('tbody');
  table.append(thead, tb);
  container.append(bar, el('div', null, 'table-responsive'));
  container.lastChild.appendChild(table);

  function draw() {
    const f = rows.filter(r => !filter || r.text.toLowerCase().indexOf(filter) !== -1);
    const pages = Math.max(1, Math.ceil(f.length / size));
    if (page > pages) page = pages;
    tb.replaceChildren();
    f.slice((page - 1) * size, page * size).forEach(r => { const tr = el('tr'); r.cells.forEach(c => tr.appendChild(c)); tb.appendChild(tr); });
    const ft = el('tr', null, 'font-weight-bold table-active');
    footer.forEach(c => ft.appendChild(c));
    tb.appendChild(ft);
    info.textContent = f.length + ' of ' + rows.length + ' accounts, page ' + page + ' of ' + pages;
    prev.disabled = page <= 1;
    next.disabled = page >= pages;
  }
  search.addEventListener('input', () => { filter = search.value.trim().toLowerCase(); page = 1; draw(); });
  prev.addEventListener('click', () => { page--; draw(); });
  next.addEventListener('click', () => { page++; draw(); });
  draw();
}

/**
 * Journal lines with server-side search and paging (decision 142). opts:
 *   load(page, size, search) -> Promise<data>   data as LinesView::shape() answers
 *   excel(search) -> url                        the .xlsx of every matching row
 *   first                                       data already loaded for page 1 with no search (optional)
 *   withAccount                                 show the account column (subledgers)
 *   openingLabel                                text for the opening balance row
 */
export function linesPanel(container, opts) {
  const state = { page: 1, size: 50, search: '' };
  const bar = el('div', null, 'd-flex align-items-center flex-wrap mb-1 no-print');
  const search = el('input', null, 'form-control form-control-sm mr-2', { type: 'search', placeholder: 'Search date, entry no, reference, narration, type, amount', style: 'width:26rem' });
  const sizeSel = el('select', null, 'form-control form-control-sm mr-2', { style: 'width:6rem' });
  [25, 50, 100, 200].forEach(n => { const o = el('option', String(n)); o.value = String(n); if (n === 50) o.selected = true; sizeSel.appendChild(o); });
  const prev = el('button', 'Prev', 'btn btn-sm btn-light mr-1', { type: 'button' });
  const next = el('button', 'Next', 'btn btn-sm btn-light mr-2', { type: 'button' });
  const info = el('span', '', 'small mr-2');
  const xl = excelLink(opts.excel(''));
  bar.append(search, sizeSel, prev, next, info, xl);
  const holder = el('div', null, 'table-responsive');
  const err = el('div');
  container.append(bar, err, holder);
  let timer = null;
  let preloaded = opts.first || null;

  function render(d) {
    holder.replaceChildren();
    const table = el('table', null, 'table table-sm table-bordered');
    table.id = 'table';
    const heads = ['Date', 'Entry no', 'Journal no', 'Reference', 'Narration', 'Type'];
    if (opts.withAccount) heads.push('Account');
    heads.push('Debit', 'Credit', 'Balance (' + d.direction + ')', 'Source');
    const thead = el('thead', null, 'bg-lightblue');
    const hr = el('tr');
    heads.forEach(h => hr.appendChild(el('th', h, ['Debit', 'Credit'].indexOf(h) !== -1 || h.indexOf('Balance') === 0 ? 'text-right' : '')));
    thead.appendChild(hr);
    table.appendChild(thead);
    const tb = el('tbody');
    const span = heads.length - 4;   // columns before Debit
    const op = el('tr', null, 'font-italic');
    op.appendChild(el('td', (opts.openingLabel || 'Opening balance'), '', { colspan: String(span + 2) }));
    op.appendChild(amountCell(d.opening));
    op.appendChild(el('td'));
    tb.appendChild(op);
    d.rows.forEach(r => {
      const tr = el('tr');
      const cells = [r.date, r.entryId, r.journalNo === null ? '' : r.journalNo, r.reference, r.memo, r.type];
      if (opts.withAccount) cells.push(r.account);
      cells.forEach(v => tr.appendChild(el('td', v)));
      tr.appendChild(amountCell(r.debit)); tr.appendChild(amountCell(r.credit)); tr.appendChild(amountCell(r.balance));
      const src = el('td');
      if (r.link) src.appendChild(el('a', 'Open', '', { href: r.link, target: '_blank', rel: 'noopener' }));
      tr.appendChild(src);
      tb.appendChild(tr);
    });
    const tot = el('tr', null, 'font-weight-bold table-active');
    tot.appendChild(el('td', d.search ? 'Total of the ' + d.matching + ' matching rows' : 'Total of the period', '', { colspan: String(span) }));
    tot.appendChild(amountCell(d.filteredDebits)); tot.appendChild(amountCell(d.filteredCredits)); tot.appendChild(el('td')); tot.appendChild(el('td'));
    tb.appendChild(tot);
    const cl = el('tr', null, 'font-weight-bold');
    cl.appendChild(el('td', 'Closing balance', '', { colspan: String(span + 2) }));
    cl.appendChild(amountCell(d.closing));
    cl.appendChild(el('td'));
    tb.appendChild(cl);
    table.appendChild(tb);
    holder.appendChild(table);
    const from = d.matching === 0 ? 0 : (d.page - 1) * d.pageSize + 1;
    const to = Math.min(d.matching, d.page * d.pageSize);
    info.textContent = from + '-' + to + ' of ' + d.matching + (d.search ? ' matching (' + d.count + ' in the period)' : ' lines') + ', page ' + d.page + ' of ' + d.pages;
    prev.disabled = d.page <= 1;
    next.disabled = d.page >= d.pages;
    xl.setAttribute('href', opts.excel(state.search));
  }

  async function refresh() {
    try {
      err.replaceChildren();
      let d;
      if (preloaded && state.page === 1 && state.search === '' && state.size === 50) { d = preloaded; preloaded = null; }
      else d = await opts.load(state.page, state.size, state.search);
      state.page = d.page;
      render(d);
    } catch (e) {
      err.appendChild(el('div', e.message, 'alert alert-danger'));
    }
  }
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { state.search = search.value.trim(); state.page = 1; refresh(); }, 350); });
  sizeSel.addEventListener('change', () => { state.size = parseInt(sizeSel.value, 10); state.page = 1; refresh(); });
  prev.addEventListener('click', () => { state.page = Math.max(1, state.page - 1); refresh(); });
  next.addEventListener('click', () => { state.page = state.page + 1; refresh(); });
  refresh();
}
