<?php

session_start();
require_once __DIR__ . "/../config/database.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../login/login.php");
    exit;
}

if ($_SESSION["tipo"] !== "admin") {
    header("Location: ../aluno/dashboard.php");
    exit;
}

$mensagem = "";
$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $modulo_id = $_POST["modulo_id"] ?? "";
    $titulo = trim($_POST["titulo"] ?? "");
    $descricao = trim($_POST["descricao"] ?? "");
    $video_url = trim($_POST["video_url"] ?? "");
    $duracao = $_POST["duracao"] ?? 0;

    if ($modulo_id === "") {
        $erro = "Selecione um módulo.";
    } elseif ($titulo === "") {
        $erro = "Digite o título da aula.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO aulas (modulo_id, titulo, descricao, video_url, duracao) VALUES (:modulo_id, :titulo, :descricao, :video_url, :duracao)");
            $stmt->execute([
                ":modulo_id" => $modulo_id,
                ":titulo" => $titulo,
                ":descricao" => $descricao,
                ":video_url" => $video_url,
                ":duracao" => $duracao,
            ]);
            $mensagem = "Aula cadastrada com sucesso!";
        } catch (PDOException $e) {
            $erro = "Erro ao cadastrar a aula: " . $e->getMessage();
        }
    }
}

$modulos = $pdo->query("SELECT * FROM modulos ORDER BY numero ASC")->fetchAll(PDO::FETCH_ASSOC);
$aulas = $pdo->query("SELECT aulas.id, aulas.titulo, aulas.descricao, aulas.video_url, aulas.duracao, modulos.numero, modulos.nome AS modulo_nome FROM aulas INNER JOIN modulos ON aulas.modulo_id = modulos.id ORDER BY modulos.numero ASC, aulas.id DESC")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . "/aulas.html";
