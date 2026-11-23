<?php

function addMdf(PDO $db, array $input): void {
  $thickness = round(decimal($input['thickness'] ?? null, 0.001, 99999), 3);
  query($db, 'INSERT INTO materials (name, thickness) VALUES (?, ?)', ["MDF $thickness mm", $thickness]);
}

function stockMovement(PDO $db, array $input, int $actor, bool $purchase): void {
  $id = integer($input['material'] ?? null);
  atomic($db, function () use ($db, $input, $actor, $purchase, $id) {
    $material = query($db, 'SELECT * FROM materials WHERE id = ? FOR UPDATE', [$id])->fetch();
    if (!$material) {
      throw new DomainException('Material inexistente.');
    }
    $width = $height = $plates = $reason = $cost = null;
    $rate = $material['rate'];
    if ($purchase) {
      $cost = integer($input['purchase_cost'] ?? null);
      if ($material['thickness'] !== null) {
        $width = round(decimal($input['width'] ?? null, 0.001, 10000), 3);
        $height = round(decimal($input['height'] ?? null, 0.001, 10000), 3);
        $plates = integer($input['plates'] ?? null, 1, 10000);
        $amount = round($width * $height * $plates, 6);
        if ($amount <= 0) {
          throw new DomainException('El área de la compra debe ser positiva.');
        }
      } else {
        $amount = decimal($input['grams'] ?? null);
      }
      $rate = ($material['stock'] * ($rate ?? 0) + $cost) / ($material['stock'] + $amount);
      if ($rate >= 1000000000000) {
        throw new DomainException('El costo por unidad excede el rango permitido.');
      }
    } else {
      $amount = decimal($input['adjustment'] ?? null, -1000000000);
      $reason = field($input, 'motivo', 250);
      if (!$amount || ($amount > 0 && $rate === null)) {
        throw new DomainException('Ingresa una cantidad distinta de cero y registra el costo con una compra inicial.');
      }
    }
    $stock = round($material['stock'] + $amount, 6);
    if ($stock < 0) {
      throw new DomainException('El ajuste dejaría existencias negativas.');
    }
    query($db, 'UPDATE materials SET stock = ?, rate = ? WHERE id = ?', [$stock, $rate, $id]);
    query($db, 'INSERT INTO movements (material_id, actor_id, kind, quantity, cost, plate_width, plate_height, plates, reason, created_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$id, $actor, $purchase ? 'purchase' : 'adjustment', $amount, $cost, $width, $height, $plates, $reason, now()]);
  });
}

function productQuery(): string {
  return 'SELECT p.id, p.name, p.size, p.category, p.mdf_id, p.area, p.assembly, p.glue, p.labor, p.retail, p.wholesale,
    m.name AS material, m.rate AS mdf_rate, g.id AS glue_id, g.rate AS glue_rate
    FROM products p JOIN materials m ON m.id = p.mdf_id CROSS JOIN materials g';
}

function costBreakdown(array $product): array {
  if ($product['mdf_rate'] === null || ($product['glue'] > 0 && $product['glue_rate'] === null)) {
    throw new DomainException('Registra una compra de cada material requerido para calcular el costo.');
  }
  return [
    'mdf' => $product['area'] * $product['mdf_rate'],
    'glue' => $product['glue'] * ($product['glue_rate'] ?? 0),
    'labor' => (int) $product['labor'],
  ];
}

function plotUpload(array $files): array {
  [$name, $plot] = upload($files, 'plot', 256 * 1024);
  if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'plt' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 255) {
    throw new DomainException('Selecciona un archivo .plt con un nombre válido.');
  }
  return [$name, $plot];
}

function saveProduct(PDO $db, array $input, array $files): void {
  $id = integer($input['id'] ?? 0, 0);
  if ($id && ($files['plot']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $old = query($db, 'SELECT filename, plot FROM products WHERE id = ?', [$id])->fetch();
    if (!$old) {
      throw new DomainException('Producto inexistente.');
    }
    [$filename, $plot] = [$old['filename'], $old['plot']];
  } else {
    [$filename, $plot] = plotUpload($files);
  }
  $selection = json_decode($input['selection'] ?? '', true);
  if (!is_array($selection) || !array_is_list($selection) || array_filter($selection, fn ($value) => !is_int($value))) {
    throw new DomainException('Selecciona las piezas en la vista previa.');
  }
  $area = round(selectedArea(parsePlt($plot), $selection), 6);
  if ($area <= 0) {
    throw new DomainException('El área es demasiado pequeña para registrar su consumo.');
  }
  $mdf = query($db, 'SELECT id, rate FROM materials WHERE id = ? AND thickness IS NOT NULL', [integer($input['mdf'] ?? null)])->fetch();
  if (!$mdf) {
    throw new DomainException('Selecciona un espesor de MDF.');
  }
  $assembly = (int) isset($input['assembly']);
  $glue = $assembly ? decimal($input['glue'] ?? null, 0) : 0;
  $labor = $assembly ? integer($input['labor'] ?? null, 0) : 0;
  costBreakdown(['area' => $area, 'glue' => $glue, 'labor' => $labor, 'mdf_rate' => $mdf['rate'],
    'glue_rate' => $db->query('SELECT rate FROM materials WHERE thickness IS NULL')->fetchColumn()]);
  $values = [field($input, 'nombre', 120), field($input, 'tamaño', 100), field($input, 'categoría', 100),
    $mdf['id'], $area, $assembly, $glue, $labor, integer($input['retail'] ?? null),
    isset($input['enable_wholesale']) ? integer($input['wholesale'] ?? null) : null, $filename, $plot, json_encode($selection)];
  if ($id) {
    query($db, 'UPDATE products SET name=?, size=?, category=?, mdf_id=?, area=?, assembly=?, glue=?, labor=?, retail=?, wholesale=?, filename=?, plot=?, selection=? WHERE id=?', [...$values, $id]);
  } else {
    query($db, 'INSERT INTO products (name,size,category,mdf_id,area,assembly,glue,labor,retail,wholesale,filename,plot,selection) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', $values);
  }
}
