// prettier-ignore
import { clearOnChange, validation, mandatoryFields,validateDate, numberWithCommas,
    displayAlert,alertBox,setLoadingState,resetLoadingState } from '../utils/utils.js';
// prettier-ignore
import {clearForm, addBtn,fromDateInput,toDateInput,bankSelect,despositsInput,
    withdrawalsInput,table,balanceInput,saveBtn } from './elements.js';
//prettier-ignore
import { clearBankings, getBankings } from './ajax-requests.js';
//prettier-ignore
import {setLoadingSpinner,removeLoadingSpinner,appendData, calculateVariance,
    tableData,
    clear} from './functionalities.js'

let initialDeposits = 0;
let InitialWidthrawals = 0;
let dataTable;

//fetch details
addBtn.addEventListener('click', async function () {
  if (validation() > 0) return;

  if (!validateDate(fromDateInput, toDateInput)) return;

  if ($.fn.DataTable.isDataTable('#clear-banking')) {
    dataTable.clear().draw(); // Clear existing data
    dataTable.destroy();
  } else {
    table.getElementsByTagName('tbody')[0].innerHTML = '';
  }

  // table.getElementsByTagName('tbody')[0].innerHTML = '';
  const fromValue = fromDateInput.value;
  const toValue = toDateInput.value;
  const bankValue = bankSelect.value;
  setLoadingSpinner();
  const data = await getBankings(bankValue, fromValue, toValue);
  removeLoadingSpinner();
  if (data) {
    const { values, bankings } = data;
    initialDeposits = values.debits;
    InitialWidthrawals = values.credits;
    despositsInput.value = numberWithCommas(values.debits);
    withdrawalsInput.value = numberWithCommas(values.credits);

    appendData(bankings);
    setDatatable();
    calculateVariance();
  }
});
function setDatatable() {
  // if ($.fn.DataTable.isDataTable('#clear-banking')) {
  //   dataTable.destroy(); // Destroy existing instance if present
  // }
  dataTable = $('#clear-banking').DataTable({
    columnDefs: [
      { targets: 0, visible: false }, // Hide the ID column
    ],
  });
}

function updateDatatableSubTotal(table, initialDeposits, initialWithdrawals) {
  let depositsTotal = 0;
  let withdrawalTotal = 0;

  table.rows().every(function () {
    var row = this.node();

    if ($(row).find('td:eq(0) input[type="checkbox"]').is(':checked')) {
      // console.log('got here');
      let value =
        parseFloat($(row).find('td:eq(3)').text().replace(',', '')) || 0;
      // console.log(value);
      if (value > 0) {
        depositsTotal += value;
      } else if (value < 0) {
        withdrawalTotal += value;
      }
    }
  });

  const totalDeposits = depositsTotal + initialDeposits;
  const totalWithdrawals = withdrawalTotal * -1 + initialWithdrawals;

  despositsInput.value = numberWithCommas(totalDeposits.toFixed(2));
  withdrawalsInput.value = numberWithCommas(totalWithdrawals.toFixed(2));
}

$('#clear-banking tbody').on('change', '.select-row', function () {
  var $row = $(this).closest('tr');
  var $dateInput = $row.find('.clear-date');

  if (this.checked) {
    $dateInput.prop('disabled', false);
  } else {
    $dateInput.prop('disabled', true).val('');
  }

  updateDatatableSubTotal(dataTable, initialDeposits, InitialWidthrawals);
  calculateVariance();
});

balanceInput.addEventListener('blur', calculateVariance);

clearForm.addEventListener('submit', async function (e) {
  e.preventDefault();

  let checkedRows = 0;
  let allDatesSelected = true;

  dataTable.rows().every(function () {
    var row = this.node();
    var $row = $(row);
    var isChecked = $row.find('td:eq(0) input[type="checkbox"]').is(':checked');
    var hasDate = $row.find('td:eq(2) input[type="date"]').val();

    if (isChecked) {
      checkedRows++;
      if (!hasDate) {
        allDatesSelected = false;
      }
    }
  });

  if (checkedRows === 0) {
    displayAlert(
      alertBox,
      'You must select at least one transaction for clearing.'
    );
    return;
  }

  if (!allDatesSelected) {
    displayAlert(alertBox, 'Each selected transaction must have a clear date.');
    return;
  }

  const selectedRows = [];

  dataTable.$('input[type="checkbox"]:checked').each(function () {
    var $row = $(this).closest('tr');
    var rowData = dataTable.row($row).data();

    var selectedRow = {
      id: rowData[0], // ID (hidden column)
      txnDate: rowData[2], // Txn Date
      clearDate: $row.find('.clear-date').val(), // Clear Date (input value)
    };

    selectedRows.push(selectedRow);
  });

  const formData = { table: selectedRows };
  setLoadingState(saveBtn, 'Saving...');
  const data = await clearBankings(formData);
  resetLoadingState(saveBtn, 'Clear selected');
  if (data && data?.success) {
    displayAlert(
      alertBox,
      'Successfully cleared all selected transactions',
      'success'
    );
    clear();
  }
});

clearOnChange(mandatoryFields);
