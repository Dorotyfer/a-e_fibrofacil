<?php
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}
require __DIR__ . '/app/core.php';

try {
  $username = field(['usuario' => $argv[1] ?? ''], 'usuario', 80);
  $password = getenv('FIBRO_ADMIN_PASSWORD') ?: '';
  if (strlen($password) < 12 || strlen($password) > 72) {
    throw new DomainException('Configura FIBRO_ADMIN_PASSWORD con una contraseña de 12 a 72 bytes.');
  }
  $name = getenv('FIBRO_DB_NAME') ?: 'ae_fibrofacil';
  database(false)->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  $db = database();
  foreach (explode(';', file_get_contents(__DIR__ . '/app/schema.sql')) as $statement) {
    if (trim($statement) !== '') {
      $db->exec($statement);
    }
  }
  if (!(int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn()) {
    query($db, "INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, 'admin')",
      ['Administración', $username, password_hash($password, PASSWORD_DEFAULT)]);
  }
  echo "Base de datos lista.\n";
} catch (Throwable $error) {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
}
