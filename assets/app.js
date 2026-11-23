import { setupProduct } from './product.js';
import { setupOrder } from './order.js';
import { setFieldset } from './fields.js';
import { setupWorkspace } from './workspace.js?v=3';
import { setupBilling } from './billing.js';

const material = document.querySelector('[data-material-form]');
if (material) {
  const selector = material.elements.material;
  const update = () => {
    for (const fieldset of material.querySelectorAll('fieldset[data-kind]')) {
      setFieldset(fieldset, selector.selectedOptions[0].dataset.kind === fieldset.dataset.kind);
    }
  };
  selector.addEventListener('change', update);
  update();
}

const product = document.querySelector('[data-product-form]');
if (product) setupProduct(product);
const order = document.querySelector('[data-order-form]');
if (order) setupOrder(order);
const billing = document.querySelector('[data-billing-form]');
if (billing) setupBilling(billing);

setupWorkspace();
