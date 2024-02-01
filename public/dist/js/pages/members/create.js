import { clearOnChange, mandatoryFields, validation } from '../utils/utils.js';

const form = document.querySelector('form');

let calculateAge = function (birthday) {
  let now = new Date();
  let past = new Date(birthday);
  let nowYear = now.getFullYear();
  let pastYear = past.getFullYear();
  let age = nowYear - pastYear;

  return age;
};

$('#dob').change(function () {
  var $birthday = $('#dob').val();
  // alert('Your age is ' + calculateAge($birthday) + ' years');
  var years = calculateAge($birthday);
  if (years < 18) {
    $('#idno').attr('disabled', true);
    $('#maritalstatus').attr('disabled', true);
    $('#marriagetype').attr('disabled', true);
    $('#marriagedate').attr('disabled', true);
    $('#idno').val('');
    $('#maritalstatus').val('');
    $('#marriagetype').val('');
  } else {
    $('#idno').attr('disabled', false);
    $('#maritalstatus').attr('disabled', false);
  }
});

$('#maritalstatus').on('change', function () {
  if ($(this).val() == '2') {
    $('#marriagetype').attr('disabled', false);
    $('#marriagedate').attr('disabled', false);
  } else {
    $('#marriagetype').attr('disabled', true);
    $('#marriagedate').attr('disabled', true);
    $('#marriagetype option:selected').prop('selected', false);
  }
});
$('#status').on('change', function () {
  if ($(this).val() == '4') {
    $('#passeddate').attr('disabled', false);
  } else {
    $('#passeddate').attr('disabled', true);
  }
});

$('#membershipstatus').on('change', function () {
  if ($(this).val() == '1') {
    $('#confirmed').attr('disabled', false);
    $('#commissioned').attr('disabled', false);
  } else {
    $('#confirmed').attr('disabled', true);
    $('#commissioned').attr('disabled', true);
    $('#confirmeddate').attr('disabled', true);
    $('#commissioneddate').attr('disabled', true);
  }
});

$('#confirmed').on('change', function () {
  if ($(this).val() == '1') {
    $('#confirmeddate').attr('disabled', false);
  } else {
    $('#confirmeddate').attr('disabled', true);
  }
});

$('#commissioned').on('change', function () {
  if ($(this).val() == '1') {
    $('#commissioneddate').attr('disabled', false);
  } else {
    $('#commissioneddate').attr('disabled', true);
  }
});

$('#baptised').on('change', function () {
  if ($(this).val() == '1') {
    $('#baptiseddate').attr('disabled', false);
  } else {
    $('#baptiseddate').attr('disabled', true);
  }
});

$('#occupation').on('change', function () {
  if ($(this).val() == 'OTHER') {
    $('#other').attr('readonly', false);
    $('#other').focus();
  } else {
    $('#other').attr('readonly', true);
  }
});

form.addEventListener('submit', e => {
  e.preventDefault();
  if (validation() > 0) return;

  form.submit();
});

clearOnChange(mandatoryFields);
