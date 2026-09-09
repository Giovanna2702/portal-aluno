<?php

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/database.php";

exigirAdmin();

$csrfToken = gerarTokenCsrf();

$mensagem =
    $_SESSION["flash_sucesso"] ?? "";

$erro =
    $_SESSION["flash_erro"] ?? "";

unset(
    $_SESSION["flash_sucesso"],
    $_SESSION["flash_erro"]
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (
        !validarTokenCsrf(
            $_POST["csrf_token"] ?? null
        )
    ) {

        $_SESSION["flash_erro"] =
            "Sessão expirada. Atualize a página e tente novamente.";

        header("Location: alunos.php");
        exit;
    }


    $acao =
        $_POST["acao"] ?? "cadastrar";


    /*
    |--------------------------------------------------------------------------
    | CADASTRAR ALUNO
    |--------------------------------------------------------------------------
    */

    if ($acao === "cadastrar") {

        $nome =
            trim($_POST["nome"] ?? "");

        $cpf =
            trim($_POST["cpf"] ?? "");

        $email =
            trim($_POST["email"] ?? "");

        $senha =
            $_POST["senha"] ?? "";

        $planoId =
            filter_input(
                INPUT_POST,
                "plano_id",
                FILTER_VALIDATE_INT
            );


        if (
            $nome === ""
            || $email === ""
            || $senha === ""
            || !$planoId
        ) {

            $erro =
                "Preencha todos os campos obrigatórios.";

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $erro =
                "Informe um e-mail válido.";

        } elseif (
            strlen($senha) < 6
        ) {

            $erro =
                "A senha deve ter pelo menos 6 caracteres.";

        } else {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM usuarios
                 WHERE email = :email
                 LIMIT 1"
            );

            $stmt->execute([
                ":email" => $email
            ]);


            if ($stmt->fetchColumn()) {

                $erro =
                    "Este e-mail já está cadastrado.";

            } else {

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM planos
                     WHERE id = :id"
                );

                $stmt->execute([
                    ":id" => $planoId
                ]);


                if (!$stmt->fetchColumn()) {

                    $erro =
                        "O plano selecionado não existe.";

                } else {

                    try {

                        $stmt = $pdo->prepare(
                            "INSERT INTO usuarios
                            (
                                nome,
                                cpf,
                                email,
                                senha,
                                tipo,
                                plano_id,
                                ativo,
                                matricula_paga,
                                data_matricula
                            )
                            VALUES
                            (
                                :nome,
                                :cpf,
                                :email,
                                :senha,
                                'aluno',
                                :plano_id,
                                1,
                                0,
                                CURDATE()
                            )"
                        );


                        $stmt->execute([

                            ":nome" =>
                                $nome,

                            ":cpf" =>
                                $cpf !== ""
                                    ? $cpf
                                    : null,

                            ":email" =>
                                $email,

                            ":senha" =>
                                password_hash(
                                    $senha,
                                    PASSWORD_DEFAULT
                                ),

                            ":plano_id" =>
                                $planoId

                        ]);


                        $_SESSION[
                            "flash_sucesso"
                        ] =
                            "Aluno cadastrado com sucesso.";


                        header(
                            "Location: alunos.php"
                        );

                        exit;

                    } catch (PDOException $e) {

                        $erro =
                            "Não foi possível cadastrar o aluno.";
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | ATIVAR / INATIVAR / ALTERAR MATRÍCULA
    |--------------------------------------------------------------------------
    */

    if (
        $acao === "alternar_ativo"
        || $acao === "alternar_matricula"
    ) {

        $id =
            filter_input(
                INPUT_POST,
                "id",
                FILTER_VALIDATE_INT
            );


        if (!$id) {

            $_SESSION["flash_erro"] =
                "Aluno inválido.";

        } else {

            try {

                if (
                    $acao ===
                    "alternar_ativo"
                ) {

                    $stmt = $pdo->prepare(
                        "UPDATE usuarios

                         SET ativo =
                            CASE
                                WHEN ativo = 1
                                THEN 0
                                ELSE 1
                            END

                         WHERE id = :id
                         AND tipo = 'aluno'"
                    );


                    $stmt->execute([
                        ":id" => $id
                    ]);


                    $_SESSION[
                        "flash_sucesso"
                    ] =
                        "Status do aluno atualizado.";

                } else {

                    $stmt = $pdo->prepare(
                        "UPDATE usuarios

                         SET matricula_paga =
                            CASE
                                WHEN matricula_paga = 1
                                THEN 0
                                ELSE 1
                            END

                         WHERE id = :id
                         AND tipo = 'aluno'"
                    );


                    $stmt->execute([
                        ":id" => $id
                    ]);


                    $_SESSION[
                        "flash_sucesso"
                    ] =
                        "Situação da matrícula atualizada.";
                }

            } catch (PDOException $e) {

                $_SESSION["flash_erro"] =
                    "Não foi possível atualizar o aluno.";
            }
        }


        header(
            "Location: alunos.php"
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| PLANOS
|--------------------------------------------------------------------------
*/

$planos = $pdo
    ->query(
        "SELECT
            id,
            nome,
            valor

         FROM planos

         ORDER BY nome ASC"
    )
    ->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| TOTAL DE AULAS
|--------------------------------------------------------------------------
*/

$totalAulas = (int) $pdo
    ->query(
        "SELECT COUNT(*)
         FROM aulas"
    )
    ->fetchColumn();


/*
|--------------------------------------------------------------------------
| LISTAGEM DE ALUNOS
|--------------------------------------------------------------------------
*/

$sql =
    "SELECT
        u.id,
        u.nome,
        u.cpf,
        u.email,
        u.ativo,
        u.matricula_paga,
        u.data_matricula,

        p.nome AS plano,

        COUNT(
            DISTINCT CASE
                WHEN pr.concluido = 1
                THEN pr.aula_id
            END
        ) AS aulas_concluidas

     FROM usuarios u

     LEFT JOIN planos p
        ON p.id = u.plano_id

     LEFT JOIN progresso pr
        ON pr.usuario_id = u.id

     WHERE u.tipo = 'aluno'

     GROUP BY
        u.id,
        u.nome,
        u.cpf,
        u.email,
        u.ativo,
        u.matricula_paga,
        u.data_matricula,
        p.nome

     ORDER BY u.id DESC";


$alunos =
    $pdo
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);


foreach ($alunos as &$aluno) {

    $aluno[
        "progresso_percentual"
    ] =
        $totalAulas > 0

        ? (int) round(
            (
                (int)
                $aluno["aulas_concluidas"]
                / $totalAulas
            )
            * 100
        )

        : 0;
}

unset($aluno);


require __DIR__ . "/alunos.html";