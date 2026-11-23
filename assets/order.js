import { formatMoney } from './fields.js';

function lineTotal(line) {
  const product = line.querySelector('select[name="product[]"]').selectedOptions[0];
  const channel = line.querySelector('select[name="channel[]"]');
  channel.options[1].disabled = !product.dataset.wholesale;
  if (channel.options[channel.selectedIndex].disabled) channel.value = 'retail';
  const quantity = line.querySelector('input[name="quantity[]"]');
  const total = quantity.validity.valid ? Number(product.dataset[channel.value]) * Number(quantity.value) : 0;
  line.querySelector('[data-line-price]').textContent = formatMoney(total);
  return total;
}

export function setupOrder(form) {
  const lines = form.querySelector('[data-order-lines]');
  const template = form.querySelector('[data-order-line]');
  const add = form.querySelector('[data-add-line]');
  function update() {
    const rows = [...lines.children];
    const total = rows.reduce((amount, row) => amount + lineTotal(row), 0);
    const deposit = Math.ceil(total / 2);
    form.querySelector('[data-order-total]').textContent = formatMoney(total);
    form.querySelector('[data-order-deposit]').textContent = formatMoney(deposit);
    form.querySelector('[data-order-balance]').textContent = formatMoney(total - deposit);
    form.elements.expected_total.value = total;
    form.querySelector('[data-send-order]').disabled = total < 2 || total > 1e12;
    add.disabled = rows.length >= 30;
    for (const row of rows) row.querySelector('[data-remove-line]').disabled = rows.length === 1;
  }
  function addLine() {
    lines.append(template.content.cloneNode(true));
    update();
  }
  add.addEventListener('click', addLine);
  lines.addEventListener('input', update);
  lines.addEventListener('click', event => {
    const remove = event.target.closest('[data-remove-line]');
    if (remove && lines.children.length > 1) {
      remove.closest('fieldset').remove();
      update();
    }
  });
  addLine();
}
