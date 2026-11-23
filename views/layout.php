<!doctype html>
<html lang="es-PY">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Fibrofácil</title>
  <link rel="stylesheet" href="assets/app.css?v=5">
  <script type="module" src="assets/app.js?v=4"></script>
</head>
<body class="<?= $user ? 'workspace' : 'guest' ?>">
<?php if ($user): ?>
  <header class="topbar">
    <a class="brand" href="?page=home"><span class="brand-mark" aria-hidden="true">F</span> Fibrofácil</a>
    <div class="account-name"><strong><?= e($user['name']) ?></strong><span><?= $user['role'] === 'admin' ? 'Administrador' : 'Vendedor' ?></span></div>
    <form method="post"><?php hidden('logout'); ?><button class="secondary small">Cerrar sesión</button></form>
  </header>
  <?php if ($page === 'home'):
    $menu = [
      'orders' => ['▤', 'Pedidos', 'Ventas, pagos y entregas'],
      'sales' => ['▤', 'Ventas', 'Registrar ventas y gestionar entregas'],
      'billing' => ['▤', 'Facturación', 'Preparar y registrar una venta'],
      'customers' => ['◎', 'Clientes', 'Datos y contactos'],
      'products' => ['◇', 'Productos', 'Diseños, costos y precios'],
      'materials' => ['▦', 'Materia prima', 'Compras y existencias'],
      'sellers' => ['♙', 'Vendedores', 'Salarios y equipo de ventas'],
      'users' => ['◉', 'Usuarios', 'Cuentas y accesos'],
      'sales_sellers' => ['▥', 'Ventas por vendedor', 'Cobros, salarios y comisiones'],
      'sales_general' => ['▤', 'Ventas generales', 'Pedidos y cobros'],
      'material_purchases' => ['▦', 'Compras de materia prima', 'Entradas y costos'],
      'customer_report' => ['◎', 'Reporte de clientes', 'Pedidos por cliente'],
    ];
    $groups = ['Trabajo diario' => ['orders', 'customers', 'products', 'materials'],
      'Administración' => ['sales', 'billing', 'sellers', 'users'],
      'Reportes' => ['sales_sellers', 'sales_general', 'material_purchases', 'customer_report']];
  ?><nav class="module-nav" aria-label="Módulos">
    <?php foreach ($groups as $group => $keys):
      $available = array_filter($keys, fn ($key) => $user['role'] === 'admin' || in_array($key, ['orders', 'sales', 'billing', 'customers', 'products'], true));
      if (!$available) continue;
    ?>
      <section class="menu-section">
        <div class="menu-section-heading"><h2><?= e($group) ?></h2><span><?= count($available) ?> módulos</span></div>
        <div class="menu-links">
          <?php foreach ($available as $key): [$icon, $label, $description] = $menu[$key]; ?>
            <a href="?page=<?= e($key) ?>" data-module class="module-card">
              <span class="module-icon" aria-hidden="true"><?= e($icon) ?></span>
              <span class="module-copy"><strong><?= e($label) ?></strong><span><?= e($description) ?></span></span>
              <span class="module-arrow" aria-hidden="true">↗</span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
<?php endif; ?>
<main class="main" data-page="<?= e($page) ?>">
  <?php if ($notice): ?><p class="notice <?= $notice[1] ? 'error' : '' ?>" role="<?= $notice[1] ? 'alert' : 'status' ?>"><?= e($notice[0]) ?></p><?php endif; ?>
  <?php if ($errorPage): ?><p class="notice error" role="alert"><?= e($errorPage) ?></p><?php endif; ?>
  <?= $content ?>
</main>
</body>
</html>
