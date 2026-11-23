<!doctype html>
<html lang="es-PY">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · Fibrofácil</title>
  <link rel="stylesheet" href="assets/app.css">
  <script type="module" src="assets/app.js"></script>
</head>
<body class="<?= $user ? 'workspace' : 'guest' ?>">
<?php if ($user): ?>
  <aside class="sidebar">
    <a class="brand" href="?page=orders"><span class="brand-mark" aria-hidden="true">F</span> Fibrofácil</a>
    <nav aria-label="Módulos">
      <?php foreach ($pages as $key => $label): ?>
        <?php if ($user['role'] === 'admin' || in_array($key, ['orders', 'products'], true)): ?>
          <a href="?page=<?= e($key) ?>" <?= $page === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="identity">
      <strong><?= e($user['name']) ?></strong>
      <span><?= $user['role'] === 'admin' ? 'Administrador' : 'Vendedor' ?></span>
      <form method="post"><?php hidden('logout'); ?><button class="secondary small">Cerrar sesión</button></form>
    </div>
  </aside>
<?php endif; ?>
<main class="main">
  <?php if ($notice): ?><p class="notice <?= $notice[1] ? 'error' : '' ?>" role="<?= $notice[1] ? 'alert' : 'status' ?>"><?= e($notice[0]) ?></p><?php endif; ?>
  <?php if ($errorPage): ?><p class="notice error" role="alert"><?= e($errorPage) ?></p><?php endif; ?>
  <?= $content ?>
</main>
</body>
</html>
