<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigirAluno();

$avisos = $pdo
    ->query(
        "SELECT id, titulo, mensagem, data_publicacao
         FROM avisos
         WHERE ativo = 1
         ORDER BY data_publicacao DESC"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/avisos.html';