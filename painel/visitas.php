<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';

$user = require_login();
$pdo = db();
$cfg = agenda_config();
$hoje = date('Y-m-d');

$mes = (string) ($_GET['mes'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mes) || !data_valida($mes . '-01')) {
    $mes = date('Y-m');
}
$ini = $mes . '-01';
$fim = date('Y-m-t', strtotime($ini));
$dia = (string) ($_GET['dia'] ?? '');
if (!data_valida($dia)) {
    $dia = ($hoje >= $ini && $hoje <= $fim) ? $hoje : $ini;
}
$mesAnt = date('Y-m', strtotime($ini . ' -1 month'));
$mesProx = date('Y-m', strtotime($ini . ' +1 month'));

/* Agendamentos e bloqueios do mês */
$st = $pdo->prepare("SELECT * FROM agendamentos WHERE data BETWEEN ? AND ? AND status <> 'cancelada' ORDER BY data, horario, criado_em");
$st->execute([$ini, $fim]);
$porDia = [];
foreach ($st->fetchAll() as $a) {
    $porDia[$a['data']][] = $a;
}
$st = $pdo->prepare('SELECT * FROM bloqueios WHERE data BETWEEN ? AND ?');
$st->execute([$ini, $fim]);
$bloqDia = [];
foreach ($st->fetchAll() as $b) {
    $bloqDia[$b['data']][] = $b;
}

/* Dia selecionado (inclui canceladas, para histórico) */
$st = $pdo->prepare("SELECT * FROM agendamentos WHERE data = ? ORDER BY CASE WHEN horario = 'manha' THEN 0 WHEN horario = 'tarde' THEN 1 ELSE 2 END, status = 'cancelada', criado_em");
$st->execute([$dia]);
$doDia = $st->fetchAll();

/* Aguardando confirmação (de hoje em diante) */
$pendentes = $pdo->prepare("SELECT * FROM agendamentos WHERE status = 'pendente' AND data >= ? ORDER BY data, horario, criado_em LIMIT 50");
$pendentes->execute([$hoje]);
$pendentes = $pendentes->fetchAll();

$voltar = 'visitas.php?' . http_build_query(['mes' => $mes, 'dia' => $dia]);

function nome_curto(string $n): string
{
    $p = preg_split('/\s+/', trim($n));
    return $p[0] . (count($p) > 1 ? ' ' . mb_substr(end($p), 0, 1) . '.' : '');
}

function rotulo_experiencia(array $a): string
{
    return $a['tipo'] === 'visita' ? (EXPERIENCIAS[$a['experiencia']] ?? $a['experiencia']) : (TIPOS_EVENTO[$a['experiencia']] ?? $a['experiencia']);
}

function botao_confirmar(array $a, string $voltar): string
{
    return '<form method="post" action="visita.php" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $a['id'] . '" />'
        . '<input type="hidden" name="acao" value="confirmar" />'
        . '<input type="hidden" name="voltar" value="' . e($voltar) . '" />'
        . '<button class="btn btn--sm btn--gold" type="submit">Confirmar</button></form>';
}

