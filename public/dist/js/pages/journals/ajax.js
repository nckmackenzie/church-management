import { HOST_URL } from '../utils/utils.js';
import { getRequest } from '../reports/utils.js';

export async function getJournalNo() {
  return await getRequest(`${HOST_URL}/journals/getjournalno`);
}

export async function saveEntries(formData) {
  const url = `${HOST_URL}/journals/createupdate`;
  // not sendHttpRequest: its generic "Contact admin" alert would hide the server's validation message
  try {
    const res = await fetch(url, {
      method: 'POST',
      body: JSON.stringify(formData),
      headers: { 'Content-Type': 'application/json' },
    });
    const data = await res.json();
    if (!res.ok) return { success: false, message: data.message };
    return data;
  } catch (error) {
    console.error(error.message);
    return {
      success: false,
      message: 'There was a problem saving the journal. Contact admin for help',
    };
  }
}

export async function getJournalEntry(journalNo) {
  const url = `${HOST_URL}/journals/getjournalentry?journalno=${+journalNo}`;
  return await getRequest(url);
}
