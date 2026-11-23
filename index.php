<?php
require __DIR__ . '/app/core.php';
require __DIR__ . '/app/geometry.php';
require __DIR__ . '/app/catalog.php';
require __DIR__ . '/app/orders.php';
require __DIR__ . '/app/accounts.php';
require __DIR__ . '/app/customers.php';
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
header("Content-Security-Policy: default-src 'self'; frame-ancestors 'self'; base-uri 'none'; form-action 'self'; style-src 'self'");

$user = null;
$errorPage = null;
$page = 'login';
$pages = ['orders' => 'Pedidos', 'sales' => 'Ventas', 'billing' => 'Facturación', 'customers' => 'Clientes', 'products' => 'Productos', 'materials' => 'Materia prima',
  'sellers' => 'Vendedores', 'users' => 'Usuarios', 'reports' => 'Reportes',
  'sales_sellers' => 'Ventas por vendedor', 'sales_general' => 'Ventas generales',
  'material_purchases' => 'Compras de materia prima', 'customer_report' => 'Reporte de clientes'];
try {
  $db = database();
  if (isset($_SESSION['user_id'])) {
    $user = query($db, 'SELECT id,name,role FROM users WHERE id=? AND active=1 AND session_version=?',
      [$_SESSION['user_id'], $_SESSION['session_version'] ?? -1])->fetch() ?: null;
    if (!$user) {
      unset($_SESSION['user_id'], $_SESSION['session_version']);
    }
  }
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
      http_response_code(413);
      throw new DomainException('El envío supera el límite de carga de PHP.');
    }
    handlePost($db, $user, $_POST, $_FILES);
  }
  if ($user) {
    $page = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home';
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
    if (in_array($page, ['materials', 'sellers', 'users', 'reports', 'sales_sellers', 'sales_general', 'material_purchases', 'customer_report'], true)) {
      requireRole($user, 'admin');
    }
    if (!isset($pages[$page]) && $page !== 'home') {
      $page = 'home';
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

$title = $page === 'home' ? 'Menú principal' : ($pages[$page] ?? 'Ingresar');
$notice = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
ob_start();
if (!$errorPage) {
  try {
    $reportPages = ['sales_sellers', 'sales_general', 'material_purchases', 'customer_report'];
    $view = in_array($page, $reportPages, true) ? 'report-detail' : ($page === 'sales' ? 'orders' : $page);
    require __DIR__ . '/views/' . $view . '.php';
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
