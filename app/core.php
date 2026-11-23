<?php

date_default_timezone_set('America/Asuncion');

function database(bool $withSchema = true): PDO {
  $host = getenv('FIBRO_DB_HOST') ?: '127.0.0.1';
  $port = getenv('FIBRO_DB_PORT') ?: '3306';
  $name = getenv('FIBRO_DB_NAME') ?: 'ae_fibrofacil';
  if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
    throw new RuntimeException('Nombre de base de datos inválido.');
  }
  $schema = $withSchema ? ";dbname=$name" : '';
  return new PDO("mysql:host=$host;port=$port$schema;charset=utf8mb4", getenv('FIBRO_DB_USER') ?: 'root',
    getenv('FIBRO_DB_PASSWORD') ?: '', [PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}

function query(PDO $db, string $sql, array $parameters = []): PDOStatement {
  $statement = $db->prepare($sql);
  $statement->execute($parameters);
  return $statement;
}

function atomic(PDO $db, callable $operation): mixed {
  $db->beginTransaction();
  try {
    $result = $operation();
    $db->commit();
    return $result;
  } catch (Throwable $error) {
    $db->rollBack();
    throw $error;
  }
}

function now(): string {
  return date('Y-m-d H:i:s');
}

function field(array $input, string $key, int $max): string {
  $value = $input[$key] ?? '';
  if (!is_string($value) || ($value = trim($value)) === '' || mb_strlen($value) > $max) {
    throw new DomainException('Completa el campo «' . $key . '» con un valor válido.');
  }
  return $value;
}

function integer(mixed $value, int $minimum = 1, int $maximum = 1000000000000): int {
  $number = filter_var(is_scalar($value) ? $value : null, FILTER_VALIDATE_INT);
  if ($number === false || $number < $minimum || $number > $maximum) {
    throw new DomainException('Ingresa un número entero dentro del rango permitido.');
  }
  return $number;
}

function decimal(mixed $value, float $minimum = 0.001, float $maximum = 1000000000): float {
  if (!is_numeric($value) || !is_finite((float) $value) || $value < $minimum || $value > $maximum) {
    throw new DomainException('Ingresa una cantidad válida.');
  }
  return round((float) $value, 6);
}

function monthKey(mixed $month): string {
  if (!is_string($month) || !preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $month)) {
    throw new DomainException('Selecciona un mes válido.');
  }
  return $month;
}

function upload(array $files, string $field, int $limit): array {
  $file = $files[$field] ?? [];
  if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) ||
      $file['size'] < 1 || $file['size'] > $limit) {
    throw new DomainException('Selecciona un archivo válido dentro del límite indicado.');
  }
  return [$file['name'], file_get_contents($file['tmp_name'])];
}

function proofUpload(array $files): array {
  [, $content] = upload($files, 'proof', 5 * 1024 * 1024);
  $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);
  if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    throw new DomainException('El comprobante debe ser PDF, JPG o PNG.');
  }
  return [$mime, $content];
}
