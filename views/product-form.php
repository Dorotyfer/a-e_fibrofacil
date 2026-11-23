<?php
$product = isset($_GET['edit'])
  ? query($db, 'SELECT * FROM products WHERE id=?', [integer($_GET['edit'])])->fetch()
  : [];
if ($product === false) {
  throw new DomainException('Producto inexistente.');
}
$mdf = $db->query('SELECT id,name,rate FROM materials WHERE thickness IS NOT NULL ORDER BY thickness')->fetchAll();
$glueRate = $db->query('SELECT rate FROM materials WHERE thickness IS NULL')->fetchColumn();
$drawing = $product ? parsePlt($product['plot']) : null;
?>
<a href="?page=products">← Productos</a>
<header class="page-header"><h1><?= $product ? 'Editar producto' : 'Añadir producto' ?></h1></header>
<?php if (!$mdf): ?>
  <p class="notice">Primero registra un espesor de MDF y su compra en <a href="?page=materials">Materia prima</a>.</p>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="stack" data-product-form>
  <?php hidden('product', $product['id'] ?? null); ?>
  <input type="hidden" name="selection" value="<?= e($product['selection'] ?? '[]') ?>">
  <div class="columns">
    <section class="panel stack">
      <h2>Datos del producto</h2>
      <label>Nombre<input name="nombre" maxlength="120" value="<?= e($product['name'] ?? '') ?>" required></label>
      <div class="fields">
        <label>Tamaño comercial<input name="tamaño" maxlength="100" value="<?= e($product['size'] ?? '') ?>" placeholder="Ej. 20 × 15 × 8 cm" required></label>
        <label>Categoría<input name="categoría" maxlength="100" value="<?= e($product['category'] ?? '') ?>" required></label>
      </div>
      <label>Espesor de MDF<select name="mdf" required>
        <?php foreach ($mdf as $material): ?>
          <option value="<?= $material['id'] ?>" data-rate="<?= e($material['rate']) ?>" <?= ($product['mdf_id'] ?? null) === $material['id'] ? 'selected' : '' ?>><?= e($material['name']) ?><?= $material['rate'] === null ? ' · Sin costo' : '' ?></option>
        <?php endforeach; ?>
      </select></label>
      <label class="check"><input name="assembly" type="checkbox" <?= !empty($product['assembly']) ? 'checked' : '' ?>> Requiere ensamble</label>
      <fieldset class="fields" data-assembly>
        <label>Pegamento por unidad (g)<input name="glue" type="number" min="0" step=".000001" value="<?= e($product['glue'] ?? 0) ?>" required></label>
        <label>Mano de obra por unidad (Gs.)<input name="labor" type="number" min="0" step="1" value="<?= e($product['labor'] ?? 0) ?>" required></label>
      </fieldset>
      <label>Precio minorista (Gs.)<input name="retail" type="number" min="1" step="1" value="<?= e($product['retail'] ?? '') ?>" required></label>
      <label class="check"><input name="enable_wholesale" type="checkbox" <?= isset($product['wholesale']) ? 'checked' : '' ?>> Habilitar venta mayorista</label>
      <fieldset data-wholesale><label>Precio mayorista (Gs.)<input name="wholesale" type="number" min="1" step="1" value="<?= e($product['wholesale'] ?? '') ?>" required></label></fieldset>
    </section>
    <section class="panel stack">
      <h2>Diseño de corte</h2>
      <label>Archivo PLT (hasta 256 KB)<input name="plot" type="file" accept=".plt" <?= $product ? '' : 'required' ?>></label>
      <?php if ($product): ?><p class="muted">Archivo actual: <?= e($product['filename']) ?></p><?php endif; ?>
      <p class="muted" data-plot-message role="status">Carga el archivo y selecciona las piezas que se sumarán al costo.</p>
      <div class="preview" data-preview></div>
      <div class="pieces" data-pieces></div>
      <dl class="totals" data-glue-rate="<?= e($glueRate) ?>" aria-live="polite">
        <div><dt>Área seleccionada</dt><dd data-cost-area>—</dd></div>
        <div><dt>MDF</dt><dd data-cost-mdf>—</dd></div>
        <div><dt>Pegamento</dt><dd data-cost-glue>—</dd></div>
        <div><dt>Mano de obra</dt><dd data-cost-labor>—</dd></div>
        <div><dt>Costo sugerido</dt><dd data-cost-total>—</dd></div>
      </dl>
      <template data-drawing><?= e(jsonData($drawing)) ?></template>
    </section>
  </div>
  <div><button data-save-product>Guardar producto</button></div>
</form>
<?php endif; ?>
