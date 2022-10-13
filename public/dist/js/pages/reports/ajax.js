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
