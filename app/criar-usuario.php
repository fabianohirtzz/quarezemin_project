<?php
declare(strict_types=1);

/*
 * Cria ou redefine a senha de um usuário do painel. Só roda pela linha de comando:
 *   php app/criar-usuario.php "Nome" email@dominio.com
 * A senha é pedida no terminal (ou passada como 3º argumento em testes).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

[$_, $nome, $email] = array_pad($argv, 3, null);
$senha = $argv[3] ?? null;
if (!$nome || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php app/criar-usuario.php \"Nome\" email@dominio.com [senha]\n");
    exit(1);
}
if ($senha === null) {
    fwrite(STDOUT, 'Senha (mínimo 10 caracteres): ');
    $senha = trim((string) fgets(STDIN));
}
if (mb_strlen($senha) < 10) {
    fwrite(STDERR, "A senha precisa ter pelo menos 10 caracteres.\n");
    exit(1);
}

$email = mb_strtolower($email);
$hash = password_hash($senha, PASSWORD_DEFAULT);
$st = db()->prepare('SELECT id FROM usuarios WHERE email = ?');
$st->execute([$email]);
if ($id = $st->fetchColumn()) {
    db()->prepare('UPDATE usuarios SET nome = ?, senha_hash = ? WHERE id = ?')->execute([$nome, $hash, $id]);
    echo "Usuário atualizado: $email\n";
} else {
    db()->prepare('INSERT INTO usuarios (nome, email, senha_hash, criado_em) VALUES (?, ?, ?, ?)')
        ->execute([$nome, $email, $hash, now()]);
    echo "Usuário criado: $email\n";
}
