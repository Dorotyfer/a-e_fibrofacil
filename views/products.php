<?php
$admin = $user['role'] === 'admin';
if ($admin && (isset($_GET['edit']) || isset($_GET['new']))) {
  require __DIR__ . '/product-form.php';
  return;
}
$products = $db->query(productQuery() . ' WHERE g.thickness IS NULL ORDER BY p.name,p.id')->fetchAll();
?>
<header class="page-header">
  <h1>Productos</h1>
  <?php if ($admin): ?><a class="button" href="?page=products&new=1">Añadir producto</a><?php endif; ?>
</header>
<section class="panel">
  <div class="table-wrap"><table>
    <thead><tr><th>Producto</th><th>Categoría</th><th>Tamaño</th>
      <?php if ($admin): ?><th>Costo de producción</th><?php endif; ?>
      <th>Minorista</th><th>Mayorista</th><?php if ($admin): ?><th>Diseño</th><?php endif; ?></tr></thead>
    <tbody>
      <?php if (!$products) emptyRow($admin ? 7 : 5, 'Todavía no hay productos en el catálogo.'); ?>
      <?php foreach ($products as $product): ?>
        <tr><td><?php if ($admin): ?><a href="?page=products&edit=<?= $product['id'] ?>"><?= e($product['name']) ?></a>
          <?php else: ?><?= e($product['name']) ?><?php endif; ?></td>
          <td><?= e($product['category']) ?></td><td><?= e($product['size']) ?></td>
          <?php if ($admin): ?><td><?= guaranies(array_sum(costBreakdown($product))) ?></td><?php endif; ?>
          <td><?= guaranies($product['retail']) ?></td><td><?= $product['wholesale'] === null ? '—' : guaranies($product['wholesale']) ?></td>
          <?php if ($admin): ?><td><a href="?page=plot&id=<?= $product['id'] ?>">Descargar PLT</a></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
