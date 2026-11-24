<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/avaliacoes.php';

$user = require_login();
$pdo = db();
$erroResumo = '';
$resumo = avaliacoes_resumo();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'resumo') {
        $notaTxt = str_replace(',', '.', trim((string) ($_POST['nota'] ?? '')));
        $totalTxt = only_digits((string) ($_POST['total'] ?? ''));
        $linkGoogle = clean_text($_POST['link_google'] ?? '', 500);
        $linkAvaliar = clean_text($_POST['link_avaliar'] ?? '', 500);
        $resumo = ['nota' => $notaTxt, 'total' => $totalTxt, 'link_google' => $linkGoogle, 'link_avaliar' => $linkAvaliar];
        if (!is_numeric($notaTxt) || (float) $notaTxt < 1 || (float) $notaTxt > 5) {
            $erroResumo = 'A nota precisa ficar entre 1,0 e 5,0, como aparece no Google.';
        } elseif ($totalTxt === '' || (int) $totalTxt < 1) {
            $erroResumo = 'Informe quantas avaliações a vinícola tem no Google.';
        } elseif (!link_google_valido($linkGoogle)) {
            $erroResumo = 'O link da página no Google precisa ser um endereço do Google começando com https://.';
        } elseif ($linkAvaliar !== '' && !link_google_valido($linkAvaliar)) {
            $erroResumo = 'O link para avaliar precisa ser um endereço do Google começando com https://.';
        } else {
            salvar_ajuste('avaliacoes_resumo', [
                'nota' => round((float) $notaTxt, 1),
                'total' => (int) $totalTxt,
                'link_google' => $linkGoogle,
                'link_avaliar' => $linkAvaliar,
            ]);
            flash('Nota e links do Google atualizados.');
            header('Location: avaliacoes.php');
            exit;
        }
    } else {
        if ($acao === 'subir' || $acao === 'descer') {
            avaliacoes_mover($id, $acao === 'subir' ? -1 : 1);
        } elseif ($acao === 'ativo') {
            $ativo = !empty($_POST['ativo']) ? 1 : 0;
            $pdo->prepare('UPDATE avaliacoes SET ativo = ? WHERE id = ?')->execute([$ativo, $id]);
            flash($ativo ? 'Avaliação visível no site.' : 'Avaliação escondida do site.');
        } elseif ($acao === 'excluir') {
            $st = $pdo->prepare('SELECT autor FROM avaliacoes WHERE id = ?');
            $st->execute([$id]);
            if ($autor = $st->fetchColumn()) {
                $pdo->prepare('DELETE FROM avaliacoes WHERE id = ?')->execute([$id]);
                flash('Avaliação de ' . $autor . ' removida.');
            }
        }
        header('Location: avaliacoes.php' . ($acao === 'subir' || $acao === 'descer' ? '#aval-' . $id : ''));
        exit;
    }
}

$lista = avaliacoes_lista(false);
$visiveis = count(array_filter($lista, fn($a) => $a['ativo']));
$naoCabem = max(0, $visiveis - AVALIACOES_MAX_SITE);

function botao_aval(int $id, string $acao, string $rotulo, string $icone, bool $desativado = false): string
{
    return '<form method="post" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . $id . '" /><input type="hidden" name="acao" value="' . e($acao) . '" />'
        . '<button class="icon-btn" type="submit" aria-label="' . e($rotulo) . '" title="' . e($rotulo) . '"' . ($desativado ? ' disabled' : '') . '>'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icone . '</svg></button></form>';
}

function estrelas_html(int $n): string
{
    $s = '<span class="stars" aria-label="' . $n . ' de 5 estrelas">';
    for ($i = 1; $i <= 5; $i++) {
        $s .= '<svg viewBox="0 0 24 24" aria-hidden="true"' . ($i > $n ? ' class="is-off"' : '') . '><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z"/></svg>';
    }
    return $s . '</span>';
}

