<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/geometry.php';
require __DIR__ . '/app/catalog.php';
require __DIR__ . '/app/orders.php';
require __DIR__ . '/app/accounts.php';
require __DIR__ . '/app/http.php';
require __DIR__ . '/app/view.php';

ini_set('session.use_strict_mode', '1');
session_name('fibrofacil_session');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax',
  'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'; style-src 'self'");

$user = null;
$errorPage = null;
$page = 'login';
$pages = ['orders' => 'Pedidos', 'products' => 'Productos', 'materials' => 'Materia prima',
  'sellers' => 'Vendedores', 'reports' => 'Reportes'];
try {
  $db = database();
  if (isset($_SESSION['user_id'])) {
    $user = query($db, 'SELECT id,name,role FROM users WHERE id=?', [$_SESSION['user_id']])->fetch() ?: null;
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
      http_response_code(413);
      throw new DomainException('El envío supera el límite de carga de PHP.');
    }
    handlePost($db, $user, $_POST, $_FILES);
  }
  if ($user) {
    $page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'orders';
    if ($page === 'proof') {
      downloadProof($db, $user, integer($_GET['id'] ?? null));
    }
    if ($page === 'plot') {
      requireRole($user, 'admin');
      $plot = query($db, 'SELECT filename,plot FROM products WHERE id=?', [integer($_GET['id'] ?? null)])->fetch();
      if (!$plot) {
        throw new DomainException('Archivo PLT no disponible.');
      }
      sendFile('application/octet-stream', $plot['filename'], $plot['plot']);
    }
    if (in_array($page, ['materials', 'sellers', 'reports'], true)) {
      requireRole($user, 'admin');
    }
    if (!isset($pages[$page])) {
      $page = 'orders';
    }
  }
} catch (DomainException $error) {
  if (http_response_code() === 200) {
    http_response_code(404);
  }
  $errorPage = $error->getMessage();
} catch (Throwable $error) {
  error_log((string) $error);
  http_response_code(500);
  $errorPage = 'No se pudo acceder al sistema. Verifica la instalación y la conexión a MySQL.';
}

$title = $pages[$page] ?? 'Ingresar';
$notice = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
ob_start();
if (!$errorPage) {
  try {
    require __DIR__ . '/views/' . $page . '.php';
  } catch (DomainException $error) {
    ob_clean();
    http_response_code(404);
    $errorPage = $error->getMessage();
  } catch (Throwable $error) {
    ob_clean();
    error_log((string) $error);
    http_response_code(500);
    $errorPage = 'No se pudo cargar la página.';
  }
}
$content = ob_get_clean();
require __DIR__ . '/views/layout.php';
