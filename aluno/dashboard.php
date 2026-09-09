<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/curso.php';
require_once __DIR__ . '/../config/database.php';

exigirAluno();

$usuarioId = (int) $_SESSION['usuario_id'];

$stmt = $pdo->prepare(
    "SELECT
        u.nome,
        u.email,
        u.cpf,
        u.data_matricula,
        u.matricula_paga,
        p.nome AS plano_nome
     FROM usuarios u
     LEFT JOIN planos p ON p.id = u.plano_id
     WHERE u.id = :id
     LIMIT 1"
);
$stmt->execute([':id' => $usuarioId]);
$aluno = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aluno) {
    sair();
    header('Location: ../login/login.php');
    exit;
}

$moduloMaximoLiberado = numeroMaximoModuloLiberado($aluno['data_matricula'] ?? null);

$stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM aulas a
     INNER JOIN modulos m ON m.id = a.modulo_id
     WHERE m.numero <= :modulo_maximo
       AND a.publicada = 1
       AND (a.data_liberacao IS NULL OR a.data_liberacao <= NOW())"
);
$stmt->execute([':modulo_maximo' => $moduloMaximoLiberado]);
$totalAulas = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT p.aula_id)
     FROM progresso p
     INNER JOIN aulas a ON a.id = p.aula_id
     INNER JOIN modulos m ON m.id = a.modulo_id
     WHERE p.usuario_id = :usuario_id
       AND p.concluido = 1
       AND m.numero <= :modulo_maximo
       AND a.publicada = 1
       AND (a.data_liberacao IS NULL OR a.data_liberacao <= NOW())"
);
$stmt->execute([
    ':usuario_id' => $usuarioId,
    ':modulo_maximo' => $moduloMaximoLiberado,
]);
$aulasConcluidas = (int) $stmt->fetchColumn();

$percentualProgresso = $totalAulas > 0
    ? (int) round(($aulasConcluidas / $totalAulas) * 100)
    : 0;

$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT er.exercicio_id)
     FROM exercicios_respostas er
     INNER JOIN exercicios e ON e.id = er.exercicio_id
     INNER JOIN modulos m ON m.id = e.modulo_id
     WHERE er.usuario_id = :usuario_id
       AND m.numero <= :modulo_maximo"
);
$stmt->execute([
    ':usuario_id' => $usuarioId,
    ':modulo_maximo' => $moduloMaximoLiberado,
]);
$exerciciosRealizados = (int) $stmt->fetchColumn();

$modulos = $pdo
    ->query('SELECT id, numero, nome, descricao FROM modulos ORDER BY numero ASC')
    ->fetchAll(PDO::FETCH_ASSOC);

foreach ($modulos as &$modulo) {
    $numero = (int) $modulo['numero'];
    $modulo['liberado'] = moduloEstaLiberado($numero, $aluno['data_matricula'] ?? null);
    $modulo['data_liberacao'] = null;

    if (!empty($aluno['data_matricula'])) {
        try {
            $modulo['data_liberacao'] = dataLiberacaoModulo(
                $aluno['data_matricula'],
                $numero
            )->format('d/m/Y');
        } catch (InvalidArgumentException $e) {
            $modulo['data_liberacao'] = null;
        }
    }
}
unset($modulo);

$stmt = $pdo->query(
    "SELECT titulo, mensagem, data_publicacao
     FROM avisos
     WHERE ativo = 1
     ORDER BY data_publicacao DESC
     LIMIT 3"
);
$avisosRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/dashboard.html';
