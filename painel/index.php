<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';

$user = require_login();
$pdo = db();

$novos = (int) $pdo->query("SELECT COUNT(*) FROM revendedores WHERE situacao = 'novo'")->fetchColumn();
$total = (int) $pdo->query('SELECT COUNT(*) FROM revendedores')->fetchColumn();
$ativos = (int) $pdo->query("SELECT COUNT(*) FROM revendedores WHERE situacao = 'ativo'")->fetchColumn();
$ultimos = $pdo->query('SELECT id, empresa, nome, cidade, uf, situacao, criado_em FROM revendedores ORDER BY criado_em DESC, id DESC LIMIT 5')->fetchAll();

$primeiro = explode(' ', trim($user['nome']))[0];
painel_header('Início', 'inicio', $user);
?>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span><?= e(data_br(now(), false)) ?><span aria-hidden="true"></span></p>
      <h1 class="title"><em>Olá,</em> <?= e($primeiro) ?></h1>
    </header>

    <section class="stats" aria-label="Resumo">
      <a class="stat<?= $novos ? ' stat--alert' : '' ?>" href="revendedores.php?situacao=novo">
        <span class="stat__num"><?= $novos ?></span>
        <span class="stat__label">Fichas novas de revendedor</span>
      </a>
      <a class="stat" href="revendedores.php?situacao=ativo">
        <span class="stat__num"><?= $ativos ?></span>
        <span class="stat__label">Revendedores ativos</span>
      </a>
      <a class="stat" href="revendedores.php">
        <span class="stat__num"><?= $total ?></span>
        <span class="stat__label">Fichas recebidas no total</span>
      </a>
    </section>

    <section class="panel">
      <header class="panel__head">
        <h2 class="panel__title">Últimas fichas de revendedor</h2>
        <a class="link" href="revendedores.php">Ver todas</a>
      </header>
      <?php if (!$ultimos): ?>
        <p class="empty">Nenhuma ficha recebida ainda. Elas chegam pela página Seja um Revendedor do site.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($ultimos as $r): ?>
            <li>
              <a class="list__item" href="revendedor.php?id=<?= (int) $r['id'] ?>">
                <span class="list__main"><strong><?= e($r['empresa']) ?></strong><span><?= e($r['nome']) ?> · <?= e($r['cidade']) ?>/<?= e($r['uf']) ?></span></span>
                <span class="list__meta"><?= badge_situacao($r['situacao']) ?><time><?= e(data_br($r['criado_em'])) ?></time></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
<?php painel_footer();
