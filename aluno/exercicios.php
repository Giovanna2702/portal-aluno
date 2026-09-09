<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/curso.php';
require_once __DIR__ . '/../config/database.php';

exigirAluno();

$usuarioId = (int) $_SESSION['usuario_id'];
$csrfToken = gerarTokenCsrf();
$mensagem = $_SESSION['flash_sucesso'] ?? '';
$erro = $_SESSION['flash_erro'] ?? '';
unset($_SESSION['flash_sucesso'], $_SESSION['flash_erro']);

$stmt = $pdo->prepare('SELECT data_matricula FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $usuarioId]);
$dataMatricula = $stmt->fetchColumn() ?: null;
$moduloMaximoLiberado = numeroMaximoModuloLiberado($dataMatricula);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_erro'] = 'Sessão expirada. Atualize a página e tente novamente.';
        header('Location: exercicios.php');
        exit;
    }

    $exercicioId = filter_input(INPUT_POST, 'exercicio_id', FILTER_VALIDATE_INT);
    $resposta = strtoupper(trim($_POST['resposta'] ?? ''));

    if (!$exercicioId || !in_array($resposta, ['A', 'B', 'C', 'D'], true)) {
        $_SESSION['flash_erro'] = 'Selecione uma alternativa válida.';
        header('Location: exercicios.php');
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT e.id, e.resposta_correta
         FROM exercicios e
         INNER JOIN modulos m ON m.id = e.modulo_id
         WHERE e.id = :id
           AND m.numero <= :modulo_maximo
         LIMIT 1"
    );
    $stmt->execute([
        ':id' => $exercicioId,
        ':modulo_maximo' => $moduloMaximoLiberado,
    ]);
    $exercicio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exercicio) {
        $_SESSION['flash_erro'] = 'Este exercício ainda não está disponível para sua conta.';
        header('Location: exercicios.php');
        exit;
    }

    $acertou = hash_equals(strtoupper($exercicio['resposta_correta']), $resposta) ? 1 : 0;

    try {
        $check = $pdo->prepare(
            "SELECT id
             FROM exercicios_respostas
             WHERE usuario_id = :usuario_id
               AND exercicio_id = :exercicio_id
             LIMIT 1"
        );
        $check->execute([
            ':usuario_id' => $usuarioId,
            ':exercicio_id' => $exercicioId,
        ]);

        if ($check->fetchColumn()) {
            $stmt = $pdo->prepare(
                "UPDATE exercicios_respostas
                 SET resposta = :resposta,
                     acertou = :acertou,
                     data_resposta = CURRENT_TIMESTAMP
                 WHERE usuario_id = :usuario_id
                   AND exercicio_id = :exercicio_id"
            );
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO exercicios_respostas
                    (usuario_id, exercicio_id, resposta, acertou)
                 VALUES
                    (:usuario_id, :exercicio_id, :resposta, :acertou)"
            );
        }

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':exercicio_id' => $exercicioId,
            ':resposta' => $resposta,
            ':acertou' => $acertou,
        ]);

        $_SESSION['flash_sucesso'] = $acertou
            ? 'Resposta correta.'
            : 'Resposta registrada. Revise o conteúdo e tente novamente.';
    } catch (PDOException $e) {
        error_log('Falha ao salvar resposta: ' . $e->getMessage());
        $_SESSION['flash_erro'] = 'Não foi possível registrar sua resposta.';
    }

    header('Location: exercicios.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT
        e.id,
        e.modulo_id,
        e.pergunta,
        e.alternativa_a,
        e.alternativa_b,
        e.alternativa_c,
        e.alternativa_d,
        m.numero,
        m.nome AS modulo_nome,
        er.resposta AS resposta_aluno,
        er.acertou
     FROM exercicios e
     INNER JOIN modulos m ON m.id = e.modulo_id
     LEFT JOIN exercicios_respostas er
       ON er.exercicio_id = e.id
      AND er.usuario_id = :usuario_id
     WHERE m.numero <= :modulo_maximo
     ORDER BY m.numero ASC, e.id ASC"
);
$stmt->execute([
    ':usuario_id' => $usuarioId,
    ':modulo_maximo' => $moduloMaximoLiberado,
]);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

$modulos = [];
foreach ($lista as $item) {
    $moduloId = (int) $item['modulo_id'];

    if (!isset($modulos[$moduloId])) {
        $modulos[$moduloId] = [
            'numero' => (int) $item['numero'],
            'nome' => $item['modulo_nome'],
            'exercicios' => [],
        ];
    }

    $modulos[$moduloId]['exercicios'][] = $item;
}

require __DIR__ . '/exercicios.html';