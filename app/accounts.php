<?php

function saveUser(PDO $db, array $input): void {
  $id = integer($input['id'] ?? 0, 0);
  $name = field($input, 'nombre', 120);
  $username = field($input, 'usuario', 80);
  $role = $input['role'] ?? '';
  if (!in_array($role, ['admin', 'seller'], true)) {
    throw new DomainException('Selecciona un rol válido.');
  }
  $password = $input['password'] ?? '';
  if (!is_string($password) || ((!$id || $password !== '') && (strlen($password) < 12 || strlen($password) > 72))) {
    throw new DomainException('La contraseña debe tener entre 12 y 72 bytes.');
  }
  $hash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);
  if ($id) {
    $existing = query($db, 'SELECT role FROM users WHERE id=?', [$id])->fetch();
    if (!$existing) {
      throw new DomainException('Usuario inexistente.');
    }
    if ($existing['role'] !== $role) {
      throw new DomainException('El rol de una cuenta existente no se puede cambiar.');
    }
    query($db, 'UPDATE users SET name=?, username=?, password=COALESCE(?,password) WHERE id=?', [$name, $username, $hash, $id]);
  } else {
    query($db, 'INSERT INTO users (name,username,password,role) VALUES (?,?,?,?)', [$name, $username, $hash, $role]);
  }
}

function setUserActive(PDO $db, int $id, int $actor, bool $active): void {
  atomic($db, function () use ($db, $id, $actor, $active) {
    $admins = query($db, "SELECT id,active FROM users WHERE role='admin' ORDER BY id FOR UPDATE")->fetchAll();
    $account = query($db, 'SELECT id,role,active FROM users WHERE id=? FOR UPDATE', [$id])->fetch();
    if (!$account) {
      throw new DomainException('Usuario inexistente.');
    }
    if (!$active && $id === $actor) {
      throw new DomainException('No puedes desactivar tu propia cuenta.');
    }
    if (!$active && $account['role'] === 'admin' && $account['active'] &&
        count(array_filter($admins, fn ($admin) => $admin['active'])) <= 1) {
      throw new DomainException('Debe quedar al menos un administrador activo.');
    }
    if ((bool) $account['active'] !== $active) {
      query($db, 'UPDATE users SET active=?, session_version=session_version+1 WHERE id=?', [(int) $active, $id]);
    }
  });
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
