<?php

function commission(int $sales): int {
  $remaining = $sales;
  $hundredths = 0;
  foreach ([3 => 2500000, 6 => 1500000, 9 => 1500000, 12 => 1500000, 15 => PHP_INT_MAX] as $rate => $width) {
    $amount = min($remaining, $width);
    $hundredths += $amount * $rate;
    $remaining -= $amount;
  }
  return intdiv($hundredths + 50, 100);
}

function paymentSplit(int $total): array {
  $deposit = intdiv($total, 2) + $total % 2;
  return [$deposit, $total - $deposit];
}

function orderEvent(PDO $db, int $order, int $actor, string $description): void {
  query($db, 'INSERT INTO order_events (order_id,actor_id,description,created_at) VALUES (?,?,?,?)',
    [$order, $actor, $description, now()]);
}

function orderRecord(PDO $db, int $id, array $user, bool $lock = false): array {
  $sql = 'SELECT * FROM orders WHERE id = ?';
  $params = [$id];
  if ($user['role'] !== 'admin') {
    $sql .= ' AND seller_id = ?';
    $params[] = $user['id'];
  }
  $order = query($db, $sql . ($lock ? ' FOR UPDATE' : ''), $params)->fetch();
  if (!$order) {
    throw new DomainException('Pedido no disponible.');
  }
  return $order;
}

function recordPayment(PDO $db, int $order, string $stage, int $amount, array $proof): void {
  query($db, 'INSERT INTO payments (order_id,stage,amount,mime,proof,submitted_at) VALUES (?,?,?,?,?,?)',
    [$order, $stage, $amount, ...$proof, now()]);
}

