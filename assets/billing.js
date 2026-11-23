import { formatMoney } from './fields.js';

export function setupBilling(form) {
  const lines = form.querySelector('[data-billing-lines]');
  const template = form.querySelector('[data-billing-line]');
  const search = form.querySelector('[data-billing-search]');
  const products = [...form.querySelectorAll('[data-product-row]')];

  function update() {
    let total = 0;
    for (const row of lines.rows) {
      const channel = row.querySelector('[name="channel[]"]').value;
      const quantity = row.querySelector('[name="quantity[]"]');
      const amount = quantity.validity.valid ? Number(quantity.value) * Number(row.dataset[channel]) : 0;
      row.querySelector('[data-line-total]').textContent = formatMoney(amount);
      total += amount;
    }
    const deposit = Math.ceil(total / 2);
    form.elements.expected_total.value = total;
    form.querySelector('[data-total]').textContent = formatMoney(total);
    form.querySelector('[data-deposit]').textContent = formatMoney(deposit);
    form.querySelector('[data-balance]').textContent = formatMoney(total - deposit);
    form.querySelector('[data-billing-empty]').hidden = lines.rows.length > 0;
    form.querySelector('[data-submit]').disabled = total < 2 || total > 1e12;
    form.querySelectorAll('[data-add-product]').forEach(button => {
      button.disabled = lines.rows.length >= 30;
    });
  }

  form.addEventListener('click', event => {
    const button = event.target.closest('[data-add-product]');
    if (button) {
      const row = template.content.firstElementChild.cloneNode(true);
      row.dataset.retail = button.dataset.retail;
      row.dataset.wholesale = button.dataset.wholesale;
      row.querySelector('[data-name]').textContent = button.dataset.name;
      row.querySelector('[name="product[]"]').value = button.dataset.id;
      row.querySelector('[name="channel[]"]').options[1].disabled = !button.dataset.wholesale;
      lines.append(row);
      update();
    }
    const remove = event.target.closest('[data-remove]');
    if (remove) {
      remove.closest('tr').remove();
      update();
    }
  });
  form.addEventListener('input', update);
  form.addEventListener('change', update);
  search.addEventListener('input', () => {
    const term = search.value.trim().toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    let visible = 0;
    for (const row of products) {
      const text = row.dataset.search.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      row.hidden = !text.includes(term);
      if (!row.hidden) visible++;
    }
    form.querySelector('[data-search-empty]').hidden = visible > 0 || products.length === 0;
  });
}
