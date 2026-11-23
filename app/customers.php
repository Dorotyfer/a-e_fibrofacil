<?php

function saveCustomer(PDO $db, array $input): void {
  $id = integer($input['id'] ?? 0, 0);
  $name = field($input, 'nombre', 120);
  $values = [];
  foreach (['teléfono' => 40, 'correo' => 160, 'dirección' => 250] as $key => $limit) {
    $value = $input[$key] ?? '';
    if (!is_string($value) || mb_strlen(trim($value)) > $limit) {
      throw new DomainException('Revisa el campo ' . $key . '.');
    }
    $values[] = trim($value);
  }
  if ($values[1] !== '' && !filter_var($values[1], FILTER_VALIDATE_EMAIL)) {
    throw new DomainException('Ingresa un correo válido.');
  }
  if ($id) {
    if (!query($db, 'SELECT id FROM customers WHERE id=?', [$id])->fetchColumn()) {
      throw new DomainException('Cliente inexistente.');
    }
    query($db, 'UPDATE customers SET name=?,phone=?,email=?,address=? WHERE id=?', [$name, ...$values, $id]);
  } else {
    query($db, 'INSERT INTO customers (name,phone,email,address) VALUES (?,?,?,?)', [$name, ...$values]);
  }
}
