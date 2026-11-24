<?php
declare(strict_types=1);

require __DIR__ . '/../app/auth.php';

painel_session();
if (current_user()) {
    header('Location: index.php');
    exit;
}

$erro = '';
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $email = clean_text($_POST['email'] ?? '', 160);
    $senha = (string) ($_POST['senha'] ?? '');
    if (!rate_limit('login', 8, 15, false)) {
        $erro = 'Muitas tentativas. Aguarde 15 minutos e tente de novo.';
    } elseif (attempt_login($email, $senha)) {
        clear_attempts('login');
        header('Location: index.php');
        exit;
    } else {
        rate_limit('login', 8, 15);
        $erro = 'E-mail ou senha incorretos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Entrar · Painel Quarezemin</title>
  <meta name="theme-color" content="#0a0807" />
  <link rel="icon" type="image/png" href="../imagens/logo-quarezemin.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/painel.css" />
</head>
<body class="painel painel--login">
  <div class="login" aria-hidden="true">
    <img class="login__bg" src="../imagens/barris-cantina.webp" alt="" />
  </div>
  <main class="login__card">
    <img class="login__logo" src="../imagens/logo-quarezemin.png" alt="Vinhos Quarezemin" />
    <p class="eyebrow"><span aria-hidden="true"></span>Área restrita<span aria-hidden="true"></span></p>
    <h1 class="title"><em>Painel</em> da cantina</h1>
    <?php if ($erro): ?><p class="form-error" role="alert"><?= e($erro) ?></p><?php endif; ?>
    <form method="post" class="form" autocomplete="on">
      <?= csrf_field() ?>
      <label class="field">
        <span class="field__label">E-mail</span>
        <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus />
      </label>
      <label class="field">
        <span class="field__label">Senha</span>
        <input type="password" name="senha" autocomplete="current-password" required />
      </label>
      <button class="btn btn--gold btn--block" type="submit">Entrar</button>
    </form>
  </main>
</body>
</html>
