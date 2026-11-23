<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require __DIR__ . '/app/core.php';

try {
  $db = database();
  $db->exec("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '', email VARCHAR(160) NOT NULL DEFAULT '',
    address VARCHAR(250) NOT NULL DEFAULT ''
  )");
  $columns = $db->query('SHOW COLUMNS FROM orders')->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array('customer_id', $columns, true)) {
    $db->exec('ALTER TABLE orders ADD customer_id INT NULL REFERENCES customers(id)');
  }
  echo "Clientes disponibles.\n";
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
}
