<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigirAluno();

$usuarioId = (int) $_SESSION['usuario_id'];

$stmt = $pdo->prepare(
    "SELECT id, mes, ano, valor, status, data_vencimento, data_pagamento
     FROM mensalidades
     WHERE usuario_id = :usuario_id
     ORDER BY ano DESC, mes DESC"
);
$stmt->execute([':usuario_id' => $usuarioId]);
$mensalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPendente = 0.0;
foreach ($mensalidades as &$mensalidade) {
    if ($mensalidade['status'] !== 'pago') {
        $totalPendente += (float) $mensalidade['valor'];
    }
}
unset($mensalidade);

require __DIR__ . '/mensalidades.html';