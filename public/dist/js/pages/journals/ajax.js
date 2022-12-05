import { alertBox, HOST_URL, sendHttpRequest } from '../utils/utils.js';
import { getRequest } from '../reports/utils.js';

export async function getJournalNo() {
  return await getRequest(`${HOST_URL}/journals/getjournalno`);
}

export async function saveEntries(formData) {
  const url = `${HOST_URL}/journals/createupdate`;
  const res = await sendHttpRequest(
    url,
    'POST',
    JSON.stringify(formData),
    {
      'Content-Type': 'application/json',
    },
    alertBox
  );
  return res;
}

export async function getJournalEntry(journalNo) {
  const url = `${HOST_URL}/journals/getjournalentry?journalno=${+journalNo}`;
  return await getRequest(url);
}
