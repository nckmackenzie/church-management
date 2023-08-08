import { sendHttpRequest, HOST_URL, alertBox } from '../utils/utils.js';
import { getRequest } from './utils.js';

//prettier-ignore
export async function invoiceReports(type,criteria=null,sdate=null,edate=null) {
    let url;
    if(type === 'balances' || type === 'supplierbalances'){
        url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}`
    }else if(type === 'byinvoice'){
      url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}&criteria=${criteria}`
    }else if(type === 'bysupplier'){
      url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}&criteria=${criteria}&sdate=${sdate}&edate=${edate}`;
    }else if(type === 'all'){
      url = `${HOST_URL}/invoicereports/getinvoicereport?type=${type}&sdate=${sdate}&edate=${edate}`;
    }
    const res = await getRequest(url);
    return await res
}

export async function getSelectOptions(type) {
  const res = await getRequest(
    `${HOST_URL}/invoicereports/fetchselectoptions?type=${type}`
  );

  return res;
}

//fetch trail balance data
export async function getTrialBalance(type, sdate, edate) {
  const res = await getRequest(
    `${HOST_URL}/trialbalance/getreport?type=${type}&sdate=${sdate}&edate=${edate}`
  );

  return res;
}

export async function getTrialBalanceReport(type, account, sdate, edate) {
  const url = `${HOST_URL}/trialbalance/detailedreport?type=${type}&account=${account}&sdate=${sdate}&edate=${edate}`;
  const res = await getRequest(url);

  return res;
}

export async function getPlDetailed(account, sdate, edate) {
  const url = `${HOST_URL}/reports/pldetailedrpt?account=${account}&sdate=${sdate}&edate=${edate}`;
  return await getRequest(url);
}

export async function getRequisitions(group) {
  const url = `${HOST_URL}/expenses/getrequisitions?group=${group}`;
  return await getRequest(url);
}

export async function getGroupPlExpenseDetailed(account, sdate, edate, group) {
  const url = `${HOST_URL}/reports/groupplexpensedetailedrpt?account=${account}&sdate=${sdate}&edate=${edate}&group=${group}`;
  return await getRequest(url);
}

export async function getGroupPlRevenueDetailed(type, sdate, edate, group) {
  const url = `${HOST_URL}/reports/groupplrevenuedetailedrpt?type=${type}&sdate=${sdate}&edate=${edate}&group=${group}`;
  return await getRequest(url);
}
