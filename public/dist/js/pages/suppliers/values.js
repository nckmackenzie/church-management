import { table } from './payment.js';
const payIdInput = document.getElementById('payid');
const payDateInput = document.getElementById('date');
const paymethodSelect = document.getElementById('paymethod');
const bankSelect = document.getElementById('bank');

export const headerData = () => {
  return {
    payid: payIdInput.value,
    paydate: payDateInput.value,
    paymethod: paymethodSelect.value,
    bank: bankSelect.value,
  };
};

export const tableData = () => {
  const tableData = [];
  const trs = table.getElementsByTagName('tbody')[0].querySelectorAll('tr');
  trs.forEach(tr => {
    const chkbx = tr.querySelector('.chkbx');
    if (chkbx.checked) {
      const invoiceid = tr.querySelector('.invoiceid').value;
      const sid = tr.querySelector('.sid').value;
      const cheque = tr.querySelector('.cheque').value;
      const balance = parseFloat(tr.querySelector('.balance').innerText);
      const payment = parseFloat(tr.querySelector('.payment').value);
      tableData.push({ invoiceid, sid, cheque, payment, balance });
    }
  });

  return tableData;
};
