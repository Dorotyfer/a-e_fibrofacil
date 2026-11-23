<?php
$materials = $db->query('SELECT * FROM materials ORDER BY thickness IS NULL,thickness')->fetchAll();
$movements = $db->query('SELECT v.*,m.name,m.thickness,u.name AS actor FROM movements v
  JOIN materials m ON m.id=v.material_id JOIN users u ON u.id=v.actor_id ORDER BY v.id DESC LIMIT 200')->fetchAll();
?>
<header class="page-header"><h1>Materia prima</h1></header>
<section class="panel">
  <h2>Existencias</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Material</th><th>Disponible</th><th>Costo promedio por unidad</th></tr></thead>
    <tbody><?php foreach ($materials as $material): ?>
      <tr><td><?= e($material['name']) ?></td><td><?= qty($material['stock']) ?> <?= unit($material) ?></td>
        <td><?= $material['rate'] === null ? 'Sin compra registrada' : 'Gs. ' . qty($material['rate']) . ' / ' . unit($material) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<div class="columns">
  <section class="panel">
    <h2>Registrar compra</h2>
    <form method="post" class="stack" data-material-form>
      <?php hidden('purchase'); ?>
      <label>Material<select name="material">
        <?php foreach ($materials as $material): ?><option value="<?= $material['id'] ?>" data-kind="<?= $material['thickness'] === null ? 'glue' : 'mdf' ?>"><?= e($material['name']) ?></option><?php endforeach; ?>
      </select></label>
      <fieldset class="fields" data-kind="mdf">
        <label>Ancho de placa (cm)<input name="width" type="number" min=".001" step=".001" max="10000" required></label>
        <label>Alto de placa (cm)<input name="height" type="number" min=".001" step=".001" max="10000" required></label>
        <label>Cantidad de placas<input name="plates" type="number" min="1" max="10000" value="1" required></label>
      </fieldset>
      <fieldset data-kind="glue"><label>Peso (g)<input name="grams" type="number" min=".001" step=".001" required></label></fieldset>
      <label>Costo total (Gs.)<input name="purchase_cost" type="number" min="1" step="1" required></label>
      <button>Registrar entrada</button>
    </form>
  </section>
  <div class="stack">
    <section class="panel">
      <h2>Añadir espesor de MDF</h2>
      <form method="post" class="stack"><?php hidden('mdf'); ?>
        <label>Espesor (mm)<input name="thickness" type="number" min=".001" step=".001" max="99999" required></label>
        <button>Añadir material</button>
      </form>
    </section>
    <section class="panel">
      <h2>Ajustar existencias</h2>
      <form method="post" class="stack"><?php hidden('adjustment'); ?>
        <label>Material<select name="material"><?php foreach ($materials as $material): ?>
          <option value="<?= $material['id'] ?>"><?= e($material['name']) ?> (<?= unit($material) ?>)</option>
        <?php endforeach; ?></select></label>
        <label>Cantidad a sumar o restar<input name="adjustment" type="number" step=".000001" required></label>
        <label>Motivo<input name="motivo" maxlength="250" required></label>
        <button>Guardar ajuste</button>
      </form>
    </section>
  </div>
</div>
<section class="panel">
  <h2>Últimos movimientos</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Fecha</th><th>Material</th><th>Movimiento</th><th>Cantidad</th><th>Detalle</th><th>Registró</th></tr></thead>
    <tbody>
    <?php if (!$movements) emptyRow(6, 'Todavía no hay movimientos.'); ?>
    <?php foreach ($movements as $movement): ?>
      <tr><td><?= e($movement['created_at']) ?></td><td><?= e($movement['name']) ?></td>
        <td><?= ['purchase' => 'Compra', 'adjustment' => 'Ajuste', 'consumption' => 'Salida'][$movement['kind']] ?></td>
        <td><?= qty($movement['quantity']) ?> <?= unit($movement) ?></td>
        <td>
          <?php if ($movement['order_id']): ?><a href="?page=orders&id=<?= $movement['order_id'] ?>">Pedido #<?= $movement['order_id'] ?></a>
          <?php elseif ($movement['cost']): ?><?= guaranies($movement['cost']) ?>
            <?php if ($movement['plates']): ?> · <?= $movement['plates'] ?> × <?= qty($movement['plate_width']) ?> × <?= qty($movement['plate_height']) ?> cm<?php endif; ?>
          <?php else: ?><?= e($movement['reason']) ?><?php endif; ?>
        </td><td><?= e($movement['actor']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
