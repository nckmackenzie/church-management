export const HOST_URL = 'http://localhost/cms';
export const mandatoryFields = document.querySelectorAll('.mandatory');
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
