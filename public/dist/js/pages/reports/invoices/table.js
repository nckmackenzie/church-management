import { numberWithCommas } from '../../utils/utils.js';
// every server string is escaped before it goes into the table markup (stored XSS, security list S10)
const esc = value =>
  String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
export function withBalancesTable(data) {
  let html = `
      <table class="table table-striped table-bordered table-sm" id="invoicereport">
         <thead class="bg-lightblue">
            <tr>
              <th>Supplier</th>
              <th>Invoice No</th>
              <th>Invoice Date</th>
              <th>Due Date</th>
              <th>Invoice Amount</th>
              <th>Amount Paid</th>
              <th>Balance</th>
            </tr>
         </thead>
         <tbody>`;
  data.forEach(dt => {
    html += `
              <tr>
                <td>${esc(dt.supplierName)}</td>
                <td>${esc(dt.invoiceNo)}</td>
                <td>${esc(dt.invoiceDate)}</td>
                <td>${esc(dt.dueDate)}</td>
                <td>${numberWithCommas(dt.inclusiveVat)}</td>
                <td>${numberWithCommas(dt.amountPaid)}</td>
                <td>${numberWithCommas(dt.Balance)}</td>
              </tr>
           `;
  });

  html += `</tbody>
    <tfoot>
      <tr>
          <th colspan="4" style="text-align:center">Total:</th>
          <th id="invoiceval"></th>
          <th id="paid"></th>
          <th id="bal"></th>
      </tr>
    </tfoot>
      </table>
    `;
  return html;
}
export function paymentByInvoice(data) {
  let html = `
      <table class="table table-striped table-bordered table-sm" id="invoicereport">
         <thead class="bg-lightblue">
            <tr>
              <th>Payment Date</th>
              <th>Payment No</th>
              <th>Supplier</th>
              <th>Invoice No</th>
              <th>Amount Paid</th>
              <th>Payment Method</th>
              <th>Payment Reference</th>
            </tr>
         </thead>
         <tbody>`;
  data.forEach(dt => {
    html += `
              <tr>
                <td>${esc(dt.paymentDate)}</td>
                <td>${esc(dt.paymentNo)}</td>
                <td>${esc(dt.supplierName)}</td>
                <td>${esc(dt.invoiceNo)}</td>
                <td>${numberWithCommas(dt.amount)}</td>
                <td>${esc(dt.payMethod)}</td>
                <td>${esc(dt.paymentReference)}</td>
              </tr>
           `;
  });
  html += `</tbody>
    <tfoot>
      <tr>
          <th colspan="4" style="text-align:center">Total:</th>
          <th id="paid"></th>
          <th colspan="2"></th>
      </tr>
    </tfoot>
      </table>
    `;
  return html;
}
export function paymentBySupplier(data) {
  let html = `
      <table class="table table-striped table-bordered table-sm" id="invoicereport">
         <thead class="bg-lightblue">
            <tr>
              <th>Payment Date</th>
              <th>Payment No</th>
              <th>Invoice No</th>
              <th>Amount Paid</th>
              <th>Payment Method</th>
              <th>Payment Reference</th>
            </tr>
         </thead>
         <tbody>`;
  data.forEach(dt => {
    html += `
              <tr>
                <td>${esc(dt.paymentDate)}</td>
                <td>${esc(dt.paymentNo)}</td>
                <td>${esc(dt.invoiceNo)}</td>
                <td>${numberWithCommas(dt.amount)}</td>
                <td>${esc(dt.payMethod)}</td>
                <td>${esc(dt.paymentReference)}</td>
              </tr>
           `;
  });
  html += `</tbody>
    <tfoot>
      <tr>
          <th colspan="3" style="text-align:center">Total:</th>
          <th id="paid"></th>
          <th colspan="2"></th>
      </tr>
    </tfoot>
      </table>
    `;
  return html;
}
export function allPayments(data) {
  let html = `
  <table class="table table-striped table-bordered table-sm" id="invoicereport">
     <thead class="bg-lightblue">
        <tr>
          <th>Payment Date</th>
          <th>Payment No</th>
          <th>Supplier</th>
          <th>Invoice No</th>
          <th>Amount Paid</th>
          <th>Payment Reference</th>
        </tr>
     </thead>
     <tbody>`;
  data.forEach(dt => {
    html += `
          <tr>
            <td>${esc(dt.paymentDate)}</td>
            <td>${esc(dt.paymentNo)}</td>
            <td>${esc(dt.supplierName)}</td>
            <td>${esc(dt.invoiceNo)}</td>
            <td>${numberWithCommas(dt.amount)}</td>
            <td>${esc(dt.paymentReference)}</td>
          </tr>
       `;
  });
  html += `</tbody>
<tfoot>
  <tr>
      <th colspan="4" style="text-align:center">Total:</th>
      <th id="paid"></th>
      <th></th>
  </tr>
</tfoot>
  </table>
`;
  return html;
}
export function supplierBalances(data) {
  let html = `
      <table class="table table-striped table-bordered table-sm" id="invoicereport">
         <thead class="bg-lightblue">
            <tr>
              <th>Supplier Name</th>
              <th>Total Balance</th>
            </tr>
         </thead>
         <tbody>`;
  data.forEach(dt => {
    html += `
              <tr>
                <td>${esc(dt.supplierName)}</td>
                <td>${numberWithCommas(dt.TotalBalance)}</td>
              </tr>
           `;
  });

  html += `</tbody>
    <tfoot>
      <tr>
          <th style="text-align:center">Total:</th>
          <th id="bal"></th>
      </tr>
    </tfoot>
      </table>
    `;
  return html;
}
