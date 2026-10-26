// Journal report, task 9.7: whole journals (never split across pages) with their lines, totals, search and Excel.
// All server strings are written with textContent.
import { el, show, ddmmyyyy, getJson, amountCell, printButton, excelLink } from './is/report-ui-v1.js';

const app = document.getElementById('jrnapp');
const base = app.dataset.base;
const results = document.getElementById('results');
const alertBox = document.getElementById('report-error');
const say = message => { alertBox.hidden = !message; show(alertBox, message); };

function panel(area, from, to, first) {
  const state = { page: 1, size: 50, search: '' };
  const bar = el('div', null, 'd-flex align-items-center flex-wrap mb-1 no-print');
  const search = el('input', null, 'form-control form-control-sm mr-2', { type: 'search', placeholder: 'Search journal no, date, reference, narration, account or amount', style: 'width:30rem' });
  const size = el('select', null, 'form-control form-control-sm mr-2', { style: 'width:6rem' });
  [25, 50, 100, 200].forEach(n => { const o = el('option', String(n)); o.value = String(n); if (n === 50) o.selected = true; size.appendChild(o); });
  const prev = el('button', 'Prev', 'btn btn-sm btn-light mr-1', { type: 'button' });
  const next = el('button', 'Next', 'btn btn-sm btn-light mr-2', { type: 'button' });
  const info = el('span', '', 'small mr-2');
  const xlsx = excelLink('');
  bar.append(search, size, prev, next, info, xlsx);
  const err = el('div');
  const holder = el('div', null, 'table-responsive');
  area.append(bar, err, holder);
  let preloaded = first;
  let timer = null;

  const url = extra => {
    const p = new URLSearchParams({ from, to });
    Object.keys(extra).forEach(k => { if (extra[k] !== '' && extra[k] !== undefined) p.set(k, extra[k]); });
    return base + '/journalsrpt?' + p.toString();
  };

  function render(d) {
    holder.replaceChildren();
    const table = el('table', null, 'table table-bordered table-sm');
    table.id = 'table';
    const thead = el('thead', null, 'bg-lightblue');
    const hr = el('tr');
    ['Journal No', 'Date', 'Reference', 'Description', 'Account', 'Debit', 'Credit', 'Memo'].forEach((h, i) => hr.appendChild(el('th', h, i === 5 || i === 6 ? 'text-right' : '')));
    thead.appendChild(hr);
    table.appendChild(thead);
    const body = el('tbody');
    d.journals.forEach(j => {
      j.lines.forEach((l, i) => {
        const tr = el('tr');
        [i === 0 ? j.journalNo : '', i === 0 ? j.date : '', i === 0 ? j.reference : '', i === 0 ? j.description : '', l.account].forEach(v => tr.appendChild(el('td', v)));
        tr.appendChild(amountCell(l.debit));
        tr.appendChild(amountCell(l.credit));
        tr.appendChild(el('td', l.memo));
        body.appendChild(tr);
      });
      const t = el('tr', null, 'font-weight-bold table-active' + (j.balanced ? '' : ' text-danger'));
      t.appendChild(el('td', 'Journal ' + j.journalNo + ' total', '', { colspan: '5' }));
      t.appendChild(amountCell(j.debits));
      t.appendChild(amountCell(j.credits));
      t.appendChild(el('td', j.balanced ? '' : 'Debits and credits differ'));
      body.appendChild(t);
    });
    const g = el('tr', null, 'font-weight-bold');
    g.appendChild(el('td', d.search ? 'Total of the ' + d.matching + ' matching journals' : 'Total', '', { colspan: '5' }));
    g.appendChild(amountCell(d.debits));
    g.appendChild(amountCell(d.credits));
    g.appendChild(el('td'));
    body.appendChild(g);
    table.appendChild(body);
    holder.appendChild(table);
    const f = d.matching ? (d.page - 1) * d.pageSize + 1 : 0;
    const t = Math.min(d.matching, d.page * d.pageSize);
    info.textContent = f + '-' + t + ' of ' + d.matching + (d.search ? ' matching (' + d.count + ' in the period)' : ' journals') + ', page ' + d.page + ' of ' + d.pages;
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

async function preview() {
  const from = document.getElementById('from').value;
  const to = document.getElementById('to').value;
  if (!from || !to) return say('Select the From and To dates');
  if (from > to) return say('From date cannot be after the to date');
  say('');
  results.replaceChildren();
  try {
    const first = await getJson(base + '/journalsrpt?' + new URLSearchParams({ from, to, page: 1, pageSize: 50 }).toString());
    results.appendChild(printButton());
    const area = el('div', null, 'is-print-area');
    area.appendChild(el('div', 'Journal report', 'font-weight-bold'));
    area.appendChild(el('div', ddmmyyyy(from) + ' to ' + ddmmyyyy(to), 'mb-2'));
    results.appendChild(area);
    panel(area, from, to, first);
  } catch (e) {
    say(e.message);
  }
}

document.getElementById('preview').addEventListener('click', preview);
