<?php
$products = $db->query('SELECT id,name,size,retail,wholesale FROM products ORDER BY name,id')->fetchAll();
$customers = $db->query('SELECT id,name,phone FROM customers ORDER BY name,id')->fetchAll();
?>
<a href="?page=<?= e($page) ?>">← <?= $page === 'sales' ? 'Ventas' : 'Pedidos' ?></a>
<header class="page-header"><h1>Nueva venta</h1></header>
<?php if (!$products): ?>
  <p class="notice">El catálogo todavía no tiene productos disponibles.</p>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="columns" data-order-form>
  <?php hidden('order'); ?>
  <input type="hidden" name="expected_total">
  <section class="panel stack">
    <h2>Detalle del pedido</h2>
    <label>Cliente<select name="customer_id" required>
      <option value="">Selecciona un cliente</option>
      <?php foreach ($customers as $customer): ?>
        <option value="<?= $customer['id'] ?>"><?= e($customer['name'] . ($customer['phone'] ? ' · ' . $customer['phone'] : '')) ?></option>
      <?php endforeach; ?>
    </select></label>
    <?php if (!$customers): ?><p>Primero registra el cliente en el módulo Clientes.</p><?php endif; ?>
    <div class="stack" data-order-lines></div>
    <div><button type="button" class="secondary" data-add-line>Añadir producto</button></div>
    <template data-order-line>
      <fieldset class="order-line">
        <label>Producto<select name="product[]" required>
          <?php foreach ($products as $product): ?>
            <option value="<?= $product['id'] ?>" data-retail="<?= $product['retail'] ?>" data-wholesale="<?= e($product['wholesale']) ?>"><?= e($product['name'] . ' · ' . $product['size']) ?></option>
          <?php endforeach; ?>
        </select></label>
        <label>Precio<select name="channel[]"><option value="retail">Minorista</option><option value="wholesale">Mayorista</option></select></label>
        <label>Cantidad<input name="quantity[]" type="number" min="1" max="10000" value="1" required></label>
        <div class="line-end"><output data-line-price></output><button class="secondary small" type="button" data-remove-line>Quitar</button></div>
      </fieldset>
    </template>
  </section>
  <section class="panel stack">
    <h2>Comprobante de la seña</h2>
    <dl class="totals" aria-live="polite">
      <div><dt>Total</dt><dd data-order-total>—</dd></div>
      <div><dt>Seña (50 %)</dt><dd data-order-deposit>—</dd></div>
      <div><dt>Saldo</dt><dd data-order-balance>—</dd></div>
    </dl>
    <label>Transferencia bancaria (PDF, JPG o PNG · hasta 5 MB)<input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png" required></label>
    <button data-send-order>Enviar a revisión</button>
  </section>
</form>
<?php endif; ?>
