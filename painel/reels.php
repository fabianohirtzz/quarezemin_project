<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/reels.php';

$user = require_login();
$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'subir' || $acao === 'descer') {
        reels_mover($id, $acao === 'subir' ? -1 : 1);
    } elseif ($acao === 'ativo') {
        $ativo = !empty($_POST['ativo']) ? 1 : 0;
        $pdo->prepare('UPDATE reels SET ativo = ? WHERE id = ?')->execute([$ativo, $id]);
        flash($ativo ? 'Reel visível no site.' : 'Reel escondido do site.');
    } elseif ($acao === 'excluir') {
        $st = $pdo->prepare('SELECT capa, titulo FROM reels WHERE id = ?');
        $st->execute([$id]);
        if ($r = $st->fetch()) {
            $pdo->prepare('DELETE FROM reels WHERE id = ?')->execute([$id]);
            apagar_upload($r['capa']);
            flash('Reel "' . $r['titulo'] . '" removido.');
        }
    }
    header('Location: reels.php' . ($acao === 'subir' || $acao === 'descer' ? '#reel-' . $id : ''));
    exit;
}

$reels = reels_lista(false);
$visiveis = count(array_filter($reels, fn($r) => $r['ativo']));

function botao_reel(int $id, string $acao, string $rotulo, string $icone, bool $desativado = false): string
{
    return '<form method="post" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '" /><input type="hidden" name="acao" value="' . e($acao) . '" />'
        . '<button class="icon-btn" type="submit" aria-label="' . e($rotulo) . '" title="' . e($rotulo) . '"' . ($desativado ? ' disabled' : '') . '>'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icone . '</svg></button></form>';
}

painel_header('Reels', 'reels', $user);
?>
    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span>Bastidores da cantina · página inicial<span aria-hidden="true"></span></p>
        <h1 class="title"><em>Reels</em> do site</h1>
        <p class="sub"><?= $visiveis ?> <?= $visiveis === 1 ? 'reel aparece' : 'reels aparecem' ?> no site, nesta ordem. Use as setas para reorganizar.</p>
      </div>
      <a class="btn btn--gold" href="reel.php">+ Adicionar reel</a>
    </header>

    <?php if (!$reels): ?>
      <p class="empty">Nenhum reel cadastrado. Sem reels, a seção Bastidores some da página inicial.</p>
    <?php else: ?>
      <ol class="reel-list">
        <?php foreach ($reels as $i => $r):
            $ig = $r['instagram'] ? instagram_link($r['instagram']) : null; ?>
          <li class="reel-row<?= $r['ativo'] ? '' : ' is-off' ?>" id="reel-<?= (int) $r['id'] ?>">
            <span class="reel-row__pos"><?= $i + 1 ?></span>
            <a class="reel-row__thumb" href="reel.php?id=<?= (int) $r['id'] ?>">
              <?php if ($r['capa']): ?><img src="../<?= e($r['capa']) ?>" alt="" loading="lazy" /><?php else: ?><span class="reel-row__nocover">Sem capa</span><?php endif; ?>
            </a>
            <div class="reel-row__info">
              <strong><?= e($r['titulo']) ?></strong>
              <span class="reel-row__src">
                <?php if ($r['video']): ?><span class="tag tag--gold">Vídeo do site</span><?php endif; ?>
                <?php if ($ig): ?><a href="<?= e($ig['url']) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
              </span>
            </div>
            <div class="reel-row__actions">
              <?= botao_reel((int) $r['id'], 'subir', 'Mover para cima', '<path d="m18 15-6-6-6 6"/>', $i === 0) ?>
              <?= botao_reel((int) $r['id'], 'descer', 'Mover para baixo', '<path d="m6 9 6 6 6-6"/>', $i === count($reels) - 1) ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="acao" value="ativo" /><input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
                <label class="switch" title="<?= $r['ativo'] ? 'Aparece no site' : 'Escondido' ?>">
                  <input type="checkbox" name="ativo" value="1"<?= $r['ativo'] ? ' checked' : '' ?> onchange="this.form.submit()" />
                  <span class="switch__ui" aria-hidden="true"></span><span class="sr-only">Mostrar no site</span>
                </label>
              </form>
              <a class="btn btn--sm" href="reel.php?id=<?= (int) $r['id'] ?>">Editar</a>
              <form method="post" class="inline-form" onsubmit="return confirm('Remover este reel do site?');">
                <?= csrf_field() ?><input type="hidden" name="acao" value="excluir" /><input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
                <button class="icon-btn icon-btn--danger" type="submit" aria-label="Remover <?= e($r['titulo']) ?>" title="Remover"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
<?php painel_footer();
