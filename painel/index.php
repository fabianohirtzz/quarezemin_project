<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';

$user = require_login();
$pdo = db();

$novos = (int) $pdo->query("SELECT COUNT(*) FROM revendedores WHERE situacao = 'novo'")->fetchColumn();
$total = (int) $pdo->query('SELECT COUNT(*) FROM revendedores')->fetchColumn();
$ativos = (int) $pdo->query("SELECT COUNT(*) FROM revendedores WHERE situacao = 'ativo'")->fetchColumn();
$rotulosNovos = (int) $pdo->query("SELECT COUNT(*) FROM rotulos WHERE situacao = 'novo'")->fetchColumn();
$hoje = date('Y-m-d');
$st = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente' AND data >= ?");
$st->execute([$hoje]);
$aguardando = (int) $st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(pessoas), 0) AS p FROM agendamentos WHERE status <> 'cancelada' AND data = ?");
$st->execute([$hoje]);
$hojeInfo = $st->fetch();
$st = $pdo->prepare("SELECT * FROM agendamentos WHERE status <> 'cancelada' AND data >= ? ORDER BY data, CASE WHEN horario = 'manha' THEN 0 WHEN horario = 'tarde' THEN 1 ELSE 2 END LIMIT 6");
$st->execute([$hoje]);
$proximas = $st->fetchAll();
$ultimos = $pdo->query('SELECT id, empresa, nome, cidade, uf, situacao, criado_em FROM revendedores ORDER BY criado_em DESC, id DESC LIMIT 5')->fetchAll();

$primeiro = explode(' ', trim($user['nome']))[0];
painel_header('Início', 'inicio', $user);
?>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span><?= e(data_br(now(), false)) ?><span aria-hidden="true"></span></p>
      <h1 class="title"><em>Olá,</em> <?= e($primeiro) ?></h1>
    </header>

    <?php if ($rotulosNovos): ?>
      <a class="notice notice--link" href="rotulos.php?situacao=novo">
        <?= $rotulosNovos ?> <?= $rotulosNovos === 1 ? 'pedido novo de rótulo personalizado esperando' : 'pedidos novos de rótulo personalizado esperando' ?> resposta. Ver pedidos
      </a>
    <?php endif; ?>

    <section class="stats stats--4" aria-label="Resumo">
      <a class="stat<?= $aguardando ? ' stat--alert' : '' ?>" href="visitas.php">
        <span class="stat__num"><?= $aguardando ?></span>
        <span class="stat__label">Visitas aguardando confirmação</span>
      </a>
      <a class="stat" href="lista-do-dia.php?data=<?= e($hoje) ?>" target="_blank" rel="noopener">
        <span class="stat__num"><?= (int) $hojeInfo['p'] ?></span>
        <span class="stat__label">Pessoas esperadas hoje (<?= (int) $hojeInfo['n'] ?> <?= (int) $hojeInfo['n'] === 1 ? 'agendamento' : 'agendamentos' ?>)</span>
      </a>
      <a class="stat<?= $novos ? ' stat--alert' : '' ?>" href="revendedores.php?situacao=novo">
        <span class="stat__num"><?= $novos ?></span>
        <span class="stat__label">Fichas novas de revendedor</span>
      </a>
      <a class="stat" href="revendedores.php?situacao=ativo">
        <span class="stat__num"><?= $ativos ?></span>
        <span class="stat__label">Revendedores ativos (<?= $total ?> fichas)</span>
      </a>
    </section>

    <div class="detail">
    <section class="panel">
      <header class="panel__head">
        <h2 class="panel__title">Próximas visitas</h2>
        <a class="link" href="visitas.php">Abrir agenda</a>
      </header>
      <?php if (!$proximas): ?>
        <p class="empty">Nenhuma visita agendada daqui para frente.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($proximas as $a): ?>
            <li>
              <a class="list__item" href="visita.php?id=<?= (int) $a['id'] ?>">
                <span class="list__main"><strong><?= e($a['nome']) ?></strong><span><?= e(DIAS_SEMANA[(int) date('w', strtotime($a['data']))]) ?>, <?= e(date('d/m', strtotime($a['data']))) ?><?= $a['horario'] ? ' · ' . e(TURNOS_CURTO[$a['horario']]) : ' · Evento' ?> · <?= (int) $a['pessoas'] ?> pessoas</span></span>
                <span class="list__meta"><?= badge_status($a['status']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
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
    </div>
<?php painel_footer();
