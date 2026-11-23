<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/eventos.php';

$user = require_login();
$pdo = db();
$hoje = date('Y-m-d');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['acao'] ?? '') === 'publicar') {
        $pub = !empty($_POST['publicado']) ? 1 : 0;
        $pdo->prepare('UPDATE eventos SET publicado = ?, atualizado_em = ? WHERE id = ?')->execute([$pub, now(), $id]);
        flash($pub ? 'Evento publicado no site.' : 'Evento escondido do site.');
    }
    header('Location: eventos.php');
    exit;
}

$st = $pdo->prepare('SELECT * FROM eventos WHERE data >= ? ORDER BY data, id');
$st->execute([$hoje]);
$proximos = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM eventos WHERE data < ? ORDER BY data DESC LIMIT 12');
$st->execute([$hoje]);
$passados = $st->fetchAll();

function card_evento_painel(array $e, bool $passado): void
{
    $foto = '../' . ($e['foto'] ?: EVENTO_FOTO_PADRAO);
    ?>
    <article class="ev<?= $passado ? ' ev--past' : '' ?><?= !$e['publicado'] ? ' ev--hidden' : '' ?>">
      <a class="ev__img" href="evento.php?id=<?= (int) $e['id'] ?>"><img src="<?= e($foto) ?>" alt="" loading="lazy" /><span class="ev__date"><?= e(data_card($e['data'])) ?></span></a>
      <div class="ev__body">
        <p class="ev__line"><?= e($e['linha'] ?: ' ') ?></p>
        <h3 class="ev__title"><a href="evento.php?id=<?= (int) $e['id'] ?>"><?php [$p1, $resto] = array_pad(explode(' ', $e['titulo'], 2), 2, ''); ?><em><?= e($p1) ?></em> <?= e($resto) ?></a></h3>
        <p class="ev__meta"><?= e(implode(' · ', array_filter([$e['horario'], $e['local'], $e['info']]))) ?></p>
      </div>
      <footer class="ev__foot">
        <?php if ($passado): ?>
          <span class="hint">Já aconteceu, saiu do site</span>
        <?php else: ?>
          <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="acao" value="publicar" /><input type="hidden" name="id" value="<?= (int) $e['id'] ?>" />
            <label class="switch" title="<?= $e['publicado'] ? 'Aparece no site' : 'Escondido do site' ?>">
              <input type="checkbox" name="publicado" value="1"<?= $e['publicado'] ? ' checked' : '' ?> onchange="this.form.submit()" />
              <span class="switch__ui" aria-hidden="true"></span>
              <span class="ev__pub"><?= $e['publicado'] ? 'No site' : 'Escondido' ?></span>
            </label>
          </form>
        <?php endif; ?>
        <a class="btn btn--sm" href="evento.php?id=<?= (int) $e['id'] ?>">Editar</a>
      </footer>
    </article>
    <?php
}

painel_header('Eventos', 'eventos', $user);
?>
    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span>Aparecem em Visitas e Eventos<span aria-hidden="true"></span></p>
        <h1 class="title"><em>Eventos</em> da cantina</h1>
        <p class="sub">Cada evento publicado aparece no site e sai sozinho depois da data.</p>
      </div>
      <a class="btn btn--gold" href="evento.php">+ Novo evento</a>
    </header>

    <section class="ev-section">
      <h2 class="panel__title">Próximos</h2>
      <?php if (!$proximos): ?>
        <p class="empty">Nenhum evento programado. Quando não há eventos, a seção "Vem aí" some do site.</p>
      <?php else: ?>
        <div class="ev-grid"><?php foreach ($proximos as $e) card_evento_painel($e, false); ?></div>
      <?php endif; ?>
    </section>

    <?php if ($passados): ?>
      <section class="ev-section">
        <h2 class="panel__title">Já aconteceram</h2>
        <div class="ev-grid"><?php foreach ($passados as $e) card_evento_painel($e, true); ?></div>
      </section>
    <?php endif; ?>
<?php painel_footer();
