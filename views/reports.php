<?php
$month = monthKey($_GET['month'] ?? date('Y-m'));
$settlements = settlements($db, $month);
$counts = array_column($db->query('SELECT status,COUNT(*) AS total FROM orders GROUP BY status')->fetchAll(), 'total', 'status');
$missing = $db->query("SELECT id,customer FROM orders WHERE status='pending' AND stocked=0 ORDER BY id")->fetchAll();
$reviews = $db->query("SELECT p.id,p.order_id,p.stage,p.amount,o.customer FROM payments p JOIN orders o ON o.id=p.order_id WHERE p.status='review' ORDER BY p.id")->fetchAll();
$materials = $db->query('SELECT * FROM materials ORDER BY thickness IS NULL,thickness')->fetchAll();
?>
<header class="page-header"><h1>Reportes</h1></header>
<div class="metrics">
  <?php foreach (['review' => 'En revisión', 'pending' => 'Pendientes', 'delivered' => 'Entregados', 'shortage' => 'Sin salida registrada'] as $key => $label): ?>
    <a class="panel metric" href="?page=orders&status=<?= e($key) ?>"><span><?= e($label) ?></span><strong><?= $key === 'shortage' ? count($missing) : ($counts[$key] ?? 0) ?></strong></a>
  <?php endforeach; ?>
</div>
<section class="panel">
  <div class="section-header">
    <h2>Liquidación por vendedor</h2>
    <form method="get" class="toolbar"><input type="hidden" name="page" value="reports">
      <label>Mes<input type="month" name="month" value="<?= e($month) ?>" required></label><button class="secondary">Consultar</button>
    </form>
  </div>
  <div class="table-wrap"><table><thead><tr><th>Vendedor</th><th>Cobros validados</th><th>Salario</th><th>Comisión por tramos</th><th>Liquidación</th></tr></thead>
    <tbody><?php if (!$settlements) emptyRow(5, 'Todavía no hay vendedores.'); ?>
      <?php foreach ($settlements as $row): $commission = commission((int) $row['collected']); ?>
        <tr><td><?= e($row['name']) ?></td><td><?= guaranies($row['collected']) ?></td>
          <td><?= $row['salary'] === null ? 'Sin configurar' : guaranies($row['salary']) ?></td>
          <td><?= guaranies($commission) ?></td><td><?= $row['salary'] === null ? '—' : guaranies($row['salary'] + $commission) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
</section>
<div class="columns">
  <section class="panel"><h2>Comprobantes por revisar</h2>
    <div class="table-wrap"><table><thead><tr><th>Pedido</th><th>Cliente</th><th>Cobro</th><th>Importe</th></tr></thead>
      <tbody><?php if (!$reviews) emptyRow(4, 'No hay comprobantes pendientes.'); ?>
        <?php foreach ($reviews as $row): ?><tr><td><a href="?page=orders&id=<?= $row['order_id'] ?>">#<?= $row['order_id'] ?></a></td>
          <td><?= e($row['customer']) ?></td><td><?= $row['stage'] === 'deposit' ? 'Seña' : 'Saldo' ?></td><td><?= guaranies($row['amount']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
  </section>
  <section class="panel"><h2>Pedidos con salida pendiente</h2>
    <div class="table-wrap"><table><thead><tr><th>Pedido</th><th>Cliente</th><th>Faltantes</th></tr></thead>
      <tbody><?php if (!$missing) emptyRow(3, 'No hay salidas pendientes.'); ?>
        <?php foreach ($missing as $row): ?>
          <tr><td><a href="?page=orders&id=<?= $row['id'] ?>">#<?= $row['id'] ?></a></td><td><?= e($row['customer']) ?></td>
            <td><?php $short = shortages(orderRequirements($db, $row['id'])); ?>
              <?= $short ? e(implode(', ', array_column($short, 'name'))) : 'Material disponible' ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
  </section>
</div>
<section class="panel">
  <div class="section-header"><h2>Existencias de materia prima</h2><a href="?page=materials">Ver movimientos</a></div>
  <div class="table-wrap"><table><thead><tr><th>Material</th><th>Disponible</th></tr></thead>
    <tbody><?php foreach ($materials as $material): ?><tr><td><?= e($material['name']) ?></td><td><?= qty($material['stock']) ?> <?= unit($material) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
</section>
