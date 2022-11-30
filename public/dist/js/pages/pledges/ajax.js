import { alertBox, HOST_URL, sendHttpRequest } from '../utils/utils.js';
import { getRequest } from '../reports/utils.js';

export async function getPledgers(category) {
  const url = `${HOST_URL}/pledges/getpledger?category=${category}`;
  return await getRequest(url);
}

export async function createPledge(formData) {
  const res = await sendHttpRequest(
    `${HOST_URL}/pledges/create`,
    'POST',
    JSON.stringify(formData),
    { 'Content-Type': 'application/json' },
    alertBox
  );

  return res;
}
