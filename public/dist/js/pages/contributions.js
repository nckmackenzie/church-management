const addButton = document.querySelector('.btnadd');
const accountSelect = document.getElementById('account');
const amountInput = document.getElementById('amount');
const categorySelect = document.getElementById('category');
const contributorSelect = document.getElementById('contributor');
const table = document.getElementById('contributions-table');
const body = table.getElementsByTagName('tbody')[0];
const form = document.querySelector('form');

function getSelectedText(sel) {
  return sel.options[sel.selectedIndex].text;
}

//add to table
addButton.addEventListener('click', () => {
  const account = Number(accountSelect.value);
  const accountName = $('#account').find('option:selected').text().trim();
  const amount = Number(amountInput.value);
  const category = Number(categorySelect.value);
  const categoryName = getSelectedText(categorySelect);
  const contributor = Number(contributorSelect.value);
  const contributorName = $('#contributor')
    .find('option:selected')
    .text()
    .trim();

  if (!amount || !account || !category || !contributor) {
    alert('Select all required fields');
    return;
  }
  let html = `
      <tr>
        <td class="d-none"><input type="text" name="accountsid[]" value="${account}"></td>
        <td><input type="text" class="table-input" name="accountsname[]" value="${accountName}" readonly></td>
        <td><input type="text" class="table-input" name="amounts[]" value="${amount}" readonly></td>
        <td class="d-none"><input type="text" class="table-input" name="categoriesid[]" value="${category}"></td>
        <td><input type="text" class="table-input" name="categoriesname[]" value="${categoryName}" readonly></td>
        <td class="d-none"><input type="text" class="table-input" name="contributorsid[]" value="${contributor}"></td>
        <td><input type="text" class="table-input" name="contributorsname[]" value="${contributorName}" readonly></td>
        <td><button type="button" class="action-icon btn btn-sm text-danger fs-5 btndel">Remove</button></td>
      </tr>
  `;
  let newRow = body.insertRow(body.rows.length);
  newRow.innerHTML = html;
  amountInput.value = '';
  $('#contributor').val('1'); // Select the option with a value of '1'
  $('#contributor').trigger('change');
});

table.addEventListener('click', function (e) {
  if (!e.target.classList.contains('btndel')) return;
  const btn = e.target;
  btn.closest('tr').remove();
});

form.addEventListener('submit', e => {
  e.preventDefault();
  if (Number(body.rows.length) === 0) {
    alert('No contributions added');
    return false;
  } else {
    document.form.submit();
  }
});
