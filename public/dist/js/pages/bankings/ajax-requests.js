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
