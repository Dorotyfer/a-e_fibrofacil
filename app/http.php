<?php

function redirectTo(string $target): never {
  header('Location: ' . $target, true, 303);
  exit;
}

function flash(string $message, bool $error = false): void {
  $_SESSION['flash'] = [$message, $error];
}

function requireRole(?array $user, string $role): void {
  if (($user['role'] ?? null) !== $role) {
    http_response_code(403);
    exit('Acceso denegado.');
  }
}

function handlePost(PDO $db, ?array $user, array $input, array $files): never {
  $token = $input['csrf'] ?? null;
  if (!is_string($token) || !hash_equals($_SESSION['csrf'], $token)) {
    http_response_code(403);
    exit('La sesión del formulario venció. Vuelve a cargar la página.');
  }
  $action = $input['action'] ?? '';
  if ($action === 'login') {
    signIn($db, $input);
  }
  if (!$user) {
    redirectTo('?page=login');
  }
  if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    redirectTo('?page=login');
  }
  if ($action === 'plot_preview') {
    requireRole($user, 'admin');
    header('Content-Type: application/json; charset=utf-8');
    try {
      [, $source] = plotUpload($files);
      echo json_encode(parsePlt($source), JSON_THROW_ON_ERROR);
    } catch (DomainException $error) {
      http_response_code(422);
      echo json_encode(['error' => $error->getMessage()]);
    }
    exit;
  }
  $page = match ($action) {
    'mdf', 'purchase', 'adjustment' => 'materials',
    'product' => 'products',
    'customer' => 'customers',
    'user', 'user_status' => 'users',
    'salary' => 'sellers',
    'order', 'review', 'proof', 'retry' => 'orders',
    default => '',
  };
  if (in_array($action, ['order', 'proof'], true)) {
    $page = 'sales';
  }
  if ($page === '') {
    http_response_code(400);
    exit('Acción desconocida.');
  }
  if (!in_array($action, ['customer', 'order', 'proof'], true)) {
    requireRole($user, 'admin');
  }
  $target = '?page=' . $page;
  $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
  if ($id && in_array($action, ['review', 'proof', 'retry'], true)) {
    $target .= '&id=' . $id;
  }
  try {
    $actor = (int) $user['id'];
    $message = 'Cambios guardados.';
    switch ($action) {
      case 'mdf':
        addMdf($db, $input);
        break;
      case 'purchase':
      case 'adjustment':
        stockMovement($db, $input, $actor, $action === 'purchase');
        break;
      case 'product':
        saveProduct($db, $input, $files);
        break;
      case 'customer':
        saveCustomer($db, $input);
        break;
      case 'user':
        saveUser($db, $input);
        break;
      case 'user_status':
        $status = $input['active'] ?? '';
        if (!in_array($status, ['0', '1'], true)) {
          throw new DomainException('Estado de usuario inválido.');
        }
        setUserActive($db, integer($id), $actor, $status === '1');
        $message = $status === '1' ? 'Usuario reactivado.' : 'Usuario desactivado.';
        break;
      case 'salary':
        saveSalary($db, $input);
        break;
      case 'order':
        $target .= '&id=' . createOrder($db, $input, proofUpload($files), $actor);
        break;
      case 'review':
        $message = reviewPayment($db, integer($id), integer($input['payment'] ?? null), $user,
          ($input['decision'] ?? '') === 'approve', $input);
        break;
      case 'proof':
        submitProof($db, integer($id), $user, ($input['stage'] ?? '') === 'deposit' ? 'deposit' : 'balance', proofUpload($files));
        break;
      case 'retry':
        $message = retryStock($db, integer($id), $user) ? 'Salida registrada.' : 'El pedido todavía tiene faltantes.';
        break;
    }
    flash($message);
  } catch (DomainException $error) {
    if ($action === 'order' && ($input['origin'] ?? '') === 'billing') {
      $target = '?page=billing';
    }
    if ($action === 'product') {
      $target .= $id ? '&edit=' . $id : '&new=1';
    }
    if ($action === 'user' && $id) {
      $target .= '&edit=' . $id;
    }
    if ($action === 'customer') {
      $target .= $id ? '&edit=' . $id : '&new=1';
    }
    flash($error->getMessage(), true);
  } catch (PDOException $error) {
    if (($error->errorInfo[1] ?? null) !== 1062) {
      throw $error;
    }
    flash('Ya existe un registro con ese valor.', true);
  }
  redirectTo($target);
}

function signIn(PDO $db, array $input): never {
  $username = $input['username'] ?? '';
  $pass = $input['password'] ?? '';
  $account = is_string($username) && is_string($pass) && strlen($username) <= 320 && strlen($pass) <= 72
    ? query($db, 'SELECT id,password,active,session_version FROM users WHERE username=?', [trim($username)])->fetch() : null;
  if (!$account || !$account['active'] || !password_verify($pass, $account['password'])) {
    usleep(400000);
    flash('Usuario o contraseña incorrectos.', true);
    redirectTo('?page=login');
  }
  session_regenerate_id(true);
  $_SESSION = ['user_id' => $account['id'], 'session_version' => $account['session_version']];
  redirectTo('?page=home');
}

function downloadProof(PDO $db, array $user, int $id): never {
  $record = query($db, 'SELECT order_id,mime FROM payments WHERE id=?', [$id])->fetch();
  if (!$record) {
    throw new DomainException('Comprobante no disponible.');
  }
  orderRecord($db, $record['order_id'], $user);
  $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$record['mime']];
  sendFile($record['mime'], "comprobante-$id.$extension", query($db, 'SELECT proof FROM payments WHERE id=?', [$id])->fetchColumn());
}

function sendFile(string $mime, string $name, string $content): never {
  header('Content-Type: ' . $mime);
  header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($name));
  header('Content-Length: ' . strlen($content));
  echo $content;
  exit;
}
