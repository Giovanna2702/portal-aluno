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

$stmt = $pdo->prepare(
    'SELECT data_matricula FROM usuarios WHERE id = :id LIMIT 1'
);
$stmt->execute([':id' => $usuarioId]);
$dataMatricula = $stmt->fetchColumn() ?: null;
$moduloMaximoLiberado = numeroMaximoModuloLiberado($dataMatricula);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_erro'] = 'Sessão expirada. Atualize a página e tente novamente.';
        header('Location: aulas.php');
        exit;
    }

    $aulaId = filter_input(INPUT_POST, 'aula_id', FILTER_VALIDATE_INT);

    if (!$aulaId) {
        $_SESSION['flash_erro'] = 'Aula inválida.';
        header('Location: aulas.php');
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT a.id
         FROM aulas a
         INNER JOIN modulos m ON m.id = a.modulo_id
         WHERE a.id = :aula_id
           AND m.numero <= :modulo_maximo
           AND a.publicada = 1
           AND (a.data_liberacao IS NULL OR a.data_liberacao <= NOW())
         LIMIT 1"
    );
    $stmt->execute([
        ':aula_id' => $aulaId,
        ':modulo_maximo' => $moduloMaximoLiberado,
    ]);

    if (!$stmt->fetchColumn()) {
        $_SESSION['flash_erro'] = 'Esta aula ainda não está disponível para sua conta.';
        header('Location: aulas.php');
        exit;
    }

    try {
        $check = $pdo->prepare(
            "SELECT id
             FROM progresso
             WHERE usuario_id = :usuario_id
               AND aula_id = :aula_id
             LIMIT 1"
        );
        $check->execute([
            ':usuario_id' => $usuarioId,
            ':aula_id' => $aulaId,
        ]);

        if ($check->fetchColumn()) {
            $stmt = $pdo->prepare(
                "UPDATE progresso
                 SET concluido = 1
                 WHERE usuario_id = :usuario_id
                   AND aula_id = :aula_id"
            );
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO progresso (usuario_id, aula_id, concluido)
                 VALUES (:usuario_id, :aula_id, 1)"
            );
        }

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':aula_id' => $aulaId,
        ]);

        $_SESSION['flash_sucesso'] = 'Aula marcada como concluída.';
    } catch (PDOException $e) {
        error_log('Falha ao salvar progresso: ' . $e->getMessage());
        $_SESSION['flash_erro'] = 'Não foi possível atualizar o progresso.';
    }

    header('Location: aulas.php');
    exit;
}

$modulos = $pdo
    ->query('SELECT id, numero, nome, descricao FROM modulos ORDER BY numero ASC')
    ->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT
        a.id,
        a.modulo_id,
        a.titulo,
        a.descricao,
        a.video_url,
        a.duracao,
        COALESCE(p.concluido, 0) AS concluido
     FROM aulas a
     INNER JOIN modulos m ON m.id = a.modulo_id
     LEFT JOIN progresso p
       ON p.aula_id = a.id
      AND p.usuario_id = :usuario_id
     WHERE m.numero <= :modulo_maximo
       AND a.publicada = 1
       AND (a.data_liberacao IS NULL OR a.data_liberacao <= NOW())
     ORDER BY m.numero ASC, a.id ASC"
);
$stmt->execute([
    ':usuario_id' => $usuarioId,
    ':modulo_maximo' => $moduloMaximoLiberado,
]);
$aulas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$aulasPorModulo = [];
foreach ($aulas as $aula) {
    $aulasPorModulo[(int) $aula['modulo_id']][] = $aula;
}

foreach ($modulos as &$modulo) {
    $numero = (int) $modulo['numero'];
    $modulo['liberado'] = $numero <= $moduloMaximoLiberado;
    $modulo['aulas'] = $modulo['liberado']
        ? ($aulasPorModulo[(int) $modulo['id']] ?? [])
        : [];
    $modulo['data_liberacao'] = null;

    if (!$modulo['liberado'] && $dataMatricula) {
        try {
            $modulo['data_liberacao'] = dataLiberacaoModulo($dataMatricula, $numero)->format('d/m/Y');
        } catch (InvalidArgumentException $e) {
            $modulo['data_liberacao'] = null;
        }
    }
}
unset($modulo);

require __DIR__ . '/aulas.html';