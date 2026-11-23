<?php
$sellers = $db->query("SELECT id,name,username FROM users WHERE role='seller' ORDER BY name")->fetchAll();
$salaries = $db->query('SELECT u.name,s.month,s.amount FROM salaries s JOIN users u ON u.id=s.user_id ORDER BY s.month DESC,u.name')->fetchAll();
?>
<header class="page-header"><h1>Vendedores</h1></header>
<section class="panel">
  <div class="section-header"><h2>Cuentas</h2><a href="?page=users">Gestionar usuarios</a></div>
  <div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Usuario</th></tr></thead>
    <tbody><?php if (!$sellers) emptyRow(2, 'Todavía no hay vendedores.'); ?>
      <?php foreach ($sellers as $person): ?><tr><td><?= e($person['name']) ?></td><td><?= e($person['username']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<div class="columns">
  <?php if ($sellers): ?>
    <section class="panel">
      <h2>Configurar salario mensual</h2>
      <form method="post" class="stack">
        <?php hidden('salary'); ?>
        <label>Vendedor<select name="seller"><?php foreach ($sellers as $person): ?>
          <option value="<?= $person['id'] ?>"><?= e($person['name']) ?></option>
        <?php endforeach; ?></select></label>
        <label>Vigente desde<input type="month" name="month" value="<?= date('Y-m') ?>" required></label>
        <label>Salario (Gs.)<input type="number" name="salary" min="0" step="1" required></label>
        <button>Guardar salario</button>
      </form>
    </section>
  <?php endif; ?>
</div>
<section class="panel">
  <h2>Historial de salarios</h2>
  <div class="table-wrap"><table><thead><tr><th>Desde</th><th>Vendedor</th><th>Salario</th></tr></thead>
    <tbody><?php if (!$salaries) emptyRow(3, 'Todavía no hay salarios configurados.'); ?>
      <?php foreach ($salaries as $salary): ?><tr><td><?= e($salary['month']) ?></td><td><?= e($salary['name']) ?></td><td><?= guaranies($salary['amount']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
