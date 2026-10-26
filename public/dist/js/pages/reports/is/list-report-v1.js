// A searchable, server-paged list of transactions with an Excel link (task 9.7, decisions 141-142).
// The server (ReportList) answers { title, lines, columns, rows, count, matching, total, page, pageSize, pages }; every server string
// goes on the page with textContent. params: an object or URLSearchParams of the report's filters.
import { el, getJson, amountCell, excelLink } from './report-ui-v1.js';

function withPaging(params, extra) {
  const q = new URLSearchParams(params.toString());
  Object.keys(extra).forEach(k => { if (extra[k] !== undefined && extra[k] !== null && extra[k] !== '') q.set(k, extra[k]); });
  return q;
}

/**
 * container: element to fill; endpoint: URL without query; params: URLSearchParams; first: page-1 data already loaded (optional);
 * placeholder: search box hint.
 */
export function listPanel(container, endpoint, params, first, placeholder) {
  const state = { page: 1, size: 50, search: '' };
  const bar = el('div', null, 'd-flex align-items-center flex-wrap mb-1 report-actions no-print');
  const search = el('input', null, 'form-control form-control-sm mr-2', { type: 'search', placeholder: placeholder || 'Search', style: 'width:24rem' });
  const size = el('select', null, 'form-control form-control-sm mr-2', { style: 'width:6rem' });
  [25, 50, 100, 200].forEach(n => { const o = el('option', String(n)); o.value = String(n); if (n === 50) o.selected = true; size.appendChild(o); });
  const prev = el('button', 'Prev', 'btn btn-sm btn-light mr-1', { type: 'button' });
  const next = el('button', 'Next', 'btn btn-sm btn-light mr-2', { type: 'button' });
  const info = el('span', '', 'small mr-2');
  const xlsx = excelLink('');
  bar.append(search, size, prev, next, info, xlsx);
  const err = el('div');
  const holder = el('div', null, 'table-responsive');
  container.append(bar, err, holder);
  let preloaded = first || null;
  let timer = null;

  const url = extra => endpoint + '?' + withPaging(params, extra).toString();

  function render(d) {
    holder.replaceChildren();
    const table = el('table', null, 'table table-sm table-bordered');
    table.id = 'table';
    const thead = el('thead', null, 'bg-lightblue');
    const hr = el('tr');
    d.columns.forEach(c => hr.appendChild(el('th', c.label, c.type === 'money' ? 'text-right' : '')));
    thead.appendChild(hr);
    table.appendChild(thead);
    const body = el('tbody');
    d.rows.forEach(r => {
      const tr = el('tr');
      d.columns.forEach(c => {
        if (c.type === 'money') { tr.appendChild(amountCell(r[c.key])); return; }
        const td = el('td');
        if (c.type === 'link' && r.link) td.appendChild(el('a', r[c.key], '', { href: r.link, target: '_blank', rel: 'noopener' }));
        else td.textContent = r[c.key];
        tr.appendChild(td);
      });
      body.appendChild(tr);
    });
    if (d.totalKey) {
      const tot = el('tr', null, 'font-weight-bold table-active');
      d.columns.forEach((c, i) => {
        if (c.key === d.totalKey) tot.appendChild(amountCell(d.total));
        else tot.appendChild(el('td', i === 0 ? (d.search ? 'Total of the ' + d.matching + ' matching rows' : 'Total') : ''));
      });
      body.appendChild(tot);
    }
    table.appendChild(body);
    holder.appendChild(table);
    const from = d.matching ? (d.page - 1) * d.pageSize + 1 : 0;
    const to = Math.min(d.matching, d.page * d.pageSize);
    info.textContent = from + '-' + to + ' of ' + d.matching + (d.search ? ' matching (' + d.count + ' in the period)' : ' rows') + ', page ' + d.page + ' of ' + d.pages;
    prev.disabled = d.page <= 1;
    next.disabled = d.page >= d.pages;
    xlsx.setAttribute('href', url({ format: 'xlsx', search: state.search }));
  }

  async function refresh() {
    try {
      err.replaceChildren();
      const d = preloaded || await getJson(url({ page: state.page, pageSize: state.size, search: state.search }));
      preloaded = null;
      state.page = d.page;
      render(d);
    } catch (e) {
      err.replaceChildren(el('div', e.message, 'alert alert-danger'));
    }
  }
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { state.search = search.value.trim(); state.page = 1; refresh(); }, 350); });
  size.addEventListener('change', () => { state.size = parseInt(size.value, 10); state.page = 1; refresh(); });
  prev.addEventListener('click', () => { state.page = Math.max(1, state.page - 1); refresh(); });
  next.addEventListener('click', () => { state.page = state.page + 1; refresh(); });
  refresh();
}

/** Fetch page 1, then draw a heading (title and period lines) and the panel. Returns when the first page is on screen. */
export async function showList(area, endpoint, params, placeholder) {
  const first = await getJson(endpoint + '?' + withPaging(params, { page: 1, pageSize: 50 }).toString());
  area.replaceChildren();
  area.appendChild(el('div', first.title, 'font-weight-bold'));
  (first.lines || []).forEach(l => area.appendChild(el('div', l, 'mb-0')));
  area.appendChild(el('div', null, 'mb-2'));
  listPanel(area, endpoint, params, first, placeholder);
  return first;
}
