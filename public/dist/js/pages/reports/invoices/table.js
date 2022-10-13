import { numberWithCommas } from '../../utils/utils.js';
export function withBalancesTable(data) {
  let html = `
      <table class="table table-striped table-bordered table-sm" id="invoicereport">
         <thead>
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
                <td>${dt.supplierName}</td>
                <td>${dt.invoiceNo}</td>
                <td>${dt.invoiceDate}</td>
                <td>${dt.dueDate}</td>
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
         <thead>
            <tr>
              <th>Payment Date</th>
              <th>Payment No</th>
              <th>Amount Paid</th>
              <th>Payment Method</th>
              <th>Payment Reference</th>
            </tr>
         </thead>
         <tbody>`;
  data.forEach(dt => {
    html += `
              <tr>
                <td>${dt.paymentDate}</td>
                <td>${dt.paymentNo}</td>
                <td>${numberWithCommas(dt.amount)}</td>
                <td>${dt.payMethod}</td>
                <td>${dt.paymentReference}</td>
              </tr>
           `;
  });
  html += `</tbody>
    <tfoot>
      <tr>
          <th colspan="2" style="text-align:center">Total:</th>
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
         <thead>
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
                <td>${dt.paymentDate}</td>
                <td>${dt.paymentNo}</td>
                <td>${dt.invoiceNo}</td>
                <td>${numberWithCommas(dt.amount)}</td>
                <td>${dt.payMethod}</td>
                <td>${dt.paymentReference}</td>
              </tr>
           `;
  });
  html += `</tbody>
    <tfoot>
      <tr>
          <th colspan="2" style="text-align:center">Total:</th>
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
     <thead>
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
            <td>${dt.paymentDate}</td>
            <td>${dt.paymentNo}</td>
            <td>${dt.supplierName}</td>
            <td>${dt.invoiceNo}</td>
            <td>${numberWithCommas(dt.amount)}</td>
            <td>${dt.paymentReference}</td>
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
