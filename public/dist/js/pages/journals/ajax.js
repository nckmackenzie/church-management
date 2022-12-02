import { HOST_URL } from '../utils/utils.js';
import { getRequest } from '../reports/utils.js';

export async function getJournalNo() {
  return await getRequest(`${HOST_URL}/journals/getjournalno`);
}
