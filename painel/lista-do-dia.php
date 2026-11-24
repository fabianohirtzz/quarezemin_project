<?php
declare(strict_types=1);

/* Lista de visitas de um dia, pronta para imprimir e usar na recepção. */

require __DIR__ . '/../app/painel-layout.php';

require_login();
$data = data_valida((string) ($_GET['data'] ?? '')) ? $_GET['data'] : date('Y-m-d');

$st = db()->prepare("SELECT * FROM agendamentos WHERE data = ? AND status <> 'cancelada'
    ORDER BY CASE WHEN horario = 'manha' THEN 0 WHEN horario = 'tarde' THEN 1 ELSE 2 END, status = 'pendente', nome");
$st->execute([$data]);
$grupos = [];
foreach ($st->fetchAll() as $a) {
    $chave = $a['tipo'] === 'evento' ? 'evento' : (string) $a['horario'];
    $grupos[$chave][] = $a;
}
$titulos = TURNOS + ['evento' => 'Eventos'];
$totalPessoas = 0;
foreach ($grupos as $g) {
    foreach ($g as $a) {
        $totalPessoas += (int) $a['pessoas'];
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Visitas de <?= e(date('d/m/Y', strtotime($data))) ?> · Vinhos Quarezemin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/impressao.css" />
</head>
<body>
  <div class="toolbar">
    <form method="get"><label>Dia <input type="date" name="data" value="<?= e($data) ?>" onchange="this.form.submit()" /></label></form>
    <button type="button" onclick="window.print()">Imprimir</button>
  </div>

  <header class="head">
    <img src="../imagens/logo-quarezemin.png" alt="Vinhos Quarezemin" />
    <div>
      <p class="eyebrow">Lista de visitas</p>
      <h1><?= e(ucfirst(data_extenso($data))) ?></h1>
    </div>
    <p class="total"><strong><?= $totalPessoas ?></strong> pessoas<br /><?= array_sum(array_map('count', $grupos)) ?> agendamentos</p>
  </header>

  <?php if (!$grupos): ?>
    <p class="empty">Nenhuma visita agendada para este dia.</p>
  <?php endif; ?>

  <?php foreach ($titulos as $chave => $titulo): if (empty($grupos[$chave])) continue;
      $soma = array_sum(array_map(fn($a) => (int) $a['pessoas'], $grupos[$chave])); ?>
    <section class="grupo">
      <h2><?= e($titulo) ?> <span><?= $soma ?> pessoas</span></h2>
      <table>
        <thead><tr><th class="c">Chegou</th><th>Nome</th><th class="n">Pessoas</th><th>Experiência</th><th>WhatsApp</th><th>Observações</th></tr></thead>
        <tbody>
          <?php foreach ($grupos[$chave] as $a): ?>
            <tr>
              <td class="c"><span class="box"></span></td>
              <td><strong><?= e($a['nome']) ?></strong><?= $a['status'] === 'pendente' ? ' <em class="pend">a confirmar</em>' : '' ?></td>
              <td class="n"><?= (int) $a['pessoas'] ?></td>
              <td><?= e($a['tipo'] === 'visita' ? (EXPERIENCIAS[$a['experiencia']] ?? $a['experiencia']) : (TIPOS_EVENTO[$a['experiencia']] ?? $a['experiencia'])) ?></td>
              <td class="nw"><?= e(format_whatsapp($a['whatsapp'])) ?></td>
              <td class="obs"><?= e(trim(($a['observacoes'] ?? '') . ($a['notas'] ? ' · ' . $a['notas'] : ''), ' ·')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endforeach; ?>

  <footer class="foot">Impresso em <?= e(data_br(now())) ?> · Painel Vinhos Quarezemin</footer>
</body>
</html>
