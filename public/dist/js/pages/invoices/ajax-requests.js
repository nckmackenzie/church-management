import { sendHttpRequest, HOST_URL, alertBox } from '../utils/utils.js';
import { modalAlert } from './supplier.js';

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
