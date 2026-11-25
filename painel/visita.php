<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';

$user = require_login();
$pdo = db();
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);

$carregar = function () use ($pdo, $id) {
    $st = $pdo->prepare('SELECT * FROM agendamentos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch();
};
$a = $carregar();
if (!$a) {
    flash('Agendamento não encontrado.');
    header('Location: visitas.php');
    exit;
}
$voltarPadrao = 'visitas.php?' . http_build_query(['mes' => substr($a['data'], 0, 7), 'dia' => $a['data']]);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';
    $voltar = voltar_para($_POST['voltar'] ?? null, 'visita.php?id=' . $id);
    $agora = now();

    if ($acao === 'confirmar' && $a['status'] !== 'confirmada') {
        $pdo->prepare("UPDATE agendamentos SET status = 'confirmada', confirmado_em = ?, atualizado_em = ? WHERE id = ?")
            ->execute([$agora, $agora, $id]);
        [$assunto, $texto] = email_confirmacao($a);
        $ok = send_mail([$a['email']], $assunto, $texto);
        flash($ok
            ? 'Confirmada. O e-mail de confirmação foi enviado para ' . $a['email'] . '.'
            : 'Confirmada, mas o e-mail não saiu. Avise ' . explode(' ', $a['nome'])[0] . ' pelo WhatsApp.');
        header('Location: ' . $voltar);
        exit;
    }

    if ($acao === 'cancelar' && $a['status'] !== 'cancelada') {
        $pdo->prepare("UPDATE agendamentos SET status = 'cancelada', atualizado_em = ? WHERE id = ?")->execute([$agora, $id]);
        $msg = 'Agendamento cancelado. A vaga foi liberada.';
        if (!empty($_POST['avisar'])) {
            [$assunto, $texto] = email_cancelamento($a);
            $msg .= send_mail([$a['email']], $assunto, $texto) ? ' O visitante foi avisado por e-mail.' : ' O e-mail de aviso não saiu.';
        }
        flash($msg);
        header('Location: ' . $voltar);
        exit;
    }

    if ($acao === 'reabrir' && $a['status'] === 'cancelada') {
        agenda_lock($pdo);
        try {
            $ok = true;
            if ($a['tipo'] === 'visita') {
                $lim = (int) (agenda_config()['limite'][$a['horario']] ?? 0);
                $ok = ocupacao($a['data'], (string) $a['horario']) + (int) $a['pessoas'] <= $lim;
            }
            if ($ok) {
                $pdo->prepare("UPDATE agendamentos SET status = 'pendente', atualizado_em = ? WHERE id = ?")->execute([$agora, $id]);
            }
        } finally {
            agenda_unlock($pdo);
        }
        flash($ok ? 'Agendamento reaberto como aguardando.' : 'Não há mais vagas neste horário para reabrir. Ajuste a lotação ou remarque.');
        header('Location: ' . $voltar);
        exit;
    }

    if ($acao === 'notas') {
        $notas = clean_text(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['notas'] ?? '')), 5000);
        $pdo->prepare('UPDATE agendamentos SET notas = ?, atualizado_em = ? WHERE id = ?')->execute([$notas !== '' ? $notas : null, $agora, $id]);
        flash('Notas salvas.');
        header('Location: visita.php?id=' . $id);
        exit;
    }

    if ($acao === 'remarcar') {
        $nData = (string) ($_POST['data'] ?? '');
        $nTurno = $a['tipo'] === 'visita' ? (string) ($_POST['horario'] ?? '') : null;
        $nPessoas = max(1, (int) ($_POST['pessoas'] ?? $a['pessoas']));
        if (!data_valida($nData) || ($a['tipo'] === 'visita' && !isset(TURNOS[$nTurno]))) {
            flash('Confira a nova data e o horário.');
            header('Location: visita.php?id=' . $id);
            exit;
        }
        agenda_lock($pdo);
        try {
            $ok = true;
            if ($a['tipo'] === 'visita' && $a['status'] !== 'cancelada') {
                $lim = (int) (agenda_config()['limite'][$nTurno] ?? 0);
                $ok = ocupacao($nData, $nTurno, $id) + $nPessoas <= $lim || !empty($_POST['forcar']);
            }
            if ($ok) {
                $pdo->prepare('UPDATE agendamentos SET data = ?, horario = ?, pessoas = ?, atualizado_em = ? WHERE id = ?')
                    ->execute([$nData, $nTurno, $nPessoas, $agora, $id]);
            }
        } finally {
            agenda_unlock($pdo);
        }
        if (!$ok) {
            flash('Esse horário ficaria acima da lotação. Marque "registrar mesmo assim" se quiser seguir.');
        } else {
            $msg = 'Agendamento remarcado para ' . date('d/m/Y', strtotime($nData)) . ($nTurno ? ' (' . TURNOS_CURTO[$nTurno] . ')' : '') . '.';
            if ($a['status'] === 'confirmada' && !empty($_POST['avisar'])) {
                [$assunto, $texto] = email_confirmacao(array_merge($a, ['data' => $nData, 'horario' => $nTurno, 'pessoas' => $nPessoas]));
                $msg .= send_mail([$a['email']], $assunto, $texto) ? ' Nova confirmação enviada por e-mail.' : ' O e-mail não saiu.';
            }
            flash($msg);
        }
        header('Location: visita.php?id=' . $id);
        exit;
    }

    header('Location: visita.php?id=' . $id);
    exit;
}

