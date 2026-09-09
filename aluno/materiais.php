<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/curso.php';
require_once __DIR__ . '/../config/database.php';

exigirAluno();

$usuarioId = (int) $_SESSION['usuario_id'];

$stmt = $pdo->prepare('SELECT data_matricula FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $usuarioId]);
$dataMatricula = $stmt->fetchColumn() ?: null;
$moduloMaximoLiberado = numeroMaximoModuloLiberado($dataMatricula);

$stmt = $pdo->prepare(
    "SELECT
        ma.id,
        ma.titulo,
        ma.descricao,
        ma.tipo,
        ma.data_upload,
        m.numero,
        m.nome AS modulo_nome
     FROM materiais ma
     INNER JOIN modulos m ON m.id = ma.modulo_id
     WHERE m.numero <= :modulo_maximo
     ORDER BY m.numero ASC, ma.id DESC"
);
$stmt->execute([':modulo_maximo' => $moduloMaximoLiberado]);
$materiais = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/materiais.html';