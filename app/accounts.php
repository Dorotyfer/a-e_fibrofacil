<?php

function saveSeller(PDO $db, array $input): void {
  $id = integer($input['id'] ?? 0, 0);
  $name = field($input, 'nombre', 120);
  $username = field($input, 'usuario', 80);
  $password = $input['password'] ?? '';
  if (!is_string($password) || ((!$id || $password !== '') && (strlen($password) < 12 || strlen($password) > 72))) {
    throw new DomainException('La contraseña debe tener entre 12 y 72 bytes.');
  }
  $hash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
  if ($id) {
    query($db, "UPDATE users SET name=?, username=?, password=COALESCE(?,password) WHERE id=? AND role='seller'", [$name, $username, $hash, $id]);
  } else {
    query($db, "INSERT INTO users (name,username,password,role) VALUES (?,?,?,'seller')", [$name, $username, $hash]);
  }
}

function saveSalary(PDO $db, array $input): void {
  $id = integer($input['seller'] ?? null);
  if (!query($db, "SELECT id FROM users WHERE id=? AND role='seller'", [$id])->fetchColumn()) {
    throw new DomainException('Vendedor inexistente.');
  }
  query($db, 'INSERT INTO salaries (user_id,month,amount) VALUES (?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)',
    [$id, monthKey($input['month'] ?? null), integer($input['salary'] ?? null, 0)]);
}

function settlements(PDO $db, string $month): array {
  $start = $month . '-01';
  $end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
  return query($db, "SELECT u.id,u.name,
    (SELECT amount FROM salaries WHERE user_id=u.id AND month<=? ORDER BY month DESC LIMIT 1) AS salary,
    COALESCE(t.total,0) AS collected
    FROM users u LEFT JOIN (
      SELECT o.seller_id,SUM(p.amount) AS total FROM payments p JOIN orders o ON o.id=p.order_id
      WHERE p.status='approved' AND p.reviewed_at>=? AND p.reviewed_at<? GROUP BY o.seller_id
    ) t ON t.seller_id=u.id WHERE u.role='seller' ORDER BY u.name", [$month, $start, $end])->fetchAll();
}
