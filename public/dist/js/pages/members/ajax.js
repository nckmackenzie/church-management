import { HOST_URL } from '../utils/utils.js';
const fullUrl = `${HOST_URL}/transfers`;
export async function ajaxCall(url) {
  try {
    const res = await fetch(url);
    return await res.json();
  } catch (error) {
    console.error(error.message);
  }
}

export async function getDistricts(cid) {
  let option = '<option selected disabled>Select district...</option>';
  const type = 'districts';
  const url = `${fullUrl}/getvalues?type=${type}&cong=${cid}`;
  const data = await ajaxCall(url);
  data.forEach(dt => {
    option = option + `<option value="${dt.id}">${dt.fieldName}</option>`;
  });
  return option;
}

export async function getMembers(did) {
  let option = '';
  const type = 'members';
  const url = `${fullUrl}/getvalues?type=${type}&district=${did}`;
  const data = await ajaxCall(url);
  data.forEach(dt => {
    option = option + `<option value="${dt.id}">${dt.fieldName}</option>`;
  });
  return option;
}