painel_header('Avaliações', 'avaliacoes', $user);
?>
    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span>Quem visita, recomenda · página inicial<span aria-hidden="true"></span></p>
        <h1 class="title"><em>Avaliações</em> do Google</h1>
        <p class="sub"><?= $visiveis ?> <?= $visiveis === 1 ? 'avaliação aparece' : 'avaliações aparecem' ?> no site, nesta ordem.</p>
      </div>
      <div class="page-top__actions">
        <a class="btn" href="qrcode.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
          QR Code
        </a>
        <a class="btn btn--gold" href="avaliacao.php">+ Adicionar avaliação</a>
      </div>
    </header>

    <section class="panel aval-resumo">
      <div class="aval-resumo__now" aria-hidden="true">
        <span class="aval-resumo__num"><?= e(nota_br((float) avaliacoes_resumo()['nota'])) ?></span>
        <span><?= estrelas_html((int) round(avaliacoes_resumo()['nota'])) ?><br /><?= (int) avaliacoes_resumo()['total'] ?> avaliações</span>
      </div>
      <div>
        <h2 class="panel__title">Nota no Google</h2>
        <p class="hint">Quando a nota ou o número de avaliações mudar no Google, atualize aqui. O site mostra exatamente o que estiver salvo.</p>
      </div>
      <?php if ($erroResumo): ?><p class="form-error" role="alert"><?= e($erroResumo) ?></p><?php endif; ?>
      <form method="post" class="form aval-resumo__form">
        <?= csrf_field() ?><input type="hidden" name="acao" value="resumo" />
        <div class="row">
          <label class="field field--nota"><span class="field__label">Nota</span>
            <input type="text" name="nota" inputmode="decimal" maxlength="3" value="<?= e(is_float($resumo['nota']) ? nota_br($resumo['nota']) : (string) $resumo['nota']) ?>" required /></label>
          <label class="field field--nota"><span class="field__label">Nº de avaliações</span>
            <input type="text" name="total" inputmode="numeric" maxlength="6" value="<?= e((string) $resumo['total']) ?>" required /></label>
        </div>
        <label class="field"><span class="field__label">Página da vinícola no Google</span>
          <input type="text" name="link_google" inputmode="url" spellcheck="false" value="<?= e($resumo['link_google']) ?>" required />
          <span class="hint">Abre no botão "Ver todas no Google" do site.</span></label>
        <label class="field"><span class="field__label">Link para deixar avaliação (opcional)</span>
          <input type="text" name="link_avaliar" inputmode="url" spellcheck="false" value="<?= e($resumo['link_avaliar']) ?>" placeholder="https://g.page/r/.../review" />
          <span class="hint">No Perfil da Empresa no Google, clique em "Pedir avaliações" e copie o link. Com ele, o QR Code já abre a janela de avaliar. Sem ele, o QR Code abre a página da vinícola.</span></label>
        <div><button class="btn btn--gold" type="submit">Salvar nota e links</button></div>
      </form>
    </section>

    <?php if ($naoCabem): ?>
      <p class="notice">O site mostra até <?= AVALIACOES_MAX_SITE ?> avaliações. As <?= $naoCabem ?> últimas visíveis da lista ficam de fora; esconda algumas ou mude a ordem.</p>
    <?php endif; ?>

    <?php if (!$lista): ?>
      <p class="empty">Nenhuma avaliação cadastrada. Sem avaliações, o site mostra só a nota e o botão para o Google.</p>
    <?php else: ?>
      <ol class="aval-list">
        <?php foreach ($lista as $i => $a): ?>
          <li class="aval-row<?= $a['ativo'] ? '' : ' is-off' ?>" id="aval-<?= (int) $a['id'] ?>">
            <span class="reel-row__pos"><?= $i + 1 ?></span>
            <div class="aval-row__info">
              <blockquote>“<?= e($a['texto']) ?>”</blockquote>
              <span class="aval-row__meta"><strong><?= e($a['autor']) ?></strong> <?= estrelas_html((int) $a['estrelas']) ?></span>
            </div>
            <div class="reel-row__actions">
              <?= botao_aval((int) $a['id'], 'subir', 'Mover para cima', '<path d="m18 15-6-6-6 6"/>', $i === 0) ?>
              <?= botao_aval((int) $a['id'], 'descer', 'Mover para baixo', '<path d="m6 9 6 6 6-6"/>', $i === count($lista) - 1) ?>
              <form method="post" class="inline-form">
                <?= csrf_field() ?><input type="hidden" name="acao" value="ativo" /><input type="hidden" name="id" value="<?= (int) $a['id'] ?>" />
                <label class="switch" title="<?= $a['ativo'] ? 'Aparece no site' : 'Escondida' ?>">
                  <input type="checkbox" name="ativo" value="1"<?= $a['ativo'] ? ' checked' : '' ?> onchange="this.form.submit()" />
                  <span class="switch__ui" aria-hidden="true"></span><span class="sr-only">Mostrar no site</span>
                </label>
              </form>
              <a class="btn btn--sm" href="avaliacao.php?id=<?= (int) $a['id'] ?>">Editar</a>
              <form method="post" class="inline-form" onsubmit="return confirm('Remover esta avaliação do site?');">
                <?= csrf_field() ?><input type="hidden" name="acao" value="excluir" /><input type="hidden" name="id" value="<?= (int) $a['id'] ?>" />
                <button class="icon-btn icon-btn--danger" type="submit" aria-label="Remover avaliação de <?= e($a['autor']) ?>" title="Remover"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
<?php painel_footer();
