import { sendHttpRequest, HOST_URL, alertBox } from '../utils/utils.js';

//prettier-ignore
export async function invoiceReports(type,criteria=null,sdate=null,edate=null) {
    let url;
    if(type === 'balances'){
        url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}`
    }else if(type === 'byinvoice'){
      url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}&criteria=${criteria}`
    }else if(type === 'bysupplier'){
      url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}&criteria=${criteria}&sdate=${sdate}&edate=${edate}`;
    }else if(type === 'all'){
      url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}&sdate=${sdate}&edate=${edate}`;
    }
    const res = await sendHttpRequest(url,'GET',undefined,{},alertBox);
    return await res
}

export async function getSelectOptions(type) {
  const res = await sendHttpRequest(
    `${HOST_URL}/invoicereports/fetchselectoptions?type=${type}`,
    'GET',
    undefined,
    {},
    alertBox
  );

  return res;
}
