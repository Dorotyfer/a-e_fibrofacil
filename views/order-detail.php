<?php
$id = integer($_GET['id']);
$order = orderRecord($db, $id, $user);
$items = query($db, 'SELECT * FROM order_items WHERE order_id=? ORDER BY id', [$id])->fetchAll();
$payments = query($db, 'SELECT p.id,p.stage,p.amount,p.status,p.reason,p.submitted_at,p.reviewed_at,u.name AS reviewer
  FROM payments p LEFT JOIN users u ON u.id=p.reviewed_by WHERE p.order_id=? ORDER BY p.id', [$id])->fetchAll();
$events = query($db, 'SELECT ev.*,u.name FROM order_events ev JOIN users u ON u.id=ev.actor_id WHERE ev.order_id=? ORDER BY ev.id DESC', [$id])->fetchAll();
$missing = $order['stocked'] ? [] : shortages(orderRequirements($db, $id));
$latest = array_column($payments, 'status', 'stage');
[$deposit, $balance] = paymentSplit($order['total']);
$nextProof = ($latest['deposit'] ?? null) === 'rejected' ? 'deposit'
  : (($order['status'] === 'pending' && $order['stocked']) || ($latest['balance'] ?? null) === 'rejected' ? 'balance' : null);
?>
<a href="?page=<?= e($page) ?>">← <?= $page === 'sales' ? 'Ventas' : 'Pedidos' ?></a>
<header class="page-header"><div><h1>Pedido #<?= $id ?></h1><p class="muted"><?= e($order['customer']) ?> · <?= e($order['created_at']) ?></p></div><?= badge($order['status']) ?></header>
<?php if ($missing || ($order['status'] === 'pending' && !$order['stocked'])): ?>
  <section class="notice" role="status">
    <strong><?= $missing ? 'Falta materia prima para este pedido' : 'Salida pendiente de registrar' ?></strong>
    <?php if ($missing): ?><ul><?php foreach ($missing as $material): ?>
      <li><?= e($material['name']) ?>: faltan <?= qty($material['required'] - $material['stock']) ?> <?= unit($material) ?></li>
    <?php endforeach; ?></ul><?php endif; ?>
    <?php if (!$seller && $order['status'] === 'pending'): ?>
      <form method="post"><?php hidden('retry', $id); ?><button class="small">Reintentar salida</button></form>
    <?php endif; ?>
  </section>
<?php endif; ?>
<section class="panel">
  <h2>Productos del pedido</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Producto</th><th>Cantidad</th><th>Precio unitario</th><?php if (!$seller): ?><th>Costo unitario</th><?php endif; ?><th>Subtotal</th></tr></thead>
    <tbody><?php foreach ($items as $item): ?>
      <tr><td><?= e($item['name']) ?> <span class="muted">· <?= $item['channel'] === 'retail' ? 'Minorista' : 'Mayorista' ?></span></td>
        <td><?= $item['quantity'] ?></td><td><?= guaranies($item['price']) ?></td>
        <?php if (!$seller): ?><td><?= guaranies($item['unit_cost']) ?></td><?php endif; ?>
        <td><?= guaranies($item['quantity'] * $item['price']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <p><strong>Total <?= guaranies($order['total']) ?></strong> · Seña <?= guaranies($deposit) ?> · Saldo <?= guaranies($balance) ?></p>
</section>
<section class="panel">
  <h2>Comprobantes</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>Cobro</th><th>Importe</th><th>Estado</th><th>Comprobante</th><th>Revisión</th></tr></thead>
    <tbody><?php foreach ($payments as $payment): ?>
      <tr><td><?= $payment['stage'] === 'deposit' ? 'Seña' : 'Saldo' ?><br><span class="muted"><?= e($payment['submitted_at']) ?></span></td>
        <td><?= guaranies($payment['amount']) ?></td><td><?= badge($payment['status']) ?></td>
        <td><a href="?page=proof&id=<?= $payment['id'] ?>">Descargar</a></td>
        <td>
          <?php if (!$seller && $payment['status'] === 'review'): ?>
            <form method="post" class="review-form">
              <?php hidden('review', $id); ?><input type="hidden" name="payment" value="<?= $payment['id'] ?>">
              <div class="actions"><button class="small" name="decision" value="approve" formnovalidate>Aprobar</button>
                <button class="secondary small" name="decision" value="reject">Rechazar</button></div>
              <label>Motivo del rechazo<input name="motivo" maxlength="250" required></label>
            </form>
          <?php elseif ($payment['reviewed_at']): ?>
            <?= e($payment['reviewed_at']) ?> · <?= e($payment['reviewer']) ?>
            <?php if ($payment['reason']): ?><br><?= e($payment['reason']) ?><?php endif; ?>
          <?php else: ?>—<?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php if ($nextProof): ?>
    <form method="post" enctype="multipart/form-data" class="toolbar">
      <?php hidden('proof', $id); ?><input type="hidden" name="stage" value="<?= e($nextProof) ?>">
      <label><?= $nextProof === 'deposit' ? 'Nuevo comprobante de seña' : 'Comprobante del saldo' ?> (hasta 5 MB)
        <input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png" required></label>
      <button><?= $nextProof === 'balance' && $order['status'] === 'pending' ? 'Registrar entrega' : 'Enviar comprobante' ?></button>
    </form>
  <?php endif; ?>
</section>
<section class="panel">
  <h2>Historial</h2>
  <ul class="timeline"><?php foreach ($events as $event): ?>
    <li><span><?= e($event['description']) ?></span><small><?= e($event['created_at'] . ' · ' . $event['name']) ?></small></li>
  <?php endforeach; ?></ul>
</section>
