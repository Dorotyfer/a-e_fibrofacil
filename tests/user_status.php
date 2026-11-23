<?php
putenv('FIBRO_DB_NAME=fibro_test_codex');
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/accounts.php';

function checkStatus(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$db = database();
$admin = $db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
$seller = $db->query("SELECT id FROM users WHERE role='seller' LIMIT 1")->fetchColumn();
$before = query($db, 'SELECT COUNT(*) FROM orders WHERE seller_id=?', [$seller])->fetchColumn();
$version = query($db, 'SELECT session_version FROM users WHERE id=?', [$seller])->fetchColumn();
setUserActive($db, (int) $seller, (int) $admin, false);
$row = query($db, 'SELECT active,session_version FROM users WHERE id=?', [$seller])->fetch();
checkStatus(!$row['active'] && $row['session_version'] == $version + 1, 'Desactivación');
setUserActive($db, (int) $seller, (int) $admin, true);
$row = query($db, 'SELECT active,session_version FROM users WHERE id=?', [$seller])->fetch();
checkStatus((bool) $row['active'] && $row['session_version'] == $version + 2, 'Reactivación');
checkStatus(query($db, 'SELECT COUNT(*) FROM orders WHERE seller_id=?', [$seller])->fetchColumn() === $before, 'Historial de ventas');
try {
  setUserActive($db, (int) $admin, (int) $admin, false);
  throw new RuntimeException('Se desactivó la cuenta propia.');
} catch (DomainException $error) {
  checkStatus(str_contains($error->getMessage(), 'propia'), 'Protección de cuenta propia');
}
echo "Estados de usuario verificados.\n";