painel_header('Visitas', 'visitas', $user);
?>
    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span>Agenda da cantina<span aria-hidden="true"></span></p>
        <h1 class="title"><em>Visitas</em> e eventos</h1>
      </div>
      <div class="page-top__actions">
        <a class="btn" href="visita-nova.php?data=<?= e($dia) ?>">+ Adicionar visita</a>
        <a class="btn" href="lista-do-dia.php?data=<?= e($hoje) ?>" target="_blank" rel="noopener">Lista de hoje</a>
      </div>
    </header>

    <?php if ($pendentes): ?>
      <section class="panel panel--alert">
        <header class="panel__head">
          <h2 class="panel__title">Aguardando confirmação <span class="pill"><?= count($pendentes) ?></span></h2>
        </header>
        <ul class="list">
          <?php foreach ($pendentes as $a): ?>
            <li class="list__row">
              <a class="list__main" href="visita.php?id=<?= (int) $a['id'] ?>">
                <strong><?= e($a['nome']) ?> <span class="muted">· <?= (int) $a['pessoas'] ?> <?= $a['tipo'] === 'visita' ? 'pessoas' : 'convidados' ?></span></strong>
                <span><?= e(DIAS_SEMANA[(int) date('w', strtotime($a['data']))]) ?>, <?= e(date('d/m', strtotime($a['data']))) ?><?= $a['horario'] ? ' · ' . e(TURNOS_CURTO[$a['horario']]) : '' ?> · <?= e(rotulo_experiencia($a)) ?><?= $a['tipo'] === 'evento' ? ' <span class="tag">Evento</span>' : '' ?></span>
              </a>
              <span class="list__actions">
                <a class="icon-btn" href="<?= e(whatsapp_link($a['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="WhatsApp de <?= e($a['nome']) ?>" title="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3zM12 21.8h0a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4a9.8 9.8 0 0 1-1.5-5.3C2.2 6.5 6.6 2.2 12 2.2c2.6 0 5.1 1 7 2.9a9.8 9.8 0 0 1 2.9 7c0 5.4-4.4 9.7-9.9 9.7z"/></svg></a>
                <?= botao_confirmar($a, $voltar) ?>
                <a class="btn btn--sm" href="visita.php?id=<?= (int) $a['id'] ?>">Abrir</a>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <div class="agenda">
      <section class="panel cal" aria-label="Calendário de <?= e(MESES[(int) substr($mes, 5)]) ?>">
        <header class="cal__head">
          <a class="icon-btn" href="?mes=<?= e($mesAnt) ?>" aria-label="Mês anterior"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></a>
          <h2 class="cal__title"><em><?= e(MESES[(int) substr($mes, 5)]) ?></em> <?= e(substr($mes, 0, 4)) ?></h2>
          <a class="icon-btn" href="?mes=<?= e($mesProx) ?>" aria-label="Próximo mês"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></a>
          <?php if ($mes !== date('Y-m')): ?><a class="link cal__today" href="visitas.php">Hoje</a><?php endif; ?>
        </header>
        <div class="cal__grid" role="grid">
          <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $w): ?><div class="cal__dow" role="columnheader"><?= $w ?></div><?php endforeach; ?>
          <?php
          $offset = (int) date('w', strtotime($ini));
          for ($i = 0; $i < $offset; $i++) {
              echo '<div class="cal__cell cal__cell--empty" aria-hidden="true"></div>';
          }
          $nDias = (int) date('t', strtotime($ini));
          for ($n = 1; $n <= $nDias; $n++):
              $d = sprintf('%s-%02d', $mes, $n);
              $itens = $porDia[$d] ?? [];
              $bl = $bloqDia[$d] ?? [];
              $dow = (int) date('w', strtotime($d));
              $fechado = empty($cfg['dias'][$dow] ?? $cfg['dias'][(string) $dow] ?? []);
              $diaBloq = (bool) array_filter($bl, fn($b) => $b['turno'] === 'dia');
              $temPend = (bool) array_filter($itens, fn($a) => $a['status'] === 'pendente');
              $pessoas = array_sum(array_map(fn($a) => $a['tipo'] === 'visita' ? (int) $a['pessoas'] : 0, $itens));
              $cls = ['cal__cell'];
              if ($d === $hoje) $cls[] = 'is-today';
              if ($d === $dia) $cls[] = 'is-selected';
              if ($d < $hoje) $cls[] = 'is-past';
              if ($diaBloq || $fechado) $cls[] = 'is-closed';
          ?>
            <a class="<?= implode(' ', $cls) ?>" href="?<?= e(http_build_query(['mes' => $mes, 'dia' => $d])) ?>" aria-label="<?= e(data_extenso($d)) ?>: <?= count($itens) ?> agendamentos<?= $diaBloq ? ', bloqueado' : '' ?>"<?= $d === $dia ? ' aria-current="date"' : '' ?>>
              <span class="cal__num"><?= $n ?></span>
              <?php if ($bl): ?><span class="cal__lock" title="<?= e(implode(', ', array_filter(array_column($bl, 'motivo'))) ?: 'Bloqueado') ?>">Bloq.<?= $diaBloq ? '' : ' ' . e(implode('/', array_map(fn($b) => TURNOS_CURTO[$b['turno']] ?? '', $bl))) ?></span><?php endif; ?>
              <?php if ($itens): ?>
                <span class="cal__items">
                  <?php foreach (array_slice($itens, 0, 3) as $a): ?>
                    <span class="cal__item cal__item--<?= e($a['status']) ?><?= $a['tipo'] === 'evento' ? ' cal__item--evento' : '' ?>"><?= e(nome_curto($a['nome'])) ?> <b><?= (int) $a['pessoas'] ?></b></span>
                  <?php endforeach; ?>
                  <?php if (count($itens) > 3): ?><span class="cal__more">+<?= count($itens) - 3 ?></span><?php endif; ?>
                </span>
                <span class="cal__dots" aria-hidden="true"><i class="<?= $temPend ? 'is-pend' : '' ?>"></i><?php if ($pessoas): ?><?= $pessoas ?>p<?php endif; ?></span>
              <?php endif; ?>
            </a>
          <?php endfor; ?>
        </div>
        <p class="cal__legend"><span class="cal__key cal__key--pendente"></span>Aguardando <span class="cal__key cal__key--confirmada"></span>Confirmada <span class="cal__key cal__key--evento"></span>Evento</p>
      </section>

      <section class="panel day" aria-labelledby="dia-titulo">
        <header class="panel__head">
          <div>
            <p class="eyebrow"><span aria-hidden="true"></span><?= e(DIAS_SEMANA[(int) date('w', strtotime($dia))]) ?><span aria-hidden="true"></span></p>
            <h2 class="panel__title" id="dia-titulo"><?= e(data_extenso($dia, false)) ?></h2>
          </div>
          <a class="btn btn--sm" href="lista-do-dia.php?data=<?= e($dia) ?>" target="_blank" rel="noopener">Imprimir lista</a>
        </header>
        <?php
        $disp = disponibilidade($dia);
        foreach (bloqueios_do_dia($dia) as $b): ?>
          <p class="note note--lock">Bloqueado<?= $b['turno'] !== 'dia' ? ' (' . e(TURNOS_CURTO[$b['turno']] ?? $b['turno']) . ')' : '' ?><?= $b['motivo'] ? ': ' . e($b['motivo']) : '' ?></p>
        <?php endforeach; ?>
        <div class="day__turnos">
          <?php foreach (TURNOS as $t => $rot):
              $ocup = ocupacao($dia, $t);
              $lim = (int) ($cfg['limite'][$t] ?? 0);
              $pct = $lim ? min(100, round($ocup / $lim * 100)) : 0; ?>
            <div class="meter">
              <span class="meter__label"><?= e(TURNOS_CURTO[$t]) ?></span>
              <span class="meter__bar"><i style="width: <?= $pct ?>%"></i></span>
              <span class="meter__num"><?= $ocup ?>/<?= $lim ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (!$doDia): ?>
          <p class="empty">Nenhum agendamento neste dia.</p>
        <?php else: ?>
          <ul class="list">
            <?php foreach ($doDia as $a): ?>
              <li class="list__row<?= $a['status'] === 'cancelada' ? ' is-cancelled' : '' ?>">
                <a class="list__main" href="visita.php?id=<?= (int) $a['id'] ?>">
                  <strong><?= e($a['nome']) ?> <span class="muted">· <?= (int) $a['pessoas'] ?> <?= $a['tipo'] === 'visita' ? 'pessoas' : 'convidados' ?></span></strong>
                  <span><?= $a['horario'] ? e(TURNOS_CURTO[$a['horario']]) . ' · ' : '' ?><?= e(rotulo_experiencia($a)) ?><?= $a['tipo'] === 'evento' ? ' <span class="tag">Evento</span>' : '' ?></span>
                </a>
                <span class="list__actions">
                  <?= badge_status($a['status']) ?>
                  <?php if ($a['status'] === 'pendente'): ?><?= botao_confirmar($a, $voltar) ?><?php endif; ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>
<?php painel_footer();
