<?php
putenv('FIBRO_DB_NAME=fibro_test_codex');
require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/geometry.php';
require __DIR__ . '/../app/catalog.php';
require __DIR__ . '/../app/orders.php';
require __DIR__ . '/../app/accounts.php';

function verify(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
$db = database();
$admin = $db->query("SELECT id,role FROM users WHERE role='admin' LIMIT 1")->fetch();
addMdf($db, ['thickness' => '3']);
$mdf = (int) $db->query('SELECT id FROM materials WHERE thickness=3')->fetchColumn();
stockMovement($db, ['material' => $mdf, 'width' => 100, 'height' => 100, 'plates' => 1, 'purchase_cost' => 100000], $admin['id'], true);
$plot = 'IN;SP1;PU0,0;PD4000,0,4000,4000,0,4000,0,0;PU;';
$drawing = parsePlt($plot);
$area = selectedArea($drawing, [0]);
query($db, "INSERT INTO products (name,size,category,mdf_id,area,assembly,glue,labor,retail,wholesale,filename,plot,selection) VALUES ('Caja','10 cm','Cajas',?,?,0,0,0,3000000,NULL,'caja.plt',?,'[0]')", [$mdf, $area, $plot]);
$product = (int) $db->lastInsertId();
query($db, "INSERT INTO users (name,username,password,role) VALUES ('Vendedor','vendedor',?,'seller')", [password_hash('test-password-123', PASSWORD_DEFAULT)]);
$seller = (int) $db->lastInsertId();
$id = createOrder($db, ['cliente' => 'Cliente', 'product' => [$product], 'quantity' => [1], 'channel' => ['retail'], 'expected_total' => 3000000], ['application/pdf', '%PDF-1.4'], $seller);
$payment = (int) query($db, 'SELECT id FROM payments WHERE order_id=?', [$id])->fetchColumn();
reviewPayment($db, $id, $payment, $admin, true, []);
verify((float) query($db, 'SELECT stock FROM materials WHERE id=?', [$mdf])->fetchColumn() === 9900.0, 'Descuento de MDF');
submitProof($db, $id, ['id' => $seller, 'role' => 'seller'], 'balance', ['application/pdf', '%PDF-1.4']);
$balance = (int) query($db, "SELECT id FROM payments WHERE order_id=? AND stage='balance'", [$id])->fetchColumn();
reviewPayment($db, $id, $balance, $admin, true, []);
$row = settlements($db, date('Y-m'))[0];
verify((int) $row['collected'] === 3000000 && commission((int) $row['collected']) === 105000, 'Liquidación');
echo "Flujo completo correcto.\n";
