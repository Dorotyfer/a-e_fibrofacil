<?php
require __DIR__ . '/../app/geometry.php';
require __DIR__ . '/../app/orders.php';

function check(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

$drawing = parsePlt('IN;SP1;PU0,0;PD4000,0,4000,4000,0,4000,0,0;PU400,400;PD800,400,800,800,400,800,400,400;PU8000,0;PR;PD400,0,0,400,-400,0,0,-400;PU;');
check(count($drawing['pieces']) === 2, 'Dos piezas y un agujero');
check(selectedArea($drawing, [0]) === 99.0, 'Área neta de la pieza');
check(selectedArea($drawing, [0, 1]) === 100.0, 'Selección múltiple');
check(commission(3000000) === 105000, 'Comisión de ejemplo');
check(commission(2500000) === 75000, 'Primer límite');
check(commission(4000000) === 165000, 'Segundo límite');
check(commission(5500000) === 300000, 'Tercer límite');
check(commission(7000000) === 480000, 'Cuarto límite');
check(commission(8000000) === 630000, 'Último tramo');
check(paymentSplit(101) === [51, 50], 'Total impar');
echo "Pruebas de dominio correctas.\n";
