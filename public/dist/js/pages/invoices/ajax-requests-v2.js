import { sendHttpRequest, HOST_URL, alertBox } from '../utils/utils.js';
import { modalAlert } from './supplier-v2.js';

export async function getSupplierDetails(supplier) {
  const data = await sendHttpRequest(
    `${HOST_URL}/supplierinvoices/fetchsupplierdetails?sid=${supplier}`,
    'GET',
    undefined,
    {},
    alertBox
  );
  return data;
}

export async function saveProduct(data) {
  const res = await sendHttpRequest(
    `${HOST_URL}/supplierinvoices/saveproduct`,
    'POST',
    JSON.stringify(data),
    { 'Content-Type': 'application/json' },
    modalAlert
  );
  return res;
}

// rate and debit account of a product of this congregation
export async function getProduct(pid) {
  const res = await sendHttpRequest(
    `${HOST_URL}/supplierinvoices/getproduct?pid=${pid}`,
    'GET',
    undefined,
    {},
    alertBox
  );
  return res;
}

export async function saveForm(data) {
  const res = await sendHttpRequest(
    `${HOST_URL}/supplierinvoices/createupdate`,
    'POST',
    JSON.stringify(data),
    { 'Content-Type': 'application/json' },
    alertBox
  );
  return res;
}
