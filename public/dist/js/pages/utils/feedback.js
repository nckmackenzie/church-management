// Field feedback for the supplier screens (task 8.7). It finds the feedback span through the form group instead of
// utils.js's double next-sibling walk, which throws when the span is the very next node (the 8.6b pledge bug).
// utils.js itself is shared by many screens and is left unchanged.

export function feedback(field) {
  return field.closest('.form-group')?.querySelector('.invalid-feedback');
}

export function clearError(field) {
  field.classList.remove('is-invalid');
  const message = feedback(field);
  if (message) message.textContent = '';
}

export function setError(field, text) {
  field.classList.add('is-invalid');
  const message = feedback(field);
  if (message) message.textContent = text;
}

/** Marks every empty field of the selector and returns how many were empty. */
export function validateRequired(selector = '.mandatory') {
  let errors = 0;
  document.querySelectorAll(selector).forEach(field => {
    clearError(field);
    if (!field.value) {
      setError(field, 'Field is required');
      errors++;
    }
  });
  return errors;
}

/** Clears a field's error as soon as it changes. */
export function clearOnChange(fields) {
  fields?.forEach(field => {
    field.addEventListener('change', () => clearError(field));
    field.addEventListener('input', () => clearError(field));
  });
}

export function escapeHtml(value) {
  return String(value ?? '').replace(
    /[&<>"']/g,
    c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]
  );
}
