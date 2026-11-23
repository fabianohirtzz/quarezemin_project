<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const SESSAO_INATIVIDADE = 4 * 3600; // 4 horas sem uso derruba a sessão

function painel_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('QZPAINEL');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (bool) config('secure_cookies'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');

    if (isset($_SESSION['uid'], $_SESSION['visto']) && time() - $_SESSION['visto'] > SESSAO_INATIVIDADE) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['visto'] = time();
}

function current_user(): ?array
{
    painel_session();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $st = db()->prepare('SELECT id, nome, email FROM usuarios WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $user = $st->fetch() ?: null;
    }
    return $user;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function attempt_login(string $email, string $senha): bool
{
    $st = db()->prepare('SELECT id, senha_hash FROM usuarios WHERE email = ?');
    $st->execute([mb_strtolower(trim($email))]);
    $row = $st->fetch();
    // Compara mesmo sem usuário, para não revelar quais e-mails existem pelo tempo de resposta.
    $hash = $row['senha_hash'] ?? password_hash(random_bytes(8), PASSWORD_DEFAULT);
    if (!password_verify($senha, $hash) || !$row) {
        return false;
    }
    painel_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $row['id'];
    $_SESSION['visto'] = time();
    if (password_needs_rehash($row['senha_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
            ->execute([password_hash($senha, PASSWORD_DEFAULT), $row['id']]);
    }
    db()->prepare('UPDATE usuarios SET ultimo_acesso = ? WHERE id = ?')->execute([now(), $row['id']]);
    return true;
}

function logout(): void
{
    painel_session();
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
}

function csrf_token(): string
{
    painel_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '" />';
}

function csrf_check(): void
{
    painel_session();
    $ok = isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
    if (!$ok) {
        http_response_code(400);
        exit('Sessão expirada. Volte e tente de novo.');
    }
}

function flash(?string $msg = null): ?string
{
    painel_session();
    if ($msg !== null) {
        $_SESSION['flash'] = $msg;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

const SITUACOES_REVENDEDOR = [
    'novo' => 'Novo',
    'em_contato' => 'Em contato',
    'ativo' => 'Revendedor ativo',
    'descartado' => 'Descartado',
];
