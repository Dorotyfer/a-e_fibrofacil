<?php
$month = monthKey($_GET['month'] ?? date('Y-m'));
$start = $month . '-01';
$end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
$rows = [];
if ($page === 'sales_sellers') {
  $rows = settlements($db, $month);
} elseif ($page === 'sales_general') {
  $rows = query($db, "SELECT o.id,o.created_at,o.customer,o.total,o.status,u.name AS seller,
    COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.order_id=o.id AND p.status='approved'),0) AS collected
    FROM orders o JOIN users u ON u.id=o.seller_id
    WHERE o.created_at>=? AND o.created_at<? ORDER BY o.created_at DESC,o.id DESC", [$start, $end])->fetchAll();
} elseif ($page === 'material_purchases') {
  $rows = query($db, "SELECT v.created_at,v.quantity,v.cost,v.plate_width,v.plate_height,v.plates,m.name,m.thickness,u.name AS actor
    FROM movements v JOIN materials m ON m.id=v.material_id JOIN users u ON u.id=v.actor_id
    WHERE v.kind='purchase' AND v.created_at>=? AND v.created_at<? ORDER BY v.created_at DESC,v.id DESC", [$start, $end])->fetchAll();
} else {
  $rows = query($db, "SELECT o.customer_id,o.customer,COUNT(*) AS orders_count,SUM(o.total) AS total,
    COALESCE(SUM((SELECT SUM(p.amount) FROM payments p WHERE p.order_id=o.id AND p.status='approved')),0) AS collected
    FROM orders o WHERE o.created_at>=? AND o.created_at<?
    GROUP BY o.customer_id,o.customer ORDER BY o.customer", [$start, $end])->fetchAll();
}
?>
<header class="page-header"><h1><?= e($pages[$page]) ?></h1></header>
<section class="panel">
  <form method="get" class="toolbar">
    <input type="hidden" name="page" value="<?= e($page) ?>">
    <label>Mes<input type="month" name="month" value="<?= e($month) ?>" required></label>
    <button class="secondary">Consultar</button>
  </form>
  <?php if ($page === 'sales_sellers'): ?>
    <p class="muted">Cobros aprobados durante el mes seleccionado.</p>
    <div class="table-wrap"><table><thead><tr><th>Vendedor</th><th>Cobros validados</th><th>Salario</th><th>Comisión</th><th>Liquidación</th></tr></thead><tbody>
      <?php if (!$rows) emptyRow(5, 'No hay vendedores registrados.'); ?>
      <?php foreach ($rows as $row): $amount = commission((int) $row['collected']); ?>
        <tr><td><?= e($row['name']) ?></td><td><?= guaranies($row['collected']) ?></td>
          <td><?= $row['salary'] === null ? 'Sin configurar' : guaranies($row['salary']) ?></td>
          <td><?= guaranies($amount) ?></td><td><?= $row['salary'] === null ? '—' : guaranies($row['salary'] + $amount) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php elseif ($page === 'sales_general'): ?>
    <p class="muted">Pedidos creados durante el mes seleccionado. Los cobros reflejan los importes aprobados hasta la fecha.</p>
    <div class="table-wrap"><table><thead><tr><th>Pedido</th><th>Fecha</th><th>Cliente</th><th>Vendedor</th><th>Estado</th><th>Total</th><th>Cobrado</th></tr></thead><tbody>
      <?php if (!$rows) emptyRow(7, 'No hay pedidos en este período.'); ?>
      <?php foreach ($rows as $row): ?>
        <tr><td><a href="?page=orders&id=<?= $row['id'] ?>">#<?= $row['id'] ?></a></td><td><?= e($row['created_at']) ?></td>
          <td><?= e($row['customer']) ?></td><td><?= e($row['seller']) ?></td><td><?= badge($row['status']) ?></td>
          <td><?= guaranies($row['total']) ?></td><td><?= guaranies($row['collected']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php elseif ($page === 'material_purchases'): ?>
    <div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Material</th><th>Cantidad</th><th>Placas</th><th>Costo</th><th>Registró</th></tr></thead><tbody>
      <?php if (!$rows) emptyRow(6, 'No hay compras en este período.'); ?>
      <?php foreach ($rows as $row): ?>
        <tr><td><?= e($row['created_at']) ?></td><td><?= e($row['name']) ?></td><td><?= qty($row['quantity']) ?> <?= unit($row) ?></td>
          <td><?= $row['plates'] ? e($row['plates'] . ' × ' . qty($row['plate_width']) . ' × ' . qty($row['plate_height']) . ' cm') : '—' ?></td>
          <td><?= guaranies($row['cost']) ?></td><td><?= e($row['actor']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php else: ?>
    <p class="muted">Pedidos creados durante el mes seleccionado, incluidos los pedidos anteriores al registro de clientes. Los cobros reflejan los importes aprobados hasta la fecha.</p>
    <div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Pedidos</th><th>Total de pedidos</th><th>Cobrado</th></tr></thead><tbody>
      <?php if (!$rows) emptyRow(4, 'No hay pedidos en este período.'); ?>
      <?php foreach ($rows as $row): ?>
        <tr><td><?= e($row['customer']) ?><?= $row['customer_id'] === null ? ' · Sin ficha' : '' ?></td><td><?= e($row['orders_count']) ?></td>
          <td><?= guaranies($row['total']) ?></td><td><?= guaranies($row['collected']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
