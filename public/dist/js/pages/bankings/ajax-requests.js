// prettier-ignore
import { sendHttpRequest,HOST_URL,alertBox } from '../utils/utils.js';

export async function getBankings(bank, start, end) {
  const res = await sendHttpRequest(
    `${HOST_URL}/clearbankings/getbankings?bank=${bank}&from=${start}&to=${end}`,
    'GET',
    undefined,
    {},
    alertBox
  );

  return res;
}

export async function clearBankings(data) {
  const res = await sendHttpRequest(
    `${HOST_URL}/clearbankings/clear`,
    'POST',
    JSON.stringify(data),
    { 'Content-Type': 'application/json' },
    alertBox
  );

  return res;
}
