<?php
declare(strict_types=1);

/* Registro manual de visita (pedidos que chegam por telefone, WhatsApp ou balcão). */

require __DIR__ . '/../app/painel-layout.php';

$user = require_login();
$pdo = db();
$erro = '';
$v = [
    'tipo' => 'visita', 'nome' => '', 'whatsapp' => '', 'email' => '', 'experiencia' => 'tour-degustacao',
    'pessoas' => '', 'data' => data_valida((string) ($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d'),
    'horario' => 'manha', 'observacoes' => '', 'status' => 'confirmada',
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $v = [
        'tipo' => ($_POST['tipo'] ?? '') === 'evento' ? 'evento' : 'visita',
        'nome' => clean_text($_POST['nome'] ?? '', 120),
        'whatsapp' => only_digits((string) ($_POST['whatsapp'] ?? '')),
        'email' => mb_strtolower(clean_text($_POST['email'] ?? '', 160)),
        'experiencia' => (string) ($_POST['experiencia'] ?? ''),
        'pessoas' => (int) ($_POST['pessoas'] ?? 0),
        'data' => (string) ($_POST['data'] ?? ''),
        'horario' => (string) ($_POST['horario'] ?? ''),
        'observacoes' => clean_text(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['observacoes'] ?? '')), 1500),
        'status' => ($_POST['status'] ?? '') === 'pendente' ? 'pendente' : 'confirmada',
    ];
    $visita = $v['tipo'] === 'visita';
    $catalogo = $visita ? EXPERIENCIAS : TIPOS_EVENTO;

    if ($v['nome'] === '' || strlen($v['whatsapp']) < 10) {
        $erro = 'Informe o nome e um WhatsApp com DDD.';
    } elseif ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $erro = 'O e-mail informado não é válido.';
    } elseif (!data_valida($v['data']) || $v['pessoas'] < 1) {
        $erro = 'Confira a data e o número de pessoas.';
    } elseif (!isset($catalogo[$v['experiencia']])) {
        $erro = $visita ? 'Escolha a experiência.' : 'Escolha o tipo de evento.';
    } elseif ($visita && !isset(TURNOS[$v['horario']])) {
        $erro = 'Escolha o horário.';
    }

    if (!$erro) {
        if (!$visita) {
            $v['horario'] = null;
        }
        agenda_lock($pdo);
        try {
            if ($visita && empty($_POST['forcar'])) {
                $lim = (int) (agenda_config()['limite'][$v['horario']] ?? 0);
                $ocup = ocupacao($v['data'], $v['horario']);
                if ($ocup + $v['pessoas'] > $lim) {
                    $erro = "Esse horário já tem {$ocup} de {$lim} lugares ocupados. Marque \"registrar mesmo assim\" para passar do limite.";
                }
            }
            if (!$erro) {
                $agora = now();
                $pdo->prepare(
                    'INSERT INTO agendamentos (tipo, nome, whatsapp, email, experiencia, pessoas, data, horario, observacoes, status, confirmado_em, criado_em, atualizado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([
                    $v['tipo'], $v['nome'], $v['whatsapp'], $v['email'], $v['experiencia'], $v['pessoas'], $v['data'], $v['horario'],
                    $v['observacoes'] ?: null, $v['status'], $v['status'] === 'confirmada' ? $agora : null, $agora, $agora,
                ]);
                $id = (int) $pdo->lastInsertId();
            }
        } finally {
            agenda_unlock($pdo);
        }
        if (!$erro) {
            $msg = 'Agendamento registrado.';
            if ($v['status'] === 'confirmada' && $v['email'] !== '' && !empty($_POST['avisar'])) {
                [$assunto, $texto] = email_confirmacao($v);
                $msg .= send_mail([$v['email']], $assunto, $texto) ? ' Confirmação enviada por e-mail.' : ' O e-mail não saiu.';
            }
            flash($msg);
            header('Location: visita.php?id=' . $id);
            exit;
        }
    }
}

