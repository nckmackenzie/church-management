//prettier-ignore
import { clearOnChange, mandatoryFields, validation,setLoadingState,resetLoadingState, 
         displayAlert, alertBox } from '../utils/utils.js';
import { getJournalNo, saveEntries } from './ajax.js';
//prettier-ignore
import { addBtn, journalNoInput, form, saveBtn, table,currJouralInput,firstJouralInput,
         searchInput,searchBtn ,deleteBtn,userTypeInput} from './elements.js';
//prettier-ignore
import { addToTable, validate, formData ,removeSelected,clear,getJournal} from './functionalities.js';

async function reset() {
  clear();
  const data = await getJournalNo();
  if (data && data.success) {
    journalNoInput.value = data.journalno;
    currJouralInput.value = data.journalno;
    firstJouralInput.value = data.firstno;
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

//incase enter is pressed on search key
searchInput.addEventListener('keypress', function (e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    getJournal(+e.target.value);
  }
});

//search
searchBtn.addEventListener('click', function () {
  if (searchInput.value === '') return;
  getJournal(+searchInput.value);
});

//remove clicked row
table.addEventListener('click', function (e) {
  removeSelected(e);
});

if (+userTypeInput.value < 3) {
  deleteBtn.addEventListener('click', function () {});
}

reset();
clearOnChange(mandatoryFields);
