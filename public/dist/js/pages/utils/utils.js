export const HOST_URL = 'http://localhost/cms';

//function to make http requests
export async function sendHttpRequest(
  url,
  method = 'GET',
  body = null,
  headers = {}
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
    alert('There was a problem executing this command! Contact admin for help');
    console.error(error.message);
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