$visita = $a['tipo'] === 'visita';
$primeiro = explode(' ', trim($a['nome']))[0];
$quando = date('d/m', strtotime($a['data'])) . ($a['horario'] ? ' (' . mb_strtolower(TURNOS_CURTO[$a['horario']]) . ')' : '');
$msgWhats = $a['status'] === 'confirmada'
    ? "Olá, {$primeiro}! Aqui é da Vinícola Quarezemin. Sua " . ($visita ? 'visita' : 'data') . " do dia {$quando} está confirmada. Até breve!"
    : "Olá, {$primeiro}! Aqui é da Vinícola Quarezemin. Recebemos o seu pedido para o dia {$quando}.";
$rotulo = $visita ? (EXPERIENCIAS[$a['experiencia']] ?? $a['experiencia']) : (TIPOS_EVENTO[$a['experiencia']] ?? $a['experiencia']);

painel_header($a['nome'], 'visitas', $user);
?>
    <a class="back" href="<?= e($voltarPadrao) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Agenda
    </a>

    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span><?= $visita ? 'Visita' : 'Pedido de evento' ?> · recebido em <?= e(data_br($a['criado_em'])) ?><span aria-hidden="true"></span></p>
        <h1 class="title"><?= e($a['nome']) ?></h1>
        <p class="sub"><?= e(ucfirst(data_extenso($a['data']))) ?><?= $a['horario'] ? ' · ' . e(TURNOS[$a['horario']]) : '' ?></p>
      </div>
      <div class="page-top__actions">
        <?= badge_status($a['status']) ?>
      </div>
    </header>

    <div class="status-bar">
      <?php if ($a['status'] === 'pendente'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>" /><input type="hidden" name="acao" value="confirmar" />
          <button class="btn btn--gold" type="submit">Confirmar e enviar e-mail</button></form>
      <?php endif; ?>
      <a class="btn" href="<?= e(whatsapp_link($a['whatsapp'], $msgWhats)) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a>
      <?php if ($a['status'] === 'cancelada'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>" /><input type="hidden" name="acao" value="reabrir" />
          <button class="btn" type="submit">Reabrir agendamento</button></form>
      <?php else: ?>
        <details class="cancel">
          <summary class="btn btn--danger">Cancelar</summary>
          <form method="post" class="cancel__box"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>" /><input type="hidden" name="acao" value="cancelar" />
            <label class="check"><input type="checkbox" name="avisar" value="1" checked /> Avisar <?= e($primeiro) ?> por e-mail, sugerindo outra data</label>
            <button class="btn btn--danger" type="submit">Confirmar cancelamento</button>
          </form>
        </details>
      <?php endif; ?>
    </div>
    <?php if ($a['confirmado_em']): ?><p class="hint">Confirmada em <?= e(data_br($a['confirmado_em'])) ?>.</p><?php endif; ?>

    <div class="detail">
      <section class="panel">
        <h2 class="panel__title">Dados do pedido</h2>
        <dl class="dl">
          <dt>Nome</dt><dd><?= e($a['nome']) ?></dd>
          <dt>WhatsApp</dt><dd><a href="<?= e(whatsapp_link($a['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e(format_whatsapp($a['whatsapp'])) ?></a></dd>
          <dt>E-mail</dt><dd><a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></dd>
          <dt><?= $visita ? 'Experiência' : 'Evento' ?></dt><dd><?= e($rotulo) ?></dd>
          <dt><?= $visita ? 'Pessoas' : 'Convidados' ?></dt><dd><?= (int) $a['pessoas'] ?></dd>
          <dt>Data</dt><dd><?= e(ucfirst(data_extenso($a['data']))) ?></dd>
          <?php if ($a['horario']): ?><dt>Horário</dt><dd><?= e(TURNOS[$a['horario']]) ?></dd><?php endif; ?>
          <dt><?= $visita ? 'Observações' : 'Descrição' ?></dt><dd class="dl__text"><?= $a['observacoes'] ? nl2br(e($a['observacoes'])) : '-' ?></dd>
        </dl>
      </section>

      <div class="stack">
        <section class="panel">
          <h2 class="panel__title">Notas internas</h2>
          <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>" /><input type="hidden" name="acao" value="notas" />
            <label class="field"><span class="sr-only">Notas internas</span>
              <textarea name="notas" rows="4" maxlength="5000" placeholder="Ex.: grupo de aniversário, preparar mesa no salão."><?= e($a['notas'] ?? '') ?></textarea></label>
            <button class="btn" type="submit">Salvar notas</button>
          </form>
        </section>

        <details class="panel remarcar">
          <summary class="panel__title">Remarcar</summary>
          <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>" /><input type="hidden" name="acao" value="remarcar" />
            <div class="row">
              <label class="field"><span class="field__label">Nova data</span><input type="date" name="data" value="<?= e($a['data']) ?>" required /></label>
              <?php if ($visita): ?>
                <label class="field"><span class="field__label">Horário</span><select name="horario">
                  <?php foreach (TURNOS as $k => $rot): ?><option value="<?= e($k) ?>"<?= $a['horario'] === $k ? ' selected' : '' ?>><?= e($rot) ?></option><?php endforeach; ?>
                </select></label>
              <?php endif; ?>
              <label class="field field--sm"><span class="field__label"><?= $visita ? 'Pessoas' : 'Convidados' ?></span><input type="number" name="pessoas" min="1" value="<?= (int) $a['pessoas'] ?>" /></label>
            </div>
            <?php if ($visita): ?><label class="check"><input type="checkbox" name="forcar" value="1" /> Registrar mesmo se passar da lotação</label><?php endif; ?>
            <?php if ($a['status'] === 'confirmada'): ?><label class="check"><input type="checkbox" name="avisar" value="1" checked /> Enviar nova confirmação por e-mail</label><?php endif; ?>
            <button class="btn" type="submit">Salvar nova data</button>
          </form>
        </details>
      </div>
    </div>
<?php painel_footer();
