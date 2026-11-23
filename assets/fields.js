const currency = new Intl.NumberFormat('es-PY', { maximumFractionDigits: 0 });

export function formatMoney(value) {
  return 'Gs. ' + currency.format(Math.round(value));
}

export function setFieldset(fieldset, visible) {
  fieldset.hidden = !visible;
  fieldset.disabled = !visible;
}
