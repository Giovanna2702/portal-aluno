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

$modulos = $pdo
    ->query('SELECT id, numero, nome, descricao FROM modulos ORDER BY numero ASC')
    ->fetchAll(PDO::FETCH_ASSOC);

$totalDisponiveis = 0;
$totalConcluidas = 0;

$stmtTotal = $pdo->prepare(
    "SELECT COUNT(*)
     FROM aulas
     WHERE modulo_id = :modulo_id
       AND publicada = 1
       AND (data_liberacao IS NULL OR data_liberacao <= NOW())"
);

$stmtConcluidas = $pdo->prepare(
    "SELECT COUNT(DISTINCT p.aula_id)
     FROM progresso p
     INNER JOIN aulas a ON a.id = p.aula_id
     WHERE p.usuario_id = :usuario_id
       AND p.concluido = 1
       AND a.modulo_id = :modulo_id
       AND a.publicada = 1
       AND (a.data_liberacao IS NULL OR a.data_liberacao <= NOW())"
);

foreach ($modulos as &$modulo) {
    $numero = (int) $modulo['numero'];
    $modulo['liberado'] = $numero <= $moduloMaximoLiberado;
    $modulo['total'] = 0;
    $modulo['concluidas'] = 0;
    $modulo['percentual'] = 0;
    $modulo['data_liberacao'] = null;

    if ($modulo['liberado']) {
        $stmtTotal->execute([':modulo_id' => (int) $modulo['id']]);
        $modulo['total'] = (int) $stmtTotal->fetchColumn();

        $stmtConcluidas->execute([
            ':usuario_id' => $usuarioId,
            ':modulo_id' => (int) $modulo['id'],
        ]);
        $modulo['concluidas'] = (int) $stmtConcluidas->fetchColumn();

        $modulo['percentual'] = $modulo['total'] > 0
            ? (int) round(($modulo['concluidas'] / $modulo['total']) * 100)
            : 0;

        $totalDisponiveis += $modulo['total'];
        $totalConcluidas += $modulo['concluidas'];
    } elseif ($dataMatricula) {
        try {
            $modulo['data_liberacao'] = dataLiberacaoModulo($dataMatricula, $numero)->format('d/m/Y');
        } catch (InvalidArgumentException $e) {
            $modulo['data_liberacao'] = null;
        }
    }
}
unset($modulo);

$percentualGeral = $totalDisponiveis > 0
    ? (int) round(($totalConcluidas / $totalDisponiveis) * 100)
    : 0;

require __DIR__ . '/progresso.html';