painel_header('Adicionar visita', 'visitas', $user);
?>
    <a class="back" href="visitas.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Agenda
    </a>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span>Telefone, WhatsApp ou balcão<span aria-hidden="true"></span></p>
      <h1 class="title"><em>Adicionar</em> agendamento</h1>
    </header>

    <section class="panel panel--narrow">
      <?php if ($erro): ?><p class="form-error" role="alert"><?= e($erro) ?></p><?php endif; ?>
      <form method="post" class="form" id="nova">
        <?= csrf_field() ?>
        <fieldset class="choices">
          <legend class="field__label">Tipo</legend>
          <label class="choice"><input type="radio" name="tipo" value="visita"<?= $v['tipo'] === 'visita' ? ' checked' : '' ?> /><span class="choice__box">Visita</span></label>
          <label class="choice"><input type="radio" name="tipo" value="evento"<?= $v['tipo'] === 'evento' ? ' checked' : '' ?> /><span class="choice__box">Evento</span></label>
        </fieldset>
        <label class="field"><span class="field__label">Nome</span><input type="text" name="nome" value="<?= e($v['nome']) ?>" maxlength="120" required /></label>
        <div class="row">
          <label class="field"><span class="field__label">WhatsApp</span><input type="tel" name="whatsapp" value="<?= e($v['whatsapp']) ?>" placeholder="(48) 9 0000-0000" required /></label>
          <label class="field"><span class="field__label">E-mail (opcional)</span><input type="email" name="email" value="<?= e($v['email']) ?>" maxlength="160" /></label>
        </div>
        <label class="field" data-tipo="visita"><span class="field__label">Experiência</span><select name="experiencia" data-visita>
          <?php foreach (EXPERIENCIAS as $k => $rot): ?><option value="<?= e($k) ?>"<?= $v['experiencia'] === $k ? ' selected' : '' ?>><?= e($rot) ?></option><?php endforeach; ?>
        </select></label>
        <label class="field" data-tipo="evento"><span class="field__label">Tipo de evento</span><select name="experiencia" data-evento disabled>
          <?php foreach (TIPOS_EVENTO as $k => $rot): ?><option value="<?= e($k) ?>"<?= $v['experiencia'] === $k ? ' selected' : '' ?>><?= e($rot) ?></option><?php endforeach; ?>
        </select></label>
        <div class="row">
          <label class="field"><span class="field__label">Data</span><input type="date" name="data" value="<?= e($v['data']) ?>" required /></label>
          <label class="field" data-tipo="visita"><span class="field__label">Horário</span><select name="horario">
            <?php foreach (TURNOS as $k => $rot): ?><option value="<?= e($k) ?>"<?= $v['horario'] === $k ? ' selected' : '' ?>><?= e($rot) ?></option><?php endforeach; ?>
          </select></label>
          <label class="field field--sm"><span class="field__label">Pessoas</span><input type="number" name="pessoas" min="1" value="<?= e($v['pessoas']) ?>" required /></label>
        </div>
        <label class="field"><span class="field__label">Observações</span><textarea name="observacoes" rows="3" maxlength="1500"><?= e($v['observacoes']) ?></textarea></label>
        <fieldset class="choices">
          <legend class="field__label">Situação</legend>
          <label class="choice"><input type="radio" name="status" value="confirmada"<?= $v['status'] === 'confirmada' ? ' checked' : '' ?> /><span class="choice__box badge--confirmada">Já confirmada</span></label>
          <label class="choice"><input type="radio" name="status" value="pendente"<?= $v['status'] === 'pendente' ? ' checked' : '' ?> /><span class="choice__box badge--pendente">Aguardando</span></label>
        </fieldset>
        <label class="check" data-tipo="visita"><input type="checkbox" name="forcar" value="1" /> Registrar mesmo se passar da lotação</label>
        <label class="check"><input type="checkbox" name="avisar" value="1" checked /> Enviar confirmação por e-mail (se houver e-mail)</label>
        <button class="btn btn--gold" type="submit">Registrar</button>
      </form>
    </section>
    <script>
      // Mostra só os campos do tipo escolhido (visita ou evento).
      (() => {
        const f = document.getElementById('nova');
        const sync = () => {
          const tipo = f.querySelector('input[name=tipo]:checked').value;
          f.querySelectorAll('[data-tipo]').forEach((el) => { el.hidden = el.dataset.tipo !== tipo; });
          f.querySelector('[data-visita]').disabled = tipo !== 'visita';
          f.querySelector('[data-evento]').disabled = tipo !== 'evento';
        };
        f.querySelectorAll('input[name=tipo]').forEach((r) => r.addEventListener('change', sync));
        sync();
      })();
    </script>
<?php painel_footer();
