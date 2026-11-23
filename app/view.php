<?php

function e(mixed $value): string {
  return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function guaranies(int|float|string $value): string {
  return 'Gs. ' . number_format(round((float) $value), 0, ',', '.');
}

function qty(int|float|string $value, int $digits = 6): string {
  return rtrim(rtrim(number_format((float) $value, $digits, ',', '.'), '0'), ',');
}

function hidden(string $action, ?int $id = null): void {
  ?>
  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
  <input type="hidden" name="action" value="<?= e($action) ?>">
  <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif;
}

function badge(string $status): string {
  $label = [
    'review' => 'En revisión', 'pending' => 'Pendiente', 'delivered' => 'Entregado',
    'approved' => 'Aprobado', 'rejected' => 'Rechazado', 'shortage' => 'Falta material',
  ][$status] ?? $status;
  return '<span class="badge ' . e($status) . '">' . e($label) . '</span>';
}

function unit(array $material): string {
  return $material['thickness'] === null ? 'g' : 'cm²';
}

function emptyRow(int $columns, string $message): void {
  ?><tr><td colspan="<?= $columns ?>" class="empty"><?= e($message) ?></td></tr><?php
}

function jsonData(mixed $data): string {
  return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}
