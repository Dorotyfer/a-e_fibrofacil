<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require __DIR__ . '/app/core.php';

try {
  $db = database();
  $columns = $db->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array('active', $columns, true)) {
    $db->exec('ALTER TABLE users ADD active BOOLEAN NOT NULL DEFAULT 1');
  }
  if (!in_array('session_version', $columns, true)) {
    $db->exec('ALTER TABLE users ADD session_version INT UNSIGNED NOT NULL DEFAULT 0');
  }
  echo "Estado de usuarios actualizado.\n";
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
}
