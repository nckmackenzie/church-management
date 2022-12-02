import { removeLoadingSpinner, setLoadingSpinner } from '../reports/utils.js';
import { getJournalNo } from './ajax.js';
import { journalNo } from './elements.js';

async function reset() {
  const data = await getJournalNo();
  if (data && data.success) journalNo.value = data.journalno;
}

reset();
