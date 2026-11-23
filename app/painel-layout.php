<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/agenda.php';

const PAINEL_MENU = [
    'inicio' => ['Início', 'index.php', '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>'],
    'visitas' => ['Visitas', 'visitas.php', '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>'],
    'agenda' => ['Datas e lotação', 'agenda.php', '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m14 14-4 4"/><path d="m10 14 4 4"/>'],
    'revendedores' => ['Revendedores', 'revendedores.php', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
];

function painel_header(string $titulo, string $ativo, array $user): void
{
    $flash = flash();
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= e($titulo) ?> · Painel Quarezemin</title>
  <meta name="theme-color" content="#0a0807" />
  <link rel="icon" type="image/png" href="../imagens/logo-quarezemin.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/painel.css" />
</head>
<body class="painel">
  <a class="painel__skip" href="#conteudo">Pular para o conteúdo</a>
  <aside class="painel-side">
    <a class="painel-side__brand" href="index.php" aria-label="Painel Quarezemin, início">
      <img src="../imagens/logo-quarezemin.png" alt="Vinhos Quarezemin" />
      <span>Painel</span>
    </a>
    <nav class="painel-side__nav" aria-label="Menu do painel">
      <?php foreach (PAINEL_MENU as $chave => [$rotulo, $href, $icone]): ?>
        <a class="painel-side__link<?= $chave === $ativo ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $chave === $ativo ? ' aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icone ?></svg>
          <span><?= e($rotulo) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="painel-side__user">
      <span class="painel-side__user-name"><?= e($user['nome']) ?></span>
      <a class="painel-side__out" href="../index.html" target="_blank" rel="noopener">Ver site</a>
      <form method="post" action="sair.php"><?= csrf_field() ?><button type="submit" class="painel-side__out">Sair</button></form>
    </div>
  </aside>
  <main class="painel-main" id="conteudo">
    <?php if ($flash): ?><p class="painel-flash" role="status"><?= e($flash) ?></p><?php endif; ?>
<?php
}

function painel_footer(): void
{
    ?>
  </main>
</body>
</html>
<?php
}

function badge_situacao(string $s): string
{
    $rotulo = SITUACOES_REVENDEDOR[$s] ?? $s;
    return '<span class="badge badge--' . e($s) . '">' . e($rotulo) . '</span>';
}

function data_br(?string $dt, bool $hora = true): string
{
    if (!$dt) {
        return '-';
    }
    $t = strtotime($dt);
    return $t ? date($hora ? 'd/m/Y H:i' : 'd/m/Y', $t) : '-';
}

function badge_status(string $s): string
{
    $rotulo = STATUS_AGENDAMENTO[$s] ?? $s;
    return '<span class="badge badge--' . e($s) . '">' . e($rotulo) . '</span>';
}

/** Aceita só destinos internos do painel (evita redirecionamento aberto). */
function voltar_para(?string $url, string $padrao): string
{
    $url = (string) $url;
    return preg_match('/^[a-z-]+\.php(\?[A-Za-z0-9=&%_.-]*)?$/', $url) ? $url : $padrao;
}
