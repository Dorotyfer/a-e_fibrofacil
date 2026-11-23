<?php
$accounts = $db->query('SELECT id,name,username,role,active FROM users ORDER BY name,id')->fetchAll();
$edit = isset($_GET['edit']) ? query($db, 'SELECT id,name,username,role FROM users WHERE id=?', [integer($_GET['edit'])])->fetch() : [];
if ($edit === false) {
  throw new DomainException('Usuario inexistente.');
}
?>
<header class="page-header"><h1>Usuarios</h1></header>
<div class="columns">
  <section class="panel">
    <h2>Cuentas</h2>
    <div class="table-wrap"><table>
      <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acción</th></tr></thead>
      <tbody><?php foreach ($accounts as $account): ?>
        <tr><td><a href="?page=users&edit=<?= $account['id'] ?>"><?= e($account['name']) ?></a></td>
          <td><?= e($account['username']) ?></td><td><?= $account['role'] === 'admin' ? 'Administrador' : 'Vendedor' ?></td>
          <td><?= $account['active'] ? 'Activo' : 'Inactivo' ?></td><td>
            <?php if ($account['id'] != $user['id']): ?>
              <form method="post">
                <?php hidden('user_status', $account['id']); ?>
                <input type="hidden" name="active" value="<?= $account['active'] ? '0' : '1' ?>">
                <button class="secondary small" aria-label="<?= $account['active'] ? 'Desactivar ' : 'Reactivar ' ?><?= e($account['name']) ?>"><?= $account['active'] ? 'Desactivar' : 'Reactivar' ?></button>
              </form>
            <?php endif; ?>
          </td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>
  <section class="panel">
    <h2><?= $edit ? 'Editar usuario' : 'Añadir usuario' ?></h2>
    <form method="post" class="stack">
      <?php hidden('user', $edit['id'] ?? null); ?>
      <label>Nombre<input name="nombre" maxlength="120" value="<?= e($edit['name'] ?? '') ?>" required></label>
      <label>Usuario<input name="usuario" maxlength="80" value="<?= e($edit['username'] ?? '') ?>" autocomplete="off" required></label>
      <?php if ($edit): ?>
        <input type="hidden" name="role" value="<?= e($edit['role']) ?>">
        <p>Rol · <?= $edit['role'] === 'admin' ? 'Administrador' : 'Vendedor' ?></p>
      <?php else: ?>
        <label>Rol<select name="role"><option value="seller">Vendedor</option><option value="admin">Administrador</option></select></label>
      <?php endif; ?>
      <label><?= $edit ? 'Nueva contraseña (opcional)' : 'Contraseña (mínimo 12 caracteres)' ?><input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></label>
      <div class="actions"><button>Guardar usuario</button><?php if ($edit): ?><a href="?page=users">Cancelar</a><?php endif; ?></div>
    </form>
  </section>
</div>
