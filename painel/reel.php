<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/reels.php';

$user = require_login();
$pdo = db();
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$erro = '';

$r = ['id' => 0, 'titulo' => '', 'instagram' => '', 'video' => null, 'capa' => null, 'descricao_capa' => '', 'ativo' => 1,
    'pagina' => reels_pagina($_POST['pagina'] ?? $_GET['pagina'] ?? 'home')];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM reels WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        flash('Item não encontrado.');
        header('Location: reels.php');
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $erro = 'A imagem é grande demais. Envie um arquivo de até 12 MB.';
    } else {
        csrf_check();
        $titulo = clean_text($_POST['titulo'] ?? '', 80);
        $personalize = $r['pagina'] === 'personalize';
        // Na página Personalize um item pode ser só uma foto (sem link): a "capa" é a foto.
        $temFotoNova = ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $fotoOk = $personalize && ($temFotoNova || ($r['capa'] && empty($_POST['remover_capa'])));
        $linkTxt = clean_text($_POST['instagram'] ?? '', 300);
        $alt = clean_text($_POST['descricao_capa'] ?? '', 160);
        $ig = $linkTxt !== '' ? instagram_link($linkTxt) : null;
        $r = array_merge($r, ['titulo' => $titulo, 'instagram' => $linkTxt, 'descricao_capa' => $alt]);

        if ($titulo === '') {
            $erro = 'Dê um título curto ao reel. Ele aparece sobre a capa.';
        } elseif ($linkTxt !== '' && !$ig) {
            $erro = 'Esse link não parece ser de um reel do Instagram. Copie pelo botão "Compartilhar" > "Copiar link".';
        } elseif (!$ig && !$r['video'] && !$fotoOk) {
            $erro = $personalize
                ? 'Cole o link do reel no Instagram ou escolha uma foto.'
                : 'Cole o link do reel no Instagram.';
        }

        $capaNova = null;
        if (!$erro && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $capaNova = $personalize && !$ig
                    ? salvar_imagem_upload($_FILES['capa'], 'reels', 1400, 400, 400)
                    : salvar_imagem_upload($_FILES['capa'], 'reels', 720, 300, 400);
            } catch (RuntimeException $ex) {
                $erro = $ex->getMessage();
            }
        }

        if (!$erro) {
            $capa = $capaNova ?: (!empty($_POST['remover_capa']) ? null : $r['capa']);
            if ($id) {
                $pdo->prepare('UPDATE reels SET titulo = ?, instagram = ?, capa = ?, descricao_capa = ? WHERE id = ?')
                    ->execute([$titulo, $ig['url'] ?? null, $capa, $alt ?: null, $id]);
                if ($capa !== $r['capa']) {
                    apagar_upload($r['capa']);
                }
                flash($ig || $r['video'] ? 'Vídeo atualizado.' : 'Foto atualizada.');
            } else {
                reels_semear();
                $stMin = $pdo->prepare('SELECT COALESCE(MIN(ordem), 10) FROM reels WHERE pagina = ?');
                $stMin->execute([$r['pagina']]);
                $ordem = (int) $stMin->fetchColumn() - 10;
                $pdo->prepare('INSERT INTO reels (titulo, instagram, capa, descricao_capa, pagina, ordem, ativo, criado_em) VALUES (?, ?, ?, ?, ?, ?, 1, ?)')
                    ->execute([$titulo, $ig['url'] ?? null, $capa, $alt ?: null, $r['pagina'], $ordem, now()]);
                reels_renumerar($r['pagina']);
                flash(($ig ? 'Vídeo adicionado.' : 'Foto adicionada.') . ' Aparece em primeiro lugar ' . ($personalize ? 'na página Personalize seu Rótulo.' : 'na página inicial.'));
            }
            header('Location: reels.php?pagina=' . $r['pagina']);
            exit;
        }
        if ($capaNova) {
            apagar_upload($capaNova);
        }
    }
}

