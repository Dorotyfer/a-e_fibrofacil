<?php
$products = $db->query('SELECT id,name,size,category,retail,wholesale FROM products ORDER BY name,id')->fetchAll();
$customers = $db->query('SELECT id,name,phone FROM customers ORDER BY name,id')->fetchAll();
?>
<header class="page-header"><h1>Facturación</h1></header>
<form method="post" enctype="multipart/form-data" class="billing stack" data-billing-form>
  <?php hidden('order'); ?>
  <input type="hidden" name="origin" value="billing">
  <input type="hidden" name="expected_total" value="0">
  <section class="panel billing-client">
    <h2>Cliente</h2>
    <label>Seleccionar cliente<select name="customer_id" required>
      <option value="">Selecciona un cliente</option>
      <?php foreach ($customers as $customer): ?>
        <option value="<?= $customer['id'] ?>"><?= e($customer['name'] . ($customer['phone'] ? ' · ' . $customer['phone'] : '')) ?></option>
      <?php endforeach; ?>
    </select></label>
    <?php if (!$customers): ?><p class="muted">Registra un cliente en el módulo Clientes para continuar.</p><?php endif; ?>
  </section>
  <section class="panel">
    <div class="section-header"><h2>Productos disponibles</h2>
      <label>Buscar producto<input type="search" data-billing-search placeholder="Nombre, categoría o tamaño"></label>
    </div>
    <div class="table-wrap billing-list"><table data-billing-table>
      <thead><tr><th>Producto</th><th>Categoría</th><th>Tamaño</th><th>Minorista</th><th>Mayorista</th><th></th></tr></thead>
      <tbody>
        <?php if (!$products) emptyRow(6, 'Todavía no hay productos disponibles.'); ?>
        <?php foreach ($products as $product): ?>
          <tr data-product-row data-search="<?= e($product['name'] . ' ' . $product['category'] . ' ' . $product['size']) ?>">
            <td><?= e($product['name']) ?></td><td><?= e($product['category']) ?></td><td><?= e($product['size']) ?></td>
            <td><?= guaranies($product['retail']) ?></td><td><?= $product['wholesale'] === null ? '—' : guaranies($product['wholesale']) ?></td>
            <td><button type="button" class="secondary small" data-add-product data-id="<?= $product['id'] ?>"
              data-name="<?= e($product['name'] . ' · ' . $product['size']) ?>" data-retail="<?= $product['retail'] ?>"
              data-wholesale="<?= e($product['wholesale']) ?>">Añadir</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="muted" data-search-empty hidden>No se encontraron productos.</p>
  </section>
  <section class="panel">
    <h2>Detalle de la venta</h2>
    <div class="table-wrap"><table data-billing-table>
      <thead><tr><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Importe</th><th></th></tr></thead>
      <tbody data-billing-lines></tbody>
    </table></div>
    <p class="muted" data-billing-empty>Añade productos para preparar la venta.</p>
    <template data-billing-line>
      <tr>
        <td><span data-name></span><input type="hidden" name="product[]"></td>
        <td><label><span class="visually-hidden">Tipo de precio</span><select name="channel[]"><option value="retail">Minorista</option><option value="wholesale">Mayorista</option></select></label></td>
        <td><label><span class="visually-hidden">Cantidad</span><input name="quantity[]" type="number" min="1" max="10000" value="1" required></label></td>
        <td><output data-line-total></output></td>
        <td><button type="button" class="secondary small" data-remove>Quitar</button></td>
      </tr>
    </template>
  </section>
  <section class="panel">
    <div class="billing-payment">
      <label>Comprobante de la seña (PDF, JPG o PNG · hasta 5 MB)<input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png" required></label>
      <dl class="totals">
        <div><dt>Seña (50 %)</dt><dd data-deposit>Gs. 0</dd></div>
        <div><dt>Saldo</dt><dd data-balance>Gs. 0</dd></div>
        <div><dt>Total</dt><dd data-total>Gs. 0</dd></div>
      </dl>
    </div>
    <button data-submit disabled>Enviar a revisión</button>
  </section>
</form>
