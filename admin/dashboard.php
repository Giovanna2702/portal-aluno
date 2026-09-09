<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

exigirAdmin();

$totalAlunos = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM usuarios
         WHERE tipo = 'aluno'"
    )
    ->fetchColumn();


$alunosAtivos = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM usuarios
         WHERE tipo = 'aluno'
         AND ativo = 1"
    )
    ->fetchColumn();


$matriculasPagas = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM usuarios
         WHERE tipo = 'aluno'
         AND matricula_paga = 1"
    )
    ->fetchColumn();


$totalAulas = (int) $pdo
    ->query("SELECT COUNT(*) FROM aulas")
    ->fetchColumn();


$totalExercicios = (int) $pdo
    ->query("SELECT COUNT(*) FROM exercicios")
    ->fetchColumn();


$totalModulos = (int) $pdo
    ->query("SELECT COUNT(*) FROM modulos")
    ->fetchColumn();


$totalMateriais = (int) $pdo
    ->query("SELECT COUNT(*) FROM materiais")
    ->fetchColumn();


$avisosAtivos = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM avisos
         WHERE ativo = 1"
    )
    ->fetchColumn();


$mensalidadesPendentes = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM mensalidades
         WHERE status IN ('pendente', 'atrasado')"
    )
    ->fetchColumn();


$progressoMedio = 0;

if ($totalAulas > 0 && $totalAlunos > 0) {

    $stmt = $pdo->query(
        "SELECT AVG(concluidas)
         FROM (
            SELECT
                u.id,
                COUNT(
                    DISTINCT CASE
                        WHEN p.concluido = 1
                        THEN p.aula_id
                    END
                ) AS concluidas

            FROM usuarios u

            LEFT JOIN progresso p
                ON p.usuario_id = u.id

            WHERE u.tipo = 'aluno'

            GROUP BY u.id
         ) AS resumo"
    );

    $mediaAulasConcluidas =
        (float) $stmt->fetchColumn();

    $progressoMedio =
        (int) round(
            ($mediaAulasConcluidas / $totalAulas) * 100
        );
}


$stmt = $pdo->query(
    "SELECT
        u.id,
        u.nome,
        u.email,
        u.ativo,
        u.matricula_paga,

        COUNT(
            DISTINCT CASE
                WHEN p.concluido = 1
                THEN p.aula_id
            END
        ) AS aulas_concluidas

     FROM usuarios u

     LEFT JOIN progresso p
        ON p.usuario_id = u.id

     WHERE u.tipo = 'aluno'

     GROUP BY
        u.id,
        u.nome,
        u.email,
        u.ativo,
        u.matricula_paga

     ORDER BY u.id DESC

     LIMIT 8"
);


$alunosRecentes =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($alunosRecentes as &$aluno) {

    $aluno["progresso"] =
        $totalAulas > 0
            ? (int) round(
                ((int) $aluno["aulas_concluidas"] / $totalAulas)
                * 100
            )
            : 0;
}

unset($aluno);


require __DIR__ . "/dashboard.html";