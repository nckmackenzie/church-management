import { removeLoadingSpinner, setLoadingSpinner } from '../reports/utils.js';
import { getJournalNo } from './ajax.js';
import { addBtn, journalNoInput } from './elements.js';
import { addToTable } from './functionalities.js';

async function reset() {
  const data = await getJournalNo();
  if (data && data.success) journalNoInput.value = data.journalno;
}

addBtn.addEventListener('click', addToTable);

reset();
