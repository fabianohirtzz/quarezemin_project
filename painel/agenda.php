<?php
declare(strict_types=1);

/* Datas e lotação: limite de pessoas por horário, dias de visita e datas bloqueadas. */

require __DIR__ . '/../app/painel-layout.php';

$user = require_login();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'regras') {
        $limite = [];
        foreach (TURNOS as $t => $_) {
            $limite[$t] = max(0, min(500, (int) ($_POST['limite'][$t] ?? 0)));
        }
        $dias = [];
        for ($w = 0; $w <= 6; $w++) {
            $dias[$w] = array_values(array_intersect(array_keys(TURNOS), (array) ($_POST['dias'][$w] ?? [])));
        }
        $antecedencia = max(0, min(30, (int) ($_POST['antecedencia'] ?? 1)));
        salvar_ajuste('limite_turno', $limite);
        salvar_ajuste('dias_turnos', $dias);
        salvar_ajuste('antecedencia_dias', $antecedencia);
        flash('Regras da agenda salvas.');
    }

    if ($acao === 'bloquear') {
        $de = (string) ($_POST['de'] ?? '');
        $ate = (string) ($_POST['ate'] ?? '') ?: $de;
        $turno = in_array($_POST['turno'] ?? '', array_merge(['dia'], array_keys(TURNOS)), true) ? $_POST['turno'] : 'dia';
        $motivo = clean_text($_POST['motivo'] ?? '', 120);
        if (!data_valida($de) || !data_valida($ate) || $ate < $de) {
            flash('Confira as datas do bloqueio.');
        } elseif ((strtotime($ate) - strtotime($de)) / 86400 > 120) {
            flash('Bloqueie no máximo 120 dias de uma vez.');
        } else {
            $ins = $pdo->prepare('INSERT INTO bloqueios (data, turno, motivo, criado_em) VALUES (?, ?, ?, ?)');
            $n = 0;
            for ($t = strtotime($de); $t <= strtotime($ate); $t = strtotime('+1 day', $t)) {
                $ins->execute([date('Y-m-d', $t), $turno, $motivo ?: null, now()]);
                $n++;
            }
            // Avisa se já existem pedidos ativos nos dias bloqueados
            $st = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE tipo = 'visita' AND status <> 'cancelada' AND data BETWEEN ? AND ?" . ($turno !== 'dia' ? ' AND horario = ?' : ''));
            $st->execute($turno !== 'dia' ? [$de, $ate, $turno] : [$de, $ate]);
            $conflitos = (int) $st->fetchColumn();
            flash(($n === 1 ? 'Data bloqueada.' : "{$n} dias bloqueados.")
                . ($conflitos ? ' Atenção: ' . ($conflitos === 1 ? 'já existe 1 visita' : "já existem {$conflitos} visitas") . ' nesse período. Veja na agenda se precisa remarcar.' : ''));
        }
    }

    if ($acao === 'desbloquear') {
        $pdo->prepare('DELETE FROM bloqueios WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Bloqueio removido.');
    }

    header('Location: agenda.php');
    exit;
}

$cfg = agenda_config();
$st = $pdo->prepare('SELECT * FROM bloqueios WHERE data >= ? ORDER BY data, turno');
$st->execute([date('Y-m-d')]);
$bloqueios = $st->fetchAll();

painel_header('Datas e lotação', 'agenda', $user);
?>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span>Regras do agendamento no site<span aria-hidden="true"></span></p>
      <h1 class="title"><em>Datas</em> e lotação</h1>
      <p class="sub">O site só aceita pedidos de visita nos dias e horários abertos aqui, e até o limite de pessoas de cada horário.</p>
    </header>

    <div class="detail">
      <section class="panel">
        <h2 class="panel__title">Horários e limite de pessoas</h2>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="acao" value="regras" />
          <div class="row">
            <?php foreach (TURNOS as $t => $rot): ?>
              <label class="field"><span class="field__label">Limite · <?= e(TURNOS_CURTO[$t]) ?></span>
                <input type="number" name="limite[<?= e($t) ?>]" min="0" max="500" value="<?= (int) ($cfg['limite'][$t] ?? 0) ?>" /></label>
            <?php endforeach; ?>
            <label class="field"><span class="field__label">Antecedência mínima</span>
              <select name="antecedencia">
                <?php foreach ([0 => 'Mesmo dia', 1 => '1 dia', 2 => '2 dias', 3 => '3 dias', 7 => '1 semana'] as $k => $rot): ?>
                  <option value="<?= $k ?>"<?= $cfg['antecedencia_dias'] === $k ? ' selected' : '' ?>><?= e($rot) ?></option>
                <?php endforeach; ?>
              </select></label>
          </div>
          <table class="table table--compact">
            <thead><tr><th>Dia</th><?php foreach (TURNOS as $t => $rot): ?><th><?= e($rot) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $w): $ativos = $cfg['dias'][$w] ?? $cfg['dias'][(string) $w] ?? []; ?>
                <tr>
                  <td><?= e(DIAS_SEMANA[$w]) ?></td>
                  <?php foreach (TURNOS as $t => $_): ?>
                    <td><label class="switch"><input type="checkbox" name="dias[<?= $w ?>][]" value="<?= e($t) ?>"<?= in_array($t, $ativos, true) ? ' checked' : '' ?> /><span class="switch__ui" aria-hidden="true"></span><span class="sr-only"><?= e(DIAS_SEMANA[$w] . ' ' . TURNOS_CURTO[$t]) ?></span></label></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <button class="btn btn--gold" type="submit">Salvar regras</button>
        </form>
      </section>

      <div class="stack">
        <section class="panel">
          <h2 class="panel__title">Bloquear datas</h2>
          <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="bloquear" />
            <div class="row">
              <label class="field"><span class="field__label">De</span><input type="date" name="de" min="<?= e(date('Y-m-d')) ?>" required /></label>
              <label class="field"><span class="field__label">Até (opcional)</span><input type="date" name="ate" min="<?= e(date('Y-m-d')) ?>" /></label>
            </div>
            <div class="row">
              <label class="field"><span class="field__label">Período</span><select name="turno">
                <option value="dia">Dia inteiro</option>
                <?php foreach (TURNOS as $t => $rot): ?><option value="<?= e($t) ?>">Só <?= e(mb_strtolower(TURNOS_CURTO[$t])) ?></option><?php endforeach; ?>
              </select></label>
              <label class="field"><span class="field__label">Motivo</span><input type="text" name="motivo" maxlength="120" placeholder="Feriado, colheita, evento fechado..." /></label>
            </div>
            <button class="btn" type="submit">Bloquear</button>
          </form>
        </section>

        <section class="panel">
          <h2 class="panel__title">Datas bloqueadas</h2>
          <?php if (!$bloqueios): ?>
            <p class="empty">Nenhuma data bloqueada daqui para frente.</p>
          <?php else: ?>
            <ul class="list">
              <?php foreach ($bloqueios as $b): ?>
                <li class="list__row">
                  <span class="list__main"><strong><?= e(ucfirst(data_extenso($b['data']))) ?></strong><span><?= $b['turno'] === 'dia' ? 'Dia inteiro' : 'Só ' . e(mb_strtolower(TURNOS_CURTO[$b['turno']] ?? $b['turno'])) ?><?= $b['motivo'] ? ' · ' . e($b['motivo']) : '' ?></span></span>
                  <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="acao" value="desbloquear" /><input type="hidden" name="id" value="<?= (int) $b['id'] ?>" />
                    <button class="btn btn--sm" type="submit">Liberar</button></form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>
    </div>
<?php painel_footer();
