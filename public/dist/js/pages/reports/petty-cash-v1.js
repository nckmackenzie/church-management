// Petty cash book, task 9.6: journal lines on one petty-cash account.
import { el, show, ddmmyyyy, getJson, query, amountCell, printButton, excelLink } from './is/report-ui-v1.js';

const app = document.getElementById('pcapp');
const base = app.dataset.base;
const alertBox = document.getElementById('report-error');
const say = message => { alertBox.hidden = !message; show(alertBox, message); };

function panel(container, params, first) {
  const state = { page: 1, size: 50, search: '' };
  const bar = el('div', null, 'd-flex align-items-center flex-wrap mb-1 report-actions');
  const search = el('input', null, 'form-control form-control-sm mr-2', { type: 'search', placeholder: 'Search date, source, reference, narration or amount', style: 'width:27rem' });
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
  let preloaded = first;
  let timer = null;

  const url = extra => base + '/pettycashrpt?' + query(Object.assign({}, params, extra));
  function render(d) {
    holder.replaceChildren();
    const table = el('table', null, 'table table-sm table-bordered');
    table.id = 'table';
    const thead = el('thead', null, 'bg-lightblue');
    const hr = el('tr');
    ['Date', 'Entry / journal no', 'Source', 'Reference', 'Narration', 'Receipts', 'Payments', 'Running balance'].forEach((h, i) => hr.appendChild(el('th', h, i >= 5 ? 'text-right' : '')));
    thead.appendChild(hr); table.appendChild(thead);
    const body = el('tbody');
    const opening = el('tr', null, 'font-italic');
    opening.appendChild(el('td', 'Opening balance (before ' + ddmmyyyy(d.from) + ')', '', { colspan: '7' }));
    opening.appendChild(amountCell(d.opening)); body.appendChild(opening);
    d.rows.forEach(r => {
      const tr = el('tr');
      [r.date, r.entry, null, r.reference, r.narration].forEach((v, i) => {
        if (i !== 2) tr.appendChild(el('td', v));
        else {
          const td = el('td');
          if (r.link) td.appendChild(el('a', r.source, '', { href: r.link, target: '_blank', rel: 'noopener' }));
          else td.textContent = r.source;
          tr.appendChild(td);
        }
      });
      tr.appendChild(amountCell(r.receipt)); tr.appendChild(amountCell(r.payment)); tr.appendChild(amountCell(r.balance)); body.appendChild(tr);
    });
    const total = el('tr', null, 'font-weight-bold table-active');
    total.appendChild(el('td', d.search ? 'Total of the ' + d.matching + ' matching rows' : 'Total of the period', '', { colspan: '5' }));
    total.appendChild(amountCell(d.filteredReceipts)); total.appendChild(amountCell(d.filteredPayments)); total.appendChild(el('td')); body.appendChild(total);
    const closing = el('tr', null, 'font-weight-bold');
    closing.appendChild(el('td', 'Closing balance', '', { colspan: '7' })); closing.appendChild(amountCell(d.closing)); body.appendChild(closing);
    table.appendChild(body); holder.appendChild(table);
    const from = d.matching ? (d.page - 1) * d.pageSize + 1 : 0;
    const to = Math.min(d.matching, d.page * d.pageSize);
    info.textContent = from + '-' + to + ' of ' + d.matching + (d.search ? ' matching (' + d.count + ' in the period)' : ' lines') + ', page ' + d.page + ' of ' + d.pages;
    prev.disabled = d.page <= 1; next.disabled = d.page >= d.pages;
    xlsx.href = url({ format: 'xlsx', search: state.search });
  }
  async function refresh() {
    try {
      err.replaceChildren();
      const d = preloaded || await getJson(url({ page: state.page, pageSize: state.size, search: state.search }));
      preloaded = null; state.page = d.page; render(d);
    } catch (e) { err.replaceChildren(el('div', e.message, 'alert alert-danger')); }
  }
  search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { state.search = search.value.trim(); state.page = 1; refresh(); }, 350); });
  size.addEventListener('change', () => { state.size = parseInt(size.value, 10); state.page = 1; refresh(); });
  prev.addEventListener('click', () => { state.page--; refresh(); }); next.addEventListener('click', () => { state.page++; refresh(); });
  refresh();
}

async function preview() {
  const account = document.getElementById('account').value;
  const from = document.getElementById('start').value;
  const to = document.getElementById('end').value;
  if (!account) return say('Please select a petty cash account');
  if (!from || !to) return say('Please select the from and to dates');
  if (from > to) return say('From date cannot be after the to date');
  say('');
  const results = document.getElementById('results'); results.replaceChildren();
  const params = { account, from, to };
  try {
    const first = await getJson(base + '/pettycashrpt?' + query(params));
    results.appendChild(printButton());
    const area = el('div', null, 'is-print-area'); area.id = 'printarea';
    area.appendChild(el('div', 'Petty cash book: ' + first.name, 'mb-0'));
    area.appendChild(el('div', ddmmyyyy(first.from) + ' to ' + ddmmyyyy(first.to), 'mb-2'));
    results.appendChild(area); panel(area, params, first);
  } catch (e) { say(e.message); }
}

document.getElementById('preview').addEventListener('click', preview);