function createOrder(PDO $db, array $input, array $proof, int $seller): int {
  $customer = field($input, 'cliente', 120);
  $ids = $input['product'] ?? null;
  if (!is_array($ids) || !$ids || count($ids) > 30) {
    throw new DomainException('Agrega entre 1 y 30 líneas al pedido.');
  }
  return atomic($db, function () use ($db, $input, $proof, $seller, $customer, $ids) {
    $items = [];
    $total = 0;
    foreach ($ids as $index => $id) {
      $product = query($db, productQuery() . ' WHERE p.id = ? AND g.thickness IS NULL', [integer($id)])->fetch();
      $channel = $input['channel'][$index] ?? '';
      if (!$product || !in_array($channel, ['retail', 'wholesale'], true) || $product[$channel] === null) {
        throw new DomainException('El producto o precio seleccionado no está disponible.');
      }
      $quantity = integer($input['quantity'][$index] ?? null, 1, 10000);
      $price = (int) $product[$channel];
      $total += $quantity * $price;
      $items[] = [$product['id'], $product['name'], $quantity, $channel, $price,
        (int) round(array_sum(costBreakdown($product))), $product['mdf_id'], $product['area'], $product['glue']];
    }
    if ($total < 2 || $total > 1000000000000) {
      throw new DomainException('El total debe estar entre Gs. 2 y Gs. 1.000.000.000.000.');
    }
    $expected = integer($input['expected_total'] ?? null);
    if ($total !== $expected) {
      throw new DomainException('El precio cambió. Vuelve a comprobar el importe de la seña.');
    }
    query($db, 'INSERT INTO orders (seller_id,customer,total,created_at) VALUES (?,?,?,?)', [$seller, $customer, $total, now()]);
    $order = (int) $db->lastInsertId();
    foreach ($items as $item) {
      query($db, 'INSERT INTO order_items (order_id,product_id,name,quantity,channel,price,unit_cost,mdf_id,area,glue)
        VALUES (?,?,?,?,?,?,?,?,?,?)', [$order, ...$item]);
    }
    recordPayment($db, $order, 'deposit', paymentSplit($total)[0], $proof);
    orderEvent($db, $order, $seller, 'Pedido creado. Seña enviada a revisión.');
    return $order;
  });
}

function orderRequirements(PDO $db, int $order, bool $lock = false): array {
  return query($db, "SELECT id, name, stock, thickness, required FROM materials
    JOIN (
      SELECT mdf_id AS material_id, SUM(area * quantity) AS required FROM order_items WHERE order_id = ? GROUP BY mdf_id
      UNION ALL
      SELECT (SELECT id FROM materials WHERE thickness IS NULL), SUM(glue * quantity) FROM order_items WHERE order_id = ?
    ) amounts ON amounts.material_id = materials.id AND amounts.required > 0
    ORDER BY id" . ($lock ? ' FOR UPDATE' : ''), [$order, $order])->fetchAll();
}

function shortages(array $requirements): array {
  return array_filter($requirements, fn ($material) => $material['stock'] < $material['required']);
}

function consumeStock(PDO $db, array $order, int $actor): bool {
  if ($order['stocked']) {
    return true;
  }
  $materials = orderRequirements($db, $order['id'], true);
  if (shortages($materials)) {
    orderEvent($db, $order['id'], $actor, 'Consumo pendiente por falta de material.');
    return false;
  }
  foreach ($materials as $material) {
    query($db, 'UPDATE materials SET stock = stock - ? WHERE id = ?', [$material['required'], $material['id']]);
    query($db, "INSERT INTO movements (material_id,actor_id,order_id,kind,quantity,created_at) VALUES (?,?,?,'consumption',?,?)",
      [$material['id'], $actor, $order['id'], '-' . $material['required'], now()]);
  }
  query($db, 'UPDATE orders SET stocked = 1 WHERE id = ?', [$order['id']]);
  orderEvent($db, $order['id'], $actor, 'Salida de materia prima registrada.');
  return true;
}

function retryStock(PDO $db, int $id, array $user): bool {
  return atomic($db, function () use ($db, $id, $user) {
    $order = orderRecord($db, $id, $user, true);
    if ($order['status'] !== 'pending') {
      throw new DomainException('El pedido debe tener la seña aprobada.');
    }
    return consumeStock($db, $order, $user['id']);
  });
}

function reviewPayment(PDO $db, int $id, int $payment, array $user, bool $approved, array $input): string {
  return atomic($db, function () use ($db, $id, $payment, $user, $approved, $input) {
    $order = orderRecord($db, $id, $user, true);
    $record = query($db, 'SELECT stage,status FROM payments WHERE id=? AND order_id=?', [$payment, $id])->fetch();
    if (!$record || $record['status'] !== 'review') {
      throw new DomainException('El comprobante ya fue revisado o no está disponible.');
    }
    $reason = $approved ? null : field($input, 'motivo', 250);
    query($db, 'UPDATE payments SET status=?, reason=?, reviewed_at=?, reviewed_by=? WHERE id=?',
      [$approved ? 'approved' : 'rejected', $reason, now(), $user['id'], $payment]);
    $label = $record['stage'] === 'deposit' ? 'Seña' : 'Saldo';
    orderEvent($db, $id, $user['id'], $label . ($approved ? ' · comprobante aprobado.' : ' · comprobante rechazado.'));
    if ($approved && $record['stage'] === 'deposit') {
      query($db, "UPDATE orders SET status='pending' WHERE id=?", [$id]);
      if (!consumeStock($db, $order, $user['id'])) {
        return 'Seña aprobada. El pedido tiene material pendiente.';
      }
    }
    return $approved ? 'Comprobante aprobado.' : 'Comprobante rechazado.';
  });
}

function submitProof(PDO $db, int $id, array $user, string $stage, array $proof): void {
  atomic($db, function () use ($db, $id, $user, $stage, $proof) {
    $order = orderRecord($db, $id, $user, true);
    $latest = query($db, 'SELECT status FROM payments WHERE order_id=? AND stage=? ORDER BY id DESC LIMIT 1',
      [$id, $stage])->fetchColumn();
    [$deposit, $balance] = paymentSplit($order['total']);
    if ($stage === 'deposit' && $order['status'] === 'review' && $latest === 'rejected') {
      recordPayment($db, $id, $stage, $deposit, $proof);
      orderEvent($db, $id, $user['id'], 'Nuevo comprobante de seña enviado.');
      return;
    }
    if ($stage !== 'balance' || !$order['stocked'] ||
        !(($order['status'] === 'pending' && $latest === false) || ($order['status'] === 'delivered' && $latest === 'rejected'))) {
      throw new DomainException('El pedido no admite un nuevo comprobante en este estado.');
    }
    recordPayment($db, $id, $stage, $balance, $proof);
    if ($order['status'] === 'pending') {
      query($db, "UPDATE orders SET status='delivered' WHERE id=?", [$id]);
    }
    orderEvent($db, $id, $user['id'], $latest === false ? 'Entrega registrada. Saldo enviado a revisión.' : 'Nuevo comprobante de saldo enviado.');
  });
}
