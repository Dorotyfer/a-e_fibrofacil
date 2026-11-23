<?php
$seller = $user['role'] === 'seller';
if (isset($_GET['id'])) {
  require __DIR__ . '/order-detail.php';
  return;
}
if (isset($_GET['new'])) {
  require __DIR__ . '/order-form.php';
  return;
}
$status = $_GET['status'] ?? '';
$filter = in_array($status, ['review', 'pending', 'delivered', 'shortage'], true) ? $status : '';
$clauses = [];
$params = [];
if ($seller) {
  $clauses[] = 'o.seller_id=?';
  $params[] = $user['id'];
}
if ($filter === 'shortage') {
  $clauses[] = "o.status='pending' AND o.stocked=0";
} elseif ($filter) {
  $clauses[] = 'o.status=?';
  $params[] = $filter;
}
$orders = query($db, "SELECT o.*,u.name AS seller,
  (SELECT status FROM payments p WHERE p.order_id=o.id AND p.stage=IF(o.status='delivered','balance','deposit') ORDER BY p.id DESC LIMIT 1) AS payment_status
  FROM orders o JOIN users u ON u.id=o.seller_id" . ($clauses ? ' WHERE ' . implode(' AND ', $clauses) : '') . ' ORDER BY o.id DESC', $params)->fetchAll();
?>
<header class="page-header"><h1><?= $page === 'sales' ? 'Ventas' : 'Pedidos' ?></h1><a class="button" href="?page=<?= e($page) ?>&new=1">Nueva venta</a></header>
<section class="panel">
  <form class="toolbar" method="get">
    <input type="hidden" name="page" value="<?= e($page) ?>">
    <label>Estado<select name="status">
      <?php foreach (['' => 'Todos', 'review' => 'En revisión', 'pending' => 'Pendiente', 'shortage' => 'Falta material', 'delivered' => 'Entregado'] as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $key === $filter ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select></label>
    <button class="secondary">Filtrar</button>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Pedido</th><th>Cliente</th><?php if (!$seller): ?><th>Vendedor</th><?php endif; ?><th>Fecha</th><th>Total</th><th>Estado</th><th>Último cobro</th></tr></thead>
    <tbody>
      <?php if (!$orders) emptyRow($seller ? 6 : 7, 'No hay pedidos para mostrar.'); ?>
      <?php foreach ($orders as $order): ?>
        <tr><td><a href="?page=<?= e($page) ?>&id=<?= $order['id'] ?>">#<?= $order['id'] ?></a></td><td><?= e($order['customer']) ?></td>
          <?php if (!$seller): ?><td><?= e($order['seller']) ?></td><?php endif; ?><td><?= e($order['created_at']) ?></td>
          <td><?= guaranies($order['total']) ?></td><td><?= badge($order['status']) ?>
          <?php if ($order['status'] === 'pending' && !$order['stocked']): ?><?= badge('shortage') ?><?php endif; ?></td>
          <td><?= badge($order['payment_status']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
