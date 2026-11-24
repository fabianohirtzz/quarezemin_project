<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/reels.php';

$user = require_login();
$pdo = db();
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$erro = '';

$r = ['id' => 0, 'titulo' => '', 'instagram' => '', 'video' => null, 'capa' => null, 'descricao_capa' => '', 'ativo' => 1];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM reels WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        flash('Reel não encontrado.');
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
        $linkTxt = clean_text($_POST['instagram'] ?? '', 300);
        $alt = clean_text($_POST['descricao_capa'] ?? '', 160);
        $ig = $linkTxt !== '' ? instagram_link($linkTxt) : null;
        $r = array_merge($r, ['titulo' => $titulo, 'instagram' => $linkTxt, 'descricao_capa' => $alt]);

        if ($titulo === '') {
            $erro = 'Dê um título curto ao reel. Ele aparece sobre a capa.';
        } elseif ($linkTxt !== '' && !$ig) {
            $erro = 'Esse link não parece ser de um reel do Instagram. Copie pelo botão "Compartilhar" > "Copiar link".';
        } elseif (!$ig && !$r['video']) {
            $erro = 'Cole o link do reel no Instagram.';
        }

        $capaNova = null;
        if (!$erro && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $capaNova = salvar_imagem_upload($_FILES['capa'], 'reels', 720, 300, 400);
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
                flash('Reel atualizado.');
            } else {
                reels_semear();
                $ordem = (int) $pdo->query('SELECT COALESCE(MIN(ordem), 10) FROM reels')->fetchColumn() - 10;
                $pdo->prepare('INSERT INTO reels (titulo, instagram, capa, descricao_capa, ordem, ativo, criado_em) VALUES (?, ?, ?, ?, ?, 1, ?)')
                    ->execute([$titulo, $ig['url'], $capa, $alt ?: null, $ordem, now()]);
                reels_renumerar();
                flash('Reel adicionado. Ele aparece em primeiro lugar na página inicial.');
            }
            header('Location: reels.php');
            exit;
        }
        if ($capaNova) {
            apagar_upload($capaNova);
        }
    }
}

$igAtual = $r['instagram'] ? instagram_link((string) $r['instagram']) : null;
painel_header($id ? 'Editar reel' : 'Adicionar reel', 'reels', $user);
?>
    <a class="back" href="reels.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Reels
    </a>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span><?= $r['video'] ? 'Vídeo hospedado no site' : 'Link do Instagram' ?><span aria-hidden="true"></span></p>
      <h1 class="title"><em><?= $id ? 'Editar' : 'Adicionar' ?></em> reel</h1>
    </header>

    <div class="detail detail--editor">
      <section class="panel">
        <?php if ($erro): ?><p class="form-error" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" class="form" enctype="multipart/form-data" id="reel-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $id ?>" />
          <label class="field"><span class="field__label">Link do reel no Instagram<?= $r['video'] ? ' (opcional)' : '' ?></span>
            <input type="text" name="instagram" autocomplete="off" spellcheck="false" value="<?= e($r['instagram'] ?? '') ?>" placeholder="https://www.instagram.com/reel/..." inputmode="url" />
            <span class="hint">No Instagram, toque nos três pontinhos do reel &gt; Copiar link, e cole aqui.<?= $r['video'] ? ' Este reel já toca o vídeo hospedado no site; o link é só para quem quiser abrir no Instagram.' : '' ?></span></label>
          <label class="field"><span class="field__label">Título</span>
            <input type="text" name="titulo" value="<?= e($r['titulo']) ?>" maxlength="80" placeholder="Ex.: Vindima 2027" required id="reel-titulo" />
            <span class="hint">Curto, aparece sobre a capa.</span></label>
          <label class="field"><span class="field__label">Capa (opcional)</span>
            <input type="file" name="capa" accept="image/jpeg,image/png,image/webp" id="reel-capa" />
            <span class="hint">Uma imagem em pé, como um print do reel. Sem capa, o card usa o visual da marca.</span></label>
          <?php if ($r['capa'] && !$r['video']): ?><label class="check"><input type="checkbox" name="remover_capa" value="1" /> Remover a capa atual</label><?php endif; ?>
          <label class="field"><span class="field__label">Descrição da capa (opcional)</span>
            <input type="text" name="descricao_capa" value="<?= e($r['descricao_capa'] ?? '') ?>" maxlength="160" placeholder="Ex.: Cachos de uva Goethe nos parreirais" />
            <span class="hint">Ajuda o Google e quem usa leitor de tela.</span></label>
          <button class="btn btn--gold" type="submit"><?= $id ? 'Salvar alterações' : 'Adicionar reel' ?></button>
        </form>
      </section>

      <aside class="preview" aria-label="Prévia do card">
        <p class="field__label">Prévia na página inicial</p>
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
