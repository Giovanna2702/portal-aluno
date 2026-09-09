<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigirAluno();

$usuarioId = (int) $_SESSION['usuario_id'];

$stmt = $pdo->prepare(
    "SELECT
        u.nome,
        u.cpf,
        u.email,
        u.data_matricula,
        u.matricula_paga,
        p.nome AS plano_nome
     FROM usuarios u
     LEFT JOIN planos p ON p.id = u.plano_id
     WHERE u.id = :id
     LIMIT 1"
);
$stmt->execute([':id' => $usuarioId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    sair();
    header('Location: ../login/login.php');
    exit;
}

require __DIR__ . '/perfil.html';