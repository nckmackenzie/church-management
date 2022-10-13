import { sendHttpRequest, HOST_URL, alertBox } from '../utils/utils.js';

//prettier-ignore
export async function invoiceReports(type,sdate=null,edate=null,criteria=null) {
    let url;
    if(type === 'balances'){
        url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}`
    }
    const res = await sendHttpRequest(url,'GET',undefined,{},alertBox);
    return await res
}

export async function getInvoiceNo() {
  const res = await sendHttpRequest(
    `${HOST_URL}/invoicereports/fetchinvoicenos`,
    'GET',
    undefined,
    {},
    alertBox
  );

  return res;
}