$igAtual = $r['instagram'] ? instagram_link((string) $r['instagram']) : null;
$personalize = $r['pagina'] === 'personalize';
painel_header($id ? 'Editar item' : 'Adicionar item', 'reels', $user);
?>
    <a class="back" href="reels.php?pagina=<?= e($r['pagina']) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Reels · <?= e(REELS_PAGINAS[$r['pagina']]) ?>
    </a>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span><?= e(REELS_PAGINAS[$r['pagina']]) ?> · <?= $r['video'] ? 'vídeo hospedado no site' : ($personalize ? 'vídeo do Instagram ou foto' : 'link do Instagram') ?><span aria-hidden="true"></span></p>
      <h1 class="title"><em><?= $id ? 'Editar' : 'Adicionar' ?></em> <?= $personalize ? 'vídeo ou foto' : 'reel' ?></h1>
    </header>

    <div class="detail detail--editor">
      <section class="panel">
        <?php if ($erro): ?><p class="form-error" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" class="form" enctype="multipart/form-data" id="reel-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $id ?>" />
          <input type="hidden" name="pagina" value="<?= e($r['pagina']) ?>" />
          <label class="field"><span class="field__label">Link do reel no Instagram<?= $r['video'] || $personalize ? ' (opcional)' : '' ?></span>
            <input type="text" name="instagram" autocomplete="off" spellcheck="false" value="<?= e($r['instagram'] ?? '') ?>" placeholder="https://www.instagram.com/reel/..." inputmode="url" />
            <span class="hint">No Instagram, toque nos três pontinhos do reel &gt; Copiar link, e cole aqui.<?= $r['video'] ? ' Este reel já toca o vídeo hospedado no site; o link é só para quem quiser abrir no Instagram.' : ($personalize ? ' Para mostrar uma foto, deixe em branco e escolha a foto abaixo.' : '') ?></span></label>
          <label class="field"><span class="field__label">Título</span>
            <input type="text" name="titulo" value="<?= e($r['titulo']) ?>" maxlength="80" placeholder="Ex.: Vindima 2027" required id="reel-titulo" />
            <span class="hint">Curto, aparece sobre a capa.</span></label>
          <label class="field"><span class="field__label"><?= $personalize ? 'Foto ou capa' : 'Capa (opcional)' ?></span>
            <input type="file" name="capa" accept="image/jpeg,image/png,image/webp" id="reel-capa" />
            <span class="hint"><?= $personalize ? 'Sem link do Instagram, esta foto aparece sozinha na galeria e abre grande ao clicar. Com link, ela vira a capa do vídeo.' : 'Uma imagem em pé, como um print do reel. Sem capa, o card usa o visual da marca.' ?></span></label>
          <?php if ($r['capa'] && !$r['video']): ?><label class="check"><input type="checkbox" name="remover_capa" value="1" /> Remover a capa atual</label><?php endif; ?>
          <label class="field"><span class="field__label">Descrição da capa (opcional)</span>
            <input type="text" name="descricao_capa" value="<?= e($r['descricao_capa'] ?? '') ?>" maxlength="160" placeholder="Ex.: Cachos de uva Goethe nos parreirais" />
            <span class="hint">Ajuda o Google e quem usa leitor de tela.</span></label>
          <button class="btn btn--gold" type="submit"><?= $id ? 'Salvar alterações' : 'Adicionar reel' ?></button>
        </form>
      </section>

      <aside class="preview" aria-label="Prévia do card">
        <p class="field__label">Prévia <?= $personalize ? 'na página Personalize seu Rótulo' : 'na página inicial' ?></p>
        <div class="reel-card<?= $r['capa'] ? '' : ' reel-card--empty' ?>" id="pv-card">
          <img id="pv-capa" src="<?= $r['capa'] ? '../' . e($r['capa']) : '' ?>" alt=""<?= $r['capa'] ? '' : ' hidden' ?> />
          <span class="reel-card__mark" aria-hidden="true"><img src="../imagens/logo-quarezemin.png" alt="" /></span>
          <span class="reel-card__play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg></span>
          <span class="reel-card__caption" id="pv-titulo"><?= e($r['titulo'] ?: 'Título do reel') ?></span>
        </div>
        <?php if ($igAtual): ?><a class="link" href="<?= e($igAtual['url']) ?>" target="_blank" rel="noopener">Abrir no Instagram</a><?php endif; ?>
      </aside>
    </div>
    <script>
      (() => {
        const t = document.getElementById('reel-titulo');
        const out = document.getElementById('pv-titulo');
        t.addEventListener('input', () => { out.textContent = t.value || 'Título do reel'; });
        document.getElementById('reel-capa').addEventListener('change', (e) => {
          const f = e.target.files[0];
          if (!f) return;
          const img = document.getElementById('pv-capa');
          img.src = URL.createObjectURL(f);
          img.hidden = false;
          document.getElementById('pv-card').classList.remove('reel-card--empty');
        });
      })();
    </script>
<?php painel_footer();
