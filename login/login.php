<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (usuarioLogado()) {
    exigirLogin();

    $destino = $_SESSION['tipo'] === 'admin'
        ? '../admin/dashboard.php'
        : '../aluno/dashboard.php';

    header('Location: ' . $destino);
    exit;
}

$erro = '';
$csrfToken = gerarTokenCsrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
        $erro = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if ($email === '' || $senha === '') {
            $erro = 'Preencha o e-mail e a senha.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'E-mail ou senha incorretos.';
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, nome, email, senha, tipo, ativo
                 FROM usuarios
                 WHERE email = :email
                   AND ativo = 1
                 LIMIT 1"
            );
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            $credenciaisValidas =
                $usuario
                && in_array($usuario['tipo'], ['admin', 'aluno'], true)
                && password_verify($senha, $usuario['senha']);

            if ($credenciaisValidas) {
                if (password_needs_rehash($usuario['senha'], PASSWORD_DEFAULT)) {
                    $novoHash = password_hash($senha, PASSWORD_DEFAULT);
                    $update = $pdo->prepare(
                        'UPDATE usuarios SET senha = :senha WHERE id = :id'
                    );
                    $update->execute([
                        ':senha' => $novoHash,
                        ':id' => (int) $usuario['id'],
                    ]);
                }

                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);

                $_SESSION['usuario_id'] = (int) $usuario['id'];
                $_SESSION['nome'] = $usuario['nome'];
                $_SESSION['tipo'] = $usuario['tipo'];

                $destino = $usuario['tipo'] === 'admin'
                    ? '../admin/dashboard.php'
                    : '../aluno/dashboard.php';

                header('Location: ' . $destino);
                exit;
            }

            $erro = 'E-mail ou senha incorretos.';
        }
    }
}

require __DIR__ . '/login.html';
