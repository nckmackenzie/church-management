import { table } from './payment-v2.js';
const payDateInput = document.getElementById('date');
const paymethodSelect = document.getElementById('paymethod');
const bankSelect = document.getElementById('bank');
const cashSelect = document.getElementById('cashaccount');

export const headerData = () => {
  return {
    paydate: payDateInput.value,
    paymethod: paymethodSelect.value,
    bank: bankSelect.disabled ? '' : bankSelect.value,
    cashaccount: cashSelect.disabled ? '' : cashSelect.value,
  };
};

// the server reads each invoice's balance and crediting account itself; only the invoice, reference and amount are sent
export const tableData = () => {
  const tableData = [];
  const trs = table.getElementsByTagName('tbody')[0].querySelectorAll('tr');
  trs.forEach(tr => {
    const chkbx = tr.querySelector('.chkbx');
    if (chkbx.checked) {
      const invoiceid = tr.querySelector('.invoiceid').value;
      const cheque = tr.querySelector('.cheque').value;
      const payment = tr.querySelector('.payment').value;
      tableData.push({ invoiceid, cheque, payment });
    }
  });

  return tableData;
};
