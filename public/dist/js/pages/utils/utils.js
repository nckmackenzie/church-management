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
