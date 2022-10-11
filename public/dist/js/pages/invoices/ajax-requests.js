import { sendHttpRequest, HOST_URL, alertBox } from '../utils/utils.js';

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
