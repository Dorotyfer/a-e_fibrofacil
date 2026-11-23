<?php

function plotPaths(string $source): array {
  $paths = [];
  $path = [];
  $point = [0, 0];
  $relative = $down = false;
  $points = 0;
  foreach (explode(';', strtoupper(trim($source))) as $statement) {
    $statement = trim($statement);
    if ($statement === '') {
      continue;
    }
    $command = substr($statement, 0, 2);
    $arguments = trim(substr($statement, 2));
    if (in_array($command, ['SP', 'VS', 'FS', 'PW'], true) && preg_match('/^\d*(?:\.\d+)?$/D', $arguments)) {
      continue;
    }
    if ($command === 'IN' && !$points && $arguments === '') {
      $point = [0, 0];
      $relative = $down = false;
      continue;
    }
    if (!in_array($command, ['PU', 'PD', 'PA', 'PR'], true)) {
      throw new DomainException('Comando PLT no admitido: ' . $command . '. Exporta el dibujo como polilíneas HP-GL sin escala personalizada.');
    }
    if ($command === 'PU') {
      if ($path) {
        $paths[] = $path;
      }
      $path = [];
      $down = false;
    } elseif ($command === 'PD') {
      $down = true;
    } else {
      $relative = $command === 'PR';
    }
    $values = $arguments === '' ? [] : preg_split('/\s*,\s*|\s+/', $arguments);
    if (count($values) % 2) {
      throw new DomainException('El PLT contiene coordenadas incompletas.');
    }
    foreach (array_chunk($values, 2) as [$x, $y]) {
      if (!preg_match('/^[+-]?\d{1,7}$/D', $x) || !preg_match('/^[+-]?\d{1,7}$/D', $y) || ++$points > 3000) {
        throw new DomainException('Coordenadas no admitidas o más de 3.000 puntos en el PLT.');
      }
      $next = [(int) $x, (int) $y];
      if ($relative) {
        $next = [$point[0] + $next[0], $point[1] + $next[1]];
      }
      if (max(abs($next[0]), abs($next[1])) > 10000000) {
        throw new DomainException('El PLT excede el rango de coordenadas.');
      }
      if ($down && $next !== $point) {
        if (!$path) {
          $path[] = $point;
        }
        $path[] = $next;
      }
      $point = $next;
    }
  }
  if ($path) {
    $paths[] = $path;
  }
  return $paths;
}

function cross(array $a, array $b, array $c): int {
  return ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);
}

function segmentsMeet(array $a, array $b, array $c, array $d): bool {
  foreach ([0, 1] as $axis) {
    if (max($a[$axis], $b[$axis]) < min($c[$axis], $d[$axis]) ||
        max($c[$axis], $d[$axis]) < min($a[$axis], $b[$axis])) {
      return false;
    }
  }
  return (cross($a, $b, $c) <=> 0) * (cross($a, $b, $d) <=> 0) <= 0
    && (cross($c, $d, $a) <=> 0) * (cross($c, $d, $b) <=> 0) <= 0;
}

function polygonContains(array $ring, array $point): bool {
  [$x, $y] = $point;
  $inside = false;
  for ($i = 1; $i < count($ring); $i++) {
    [$ax, $ay] = $ring[$i - 1];
    [$bx, $by] = $ring[$i];
    if (($ay > $y) !== ($by > $y) && $x < ($bx - $ax) * ($y - $ay) / ($by - $ay) + $ax) {
      $inside = !$inside;
    }
  }
  return $inside;
}

function measureRings(array $rings): array {
  $edges = [];
  $areas = [];
  foreach ($rings as $id => $ring) {
    $last = count($ring) - 1;
    $area = 0;
    for ($i = 0; $i < $last; $i++) {
      $a = $ring[$i];
      $b = $ring[$i + 1];
      foreach ($edges as [$other, $index, $c, $d]) {
        if ($other === $id && ($index === $i - 1 || ($index === 0 && $i === $last - 1))) {
          continue;
        }
        if (segmentsMeet($a, $b, $c, $d)) {
          throw new DomainException('Hay contornos cerrados que se cruzan o se tocan. Separa o corrige las piezas en el PLT.');
        }
      }
      $edges[] = [$id, $i, $a, $b];
      $area += $a[0] * $b[1] - $b[0] * $a[1];
    }
    if ($area === 0) {
      throw new DomainException('El PLT contiene un contorno cerrado sin área.');
    }
    $areas[] = abs($area) / 320000;
  }
  return $areas;
}

function parsePlt(string $source): array {
  $rings = $open = [];
  foreach (plotPaths($source) as $path) {
    if (count($path) >= 4 && $path[0] === $path[count($path) - 1]) {
      $rings[] = $path;
    } else {
      $open[] = $path;
    }
  }
  if (!$rings) {
    throw new DomainException('El PLT no contiene siluetas cerradas para seleccionar.');
  }
  $areas = measureRings($rings);
  $parents = [];
  $depths = [];
  foreach ($rings as $id => $ring) {
    $containers = [];
    foreach ($rings as $candidate => $outer) {
      if ($areas[$candidate] > $areas[$id] && polygonContains($outer, $ring[0])) {
        $containers[$candidate] = $areas[$candidate];
      }
    }
    asort($containers);
    $parents[$id] = array_key_first($containers);
    $depths[$id] = count($containers);
  }
  $pieces = [];
  foreach ($rings as $id => $ring) {
    if ($depths[$id] % 2) {
      continue;
    }
    $holes = [];
    $area = $areas[$id];
    foreach ($parents as $hole => $parent) {
      if ($parent === $id) {
        $holes[] = $rings[$hole];
        $area -= $areas[$hole];
      }
    }
    $pieces[] = ['outline' => $ring, 'holes' => $holes, 'area' => $area,
      'width' => (max(array_column($ring, 0)) - min(array_column($ring, 0))) / 40,
      'height' => (max(array_column($ring, 1)) - min(array_column($ring, 1))) / 40];
  }
  return ['pieces' => $pieces, 'open' => $open];
}

function selectedArea(array $drawing, array $selection): float {
  $area = 0.0;
  foreach (array_unique($selection) as $id) {
    if (!is_int($id) || !isset($drawing['pieces'][$id])) {
      throw new DomainException('Selección de piezas inválida.');
    }
    $area += $drawing['pieces'][$id]['area'];
  }
  if ($area <= 0) {
    throw new DomainException('Selecciona al menos una silueta.');
  }
  return $area;
}
