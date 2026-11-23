<?php
$edit = isset($_GET['edit']) ? query($db, 'SELECT * FROM customers WHERE id=?', [integer($_GET['edit'])])->fetch() : [];
if ($edit === false) {
  throw new DomainException('Cliente inexistente.');
}
if (isset($_GET['new']) || $edit):
?>
<header class="page-header"><h1><?= $edit ? 'Editar cliente' : 'Añadir cliente' ?></h1></header>
<section class="panel">
  <form method="post" class="stack">
    <?php hidden('customer', $edit['id'] ?? null); ?>
    <label>Nombre<input name="nombre" maxlength="120" value="<?= e($edit['name'] ?? '') ?>" required></label>
    <label>Teléfono<input name="teléfono" maxlength="40" value="<?= e($edit['phone'] ?? '') ?>"></label>
    <label>Correo<input name="correo" type="email" maxlength="160" value="<?= e($edit['email'] ?? '') ?>"></label>
    <label>Dirección<input name="dirección" maxlength="250" value="<?= e($edit['address'] ?? '') ?>"></label>
    <button>Guardar cliente</button>
  </form>
</section>
<?php else:
$customers = $db->query('SELECT * FROM customers ORDER BY name,id')->fetchAll();
?>
<header class="page-header"><h1>Clientes</h1><a class="button" href="?page=customers&new=1">Añadir cliente</a></header>
<section class="panel"><div class="table-wrap"><table>
  <thead><tr><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Dirección</th></tr></thead>
  <tbody>
    <?php if (!$customers) emptyRow(4, 'Todavía no hay clientes registrados.'); ?>
    <?php foreach ($customers as $customer): ?>
      <tr><td><a href="?page=customers&edit=<?= $customer['id'] ?>"><?= e($customer['name']) ?></a></td>
        <td><?= e($customer['phone']) ?></td><td><?= e($customer['email']) ?></td><td><?= e($customer['address']) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
</table></div></section>
<?php endif; ?>
