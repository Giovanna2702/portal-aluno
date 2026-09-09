<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

exigirAdmin();

$materialId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$materialId) {
    http_response_code(400);
    exit('Material inválido.');
}

$stmt = $pdo->prepare(
    'SELECT arquivo, titulo FROM materiais WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $materialId]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    http_response_code(404);
    exit('Material não encontrado.');
}

$arquivo = basename($material['arquivo']);
$baseDir = realpath(__DIR__ . '/../uploads/materiais');
$caminho = realpath(__DIR__ . '/../uploads/materiais/' . $arquivo);

if (!$baseDir || !$caminho || !str_starts_with($caminho, $baseDir . DIRECTORY_SEPARATOR) || !is_file($caminho)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($caminho) ?: 'application/octet-stream';
$extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));
$nomeBase = preg_replace('/[^A-Za-z0-9_-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $material['titulo']) ?: 'material');
$nomeDownload = trim($nomeBase, '-') ?: 'material';
$nomeDownload .= $extensao !== '' ? '.' . $extensao : '';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($caminho));
header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
header('X-Content-Type-Options: nosniff');

readfile($caminho);
exit;
