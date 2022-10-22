export const HOST_URL = 'http://localhost/cms';
export const mandatoryFields = document.querySelectorAll('.mandatory');
export const alertBox = document.getElementById('alertBox');
//display alert
export function displayAlert(elm, message, status = 'danger') {
  const html = `
    <div class="alert custom-${status}" role="alert">
      ${message}
    </div>
  `;
  elm.insertAdjacentHTML('afterbegin', html);
  setTimeout(function () {
    elm.innerHTML = '';
  }, 5000);
}

//function to make http requests
export async function sendHttpRequest(
  url,
  method = 'GET',
  body = null,
  headers = {},
  alertBox = undefined
) {
  try {
    const res = await fetch(url, {
      method,
      body,
      headers,
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message);
    return data;
  } catch (error) {
    if (!alertBox) {
      alert(
        'There was a problem executing this command! Contact admin for help'
      );
      console.error(error.message);
    } else {
      displayAlert(alertBox, error.message);
    }
  }
}

export function validation() {
  let errorCount = 0;

  const mandatoryField = document.querySelectorAll('.mandatory');
  mandatoryField?.forEach(field => {
    if (!field.value || field.value == '') {
      field.classList.add('is-invalid');
      field.nextSibling.nextSibling.textContent = 'Field is required';
      errorCount++;
    }
  });

  return errorCount;
}

export function clearOnChange(mandatoryField) {
  mandatoryField?.forEach(field => {
    field.addEventListener('change', function () {
      field.classList.remove('is-invalid');
      field.nextSibling.nextSibling.textContent = '';
    });
  });
}

export function numberFormatter(number) {
  if (number.includes(',')) {
    return number.replaceAll(',', '');
  }
  return number;
}

export function getSelectedText(sel) {
  return sel.options[sel.selectedIndex].text;
}

export function numberWithCommas(x) {
  return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

//set loading spinner for buttons
export function setLoadingState(btn, text = 'loading') {
  btn.innerHTML = '';
  let html = `
    <div class="spinner-container">
    <div class="spinner"></div> 
    <span>${text}...</span> 
  </div>
    `;
  btn.innerHTML = html;
  btn.disabled = true;
}

//reset button to normal state
export function resetLoadingState(btn, btnText = 'Submit') {
  btn.disabled = false;
  btn.textContent = btnText;
}

//get totals - table
export function updateSubTotal(table, cell, elementType, element) {
  let sumVal = 0;
  for (var i = 1; i < table.rows.length; i++) {
    const rowValue = parseFloat(table.rows[i].cells[cell].innerText) || 0;
    sumVal = sumVal + rowValue;
  }

  if (elementType === 'input') {
    element.value = numberWithCommas(sumVal.toFixed(2));
  } else {
    element.innerText = numberWithCommas(sumVal.toFixed(2));
  }
}

export function validateDate(start, end) {
  if (new Date(start.value).getTime() > new Date(end.value).getTime()) {
    start.classList.add('is-invalid');
    start.nextSibling.nextSibling.textContent =
      'Date cannot be greather than end date';
    return false;
  }
  return true;
}

export function formatDate(date) {
  const providedDate = new Date(date);
  const yyyy = providedDate.getFullYear();
  let mm = providedDate.getMonth() + 1; // Months start at 0!
  let dd = providedDate.getDate();

  if (dd < 10) dd = '0' + dd;
  if (mm < 10) mm = '0' + mm;

  const formattedToday = dd + '/' + mm + '/' + yyyy;
  return formattedToday;
}

export function setdatatable(tbl, columnDefs = [], pageLength) {
  $(document).ready(function () {
    'use strict';
    var table = $(`#${tbl}`).DataTable();
    table.destroy();
    table = $(`#${tbl}`)
      .DataTable({
        lengthChange: !1,
        pageLength: pageLength || 25,
        buttons: ['print', 'excel', 'pdf'],
        columnDefs: columnDefs,
        ordering: false,
        drawCallback: function () {
          $('.dataTables_paginate > .pagination').addClass(
            'pagination-rounded'
          );
        },
      })
      .buttons()
      .container()
      .appendTo(`#${tbl}_wrapper .col-md-6:eq(0)`);
  });
}

export function getColumnTotal(table, cell) {
  let sumVal = 0;
  const tbody = table.getElementsByTagName('tbody')[0];
  for (var i = 1; i < tbody.rows.length + 1; i++) {
    const rowValue =
      parseFloat(numberFormatter(table.rows[i].cells[cell].innerText)) || 0;
    sumVal = sumVal + rowValue;
  }

  return numberWithCommas(sumVal.toFixed(2));
}

export function clearValues() {
  const inputs = document.querySelectorAll('.form-control');
  if (inputs.length > 0) {
    inputs.forEach(input => (input.value = ''));
  }
}
