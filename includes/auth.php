<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function usuarioLogado(): bool
{
    return isset($_SESSION['usuario_id'], $_SESSION['tipo']);
}

function redirecionarLogin(): void
{
    header('Location: ../login/login.php');
    exit;
}

function exigirLogin(): void
{
    if (!usuarioLogado()) {
        redirecionarLogin();
    }

    global $pdo;

    if (isset($pdo) && $pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, nome, tipo, ativo
                 FROM usuarios
                 WHERE id = :id
                 LIMIT 1"
            );
            $stmt->execute([':id' => (int) $_SESSION['usuario_id']]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario || (int) $usuario['ativo'] !== 1) {
                sair();
                redirecionarLogin();
            }

            if (!in_array($usuario['tipo'], ['admin', 'aluno'], true)) {
                sair();
                redirecionarLogin();
            }

            $_SESSION['usuario_id'] = (int) $usuario['id'];
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['tipo'] = $usuario['tipo'];
        } catch (PDOException $e) {
            error_log('Falha ao validar sessão: ' . $e->getMessage());
            http_response_code(500);
            exit('Não foi possível validar sua sessão.');
        }
    }
}

function exigirAdmin(): void
{
    exigirLogin();

    if ($_SESSION['tipo'] !== 'admin') {
        header('Location: ../aluno/dashboard.php');
        exit;
    }
}

function exigirAluno(): void
{
    exigirLogin();

    if ($_SESSION['tipo'] !== 'aluno') {
        header('Location: ../admin/dashboard.php');
        exit;
    }
}

function gerarTokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validarTokenCsrf(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function sair(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

