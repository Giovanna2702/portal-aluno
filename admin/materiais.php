<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

exigirAdmin();

$csrfToken = gerarTokenCsrf();
$mensagem = $_SESSION["flash_sucesso"] ?? "";
$erro = $_SESSION["flash_erro"] ?? "";
unset($_SESSION["flash_sucesso"], $_SESSION["flash_erro"]);

$uploadDir = __DIR__ . "/../uploads/materiais";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$edicao = null;

function apagarArquivoMaterial(string $uploadDir, ?string $arquivo): void
{
    if (!$arquivo) {
        return;
    }

    $caminho = $uploadDir . DIRECTORY_SEPARATOR . basename($arquivo);
    if (is_file($caminho)) {
        unlink($caminho);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!validarTokenCsrf($_POST["csrf_token"] ?? null)) {
        $_SESSION["flash_erro"] = "Sessão expirada. Atualize a página e tente novamente.";
        header("Location: materiais.php");
        exit;
    }

    $acao = $_POST["acao"] ?? "";

    if ($acao === "salvar") {
        $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT) ?: null;
        $moduloId = filter_input(INPUT_POST, "modulo_id", FILTER_VALIDATE_INT);
        $titulo = trim($_POST["titulo"] ?? "");
        $descricao = trim($_POST["descricao"] ?? "");
        $tipo = trim($_POST["tipo"] ?? "material");
        $arquivoAtual = null;

        if ($id) {
            $stmt = $pdo->prepare("SELECT arquivo FROM materiais WHERE id = :id");
            $stmt->execute([":id" => $id]);
            $arquivoAtual = $stmt->fetchColumn() ?: null;
        }

        if (!$moduloId || $titulo === "") {
            $erro = "Informe o módulo e o título do material.";
        } else {
            $novoArquivo = $arquivoAtual;
            $arquivoEnviado = $_FILES["arquivo"] ?? null;

            if ($arquivoEnviado && $arquivoEnviado["error"] !== UPLOAD_ERR_NO_FILE) {
                if ($arquivoEnviado["error"] !== UPLOAD_ERR_OK) {
                    $erro = "O upload do arquivo falhou.";
                } elseif (!is_uploaded_file($arquivoEnviado["tmp_name"])) {
                    $erro = "Arquivo de upload inválido.";
                } elseif ($arquivoEnviado["size"] > 10 * 1024 * 1024) {
                    $erro = "O arquivo deve ter no máximo 10 MB.";
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($arquivoEnviado["tmp_name"]);
                    $permitidos = [
                        "application/pdf" => "pdf",
                        "text/plain" => "txt",
                        "audio/mpeg" => "mp3",
                        "audio/mp4" => "m4a",
                        "audio/x-m4a" => "m4a",
                    ];

                    if (!isset($permitidos[$mime])) {
                        $erro = "Tipo de arquivo não permitido. Use PDF, TXT, MP3 ou M4A.";
                    } else {
                        $novoArquivo = bin2hex(random_bytes(16)) . "." . $permitidos[$mime];
                        $destino = $uploadDir . DIRECTORY_SEPARATOR . $novoArquivo;

                        if (!move_uploaded_file($arquivoEnviado["tmp_name"], $destino)) {
                            $erro = "Não foi possível salvar o arquivo enviado.";
                            $novoArquivo = $arquivoAtual;
                        }
                    }
                }
            }

            if ($erro === "") {
                try {
                    if ($id) {
                        $stmt = $pdo->prepare(
                            "UPDATE materiais
                             SET modulo_id = :modulo_id, titulo = :titulo, descricao = :descricao, tipo = :tipo, arquivo = :arquivo
                             WHERE id = :id"
                        );
                        $stmt->execute([
                            ":modulo_id" => $moduloId,
                            ":titulo" => $titulo,
                            ":descricao" => $descricao,
                            ":tipo" => $tipo,
                            ":arquivo" => $novoArquivo,
                            ":id" => $id,
                        ]);

                        if ($novoArquivo !== $arquivoAtual && $arquivoAtual) {
                            apagarArquivoMaterial($uploadDir, $arquivoAtual);
                        }

                        $_SESSION["flash_sucesso"] = "Material atualizado com sucesso.";
                    } else {
                        if (!$novoArquivo) {
                            $erro = "Selecione um arquivo para o novo material.";
                        } else {
                            $stmt = $pdo->prepare(
                                "INSERT INTO materiais (modulo_id, titulo, descricao, arquivo, tipo)
                                 VALUES (:modulo_id, :titulo, :descricao, :arquivo, :tipo)"
                            );
                            $stmt->execute([
                                ":modulo_id" => $moduloId,
                                ":titulo" => $titulo,
                                ":descricao" => $descricao,
                                ":arquivo" => $novoArquivo,
                                ":tipo" => $tipo,
                            ]);
                            $_SESSION["flash_sucesso"] = "Material cadastrado com sucesso.";
                        }
                    }

                    if ($erro === "") {
                        header("Location: materiais.php");
                        exit;
                    }
                } catch (PDOException $e) {
                    if ($novoArquivo && $novoArquivo !== $arquivoAtual) {
                        apagarArquivoMaterial($uploadDir, $novoArquivo);
                    }
                    $erro = "Não foi possível salvar o material.";
                }
            }
        }

        $edicao = [
            "id" => $id,
            "modulo_id" => $moduloId ?: "",
            "titulo" => $titulo,
            "descricao" => $descricao,
            "tipo" => $tipo,
            "arquivo" => $arquivoAtual,
        ];
    }

    if ($acao === "excluir") {
        $id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

        if (!$id) {
            $_SESSION["flash_erro"] = "Material inválido.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT arquivo FROM materiais WHERE id = :id");
                $stmt->execute([":id" => $id]);
                $arquivo = $stmt->fetchColumn() ?: null;

                $stmt = $pdo->prepare("DELETE FROM materiais WHERE id = :id");
                $stmt->execute([":id" => $id]);

                apagarArquivoMaterial($uploadDir, $arquivo);
                $_SESSION["flash_sucesso"] = "Material excluído com sucesso.";
            } catch (PDOException $e) {
                $_SESSION["flash_erro"] = "Não foi possível excluir o material.";
            }
        }

        header("Location: materiais.php");
        exit;
    }
}

$editarId = filter_input(INPUT_GET, "editar", FILTER_VALIDATE_INT);
if ($editarId && $edicao === null) {
    $stmt = $pdo->prepare("SELECT * FROM materiais WHERE id = :id");
    $stmt->execute([":id" => $editarId]);
    $edicao = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$modulos = $pdo->query("SELECT id, numero, nome FROM modulos ORDER BY numero ASC")->fetchAll(PDO::FETCH_ASSOC);
$materiais = $pdo->query(
    "SELECT ma.id, ma.titulo, ma.descricao, ma.arquivo, ma.tipo, ma.data_upload,
            m.numero, m.nome AS modulo_nome
     FROM materiais ma
     INNER JOIN modulos m ON m.id = ma.modulo_id
     ORDER BY m.numero ASC, ma.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . "/materiais.html";
