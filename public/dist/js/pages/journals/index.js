import { removeLoadingSpinner, setLoadingSpinner } from '../reports/utils.js';
//prettier-ignore
import { clearOnChange, mandatoryFields, validation,setLoadingState,resetLoadingState, 
         displayAlert, alertBox } from '../utils/utils.js';
import { getJournalNo, saveEntries } from './ajax.js';
//prettier-ignore
import { addBtn, journalNoInput, form, saveBtn, table,currJouralInput } from './elements.js';
//prettier-ignore
import { addToTable, validate, formData ,removeSelected,clear} from './functionalities.js';

async function reset() {
  clear();
  const data = await getJournalNo();
  if (data && data.success) {
    journalNoInput.value = data.journalno;
    currJouralInput.value = data.journalno;
  }
}
//add btn click
addBtn.addEventListener('click', addToTable);

//form submit
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  //validations
  if (validation() > 0) return;
  if (!validate()) return;
  setLoadingState(saveBtn, 'Saving...');
  const res = await saveEntries(formData());
  resetLoadingState(saveBtn, 'Save');
  if (res && res.success) {
    displayAlert(alertBox, 'Saved successfully!', 'success');
    reset();
  }
});

//remove clicked row
table.addEventListener('click', function (e) {
  removeSelected(e);
});

reset();
clearOnChange(mandatoryFields);
