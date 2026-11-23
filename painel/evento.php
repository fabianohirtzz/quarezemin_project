<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/eventos.php';

$user = require_login();
$pdo = db();
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$erro = '';

$ev = ['id' => 0, 'titulo' => '', 'linha' => '', 'data' => '', 'horario' => '', 'local' => 'Vinícola Quarezemin', 'info' => '', 'descricao' => '', 'foto' => null, 'publicado' => 1];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM eventos WHERE id = ?');
    $st->execute([$id]);
    $ev = $st->fetch();
    if (!$ev) {
        flash('Evento não encontrado.');
        header('Location: eventos.php');
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Upload maior que post_max_size chega sem nenhum campo: avisa em vez de "sessão expirada".
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $erro = 'A foto é grande demais. Envie uma imagem de até 12 MB.';
    } else {
        csrf_check();
        $acao = $_POST['acao'] ?? 'salvar';

        if ($acao === 'excluir' && $id) {
            $pdo->prepare('DELETE FROM eventos WHERE id = ?')->execute([$id]);
            apagar_foto_evento($ev['foto']);
            flash('Evento "' . $ev['titulo'] . '" excluído.');
            header('Location: eventos.php');
            exit;
        }

        $novo = [
            'titulo' => clean_text($_POST['titulo'] ?? '', 120),
            'linha' => clean_text($_POST['linha'] ?? '', 80),
            'data' => (string) ($_POST['data'] ?? ''),
            'horario' => clean_text($_POST['horario'] ?? '', 20),
            'local' => clean_text($_POST['local'] ?? '', 80),
            'info' => clean_text($_POST['info'] ?? '', 60),
            'descricao' => clean_text(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['descricao'] ?? '')), 600),
            'publicado' => !empty($_POST['publicado']) ? 1 : 0,
        ];
        $ev = array_merge($ev, $novo);

        if ($novo['titulo'] === '' || $novo['descricao'] === '') {
            $erro = 'Preencha o título e a descrição.';
        } elseif (!data_valida($novo['data'])) {
            $erro = 'Escolha a data do evento.';
        }

        $fotoNova = null;
        if (!$erro && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $fotoNova = salvar_foto_evento($_FILES['foto']);
            } catch (RuntimeException $ex) {
                $erro = $ex->getMessage();
            }
        }

        if (!$erro) {
            $fotoFinal = $ev['foto'];
            if ($fotoNova) {
                $fotoFinal = $fotoNova;
            } elseif (!empty($_POST['remover_foto'])) {
                $fotoFinal = null;
            }
            $agora = now();
            $vals = [$novo['titulo'], $novo['linha'] ?: null, $novo['data'], $novo['horario'] ?: null, $novo['local'] ?: null,
                $novo['info'] ?: null, $novo['descricao'], $fotoFinal, $novo['publicado'], $agora];
            if ($id) {
                $pdo->prepare('UPDATE eventos SET titulo = ?, linha = ?, data = ?, horario = ?, local = ?, info = ?, descricao = ?, foto = ?, publicado = ?, atualizado_em = ? WHERE id = ?')
                    ->execute([...$vals, $id]);
            } else {
                $pdo->prepare('INSERT INTO eventos (titulo, linha, data, horario, local, info, descricao, foto, publicado, atualizado_em, criado_em) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([...$vals, $agora]);
                $id = (int) $pdo->lastInsertId();
            }
            if ($fotoFinal !== $ev['foto']) {
                apagar_foto_evento($ev['foto']);
            }
            $passado = $novo['data'] < date('Y-m-d');
            flash($novo['publicado'] && !$passado
                ? 'Evento salvo e publicado. Ele já aparece em Visitas e Eventos.'
                : ($passado ? 'Evento salvo. Como a data já passou, ele não aparece no site.' : 'Evento salvo como rascunho (escondido do site).'));
            header('Location: eventos.php');
            exit;
        }
        if ($fotoNova) {
            apagar_foto_evento($fotoNova);
        }
    }
}

$foto = $ev['foto'] ?: EVENTO_FOTO_PADRAO;
painel_header($id ? 'Editar evento' : 'Novo evento', 'eventos', $user);
?>
    <a class="back" href="eventos.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Eventos
    </a>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span><?= $id ? 'Editar' : 'Publicar no site' ?><span aria-hidden="true"></span></p>
      <h1 class="title"><em><?= $id ? 'Editar' : 'Novo' ?></em> evento</h1>
    </header>

    <div class="detail detail--editor">
      <section class="panel">
        <?php if ($erro): ?><p class="form-error" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" class="form" enctype="multipart/form-data" id="ev-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $id ?>" />
          <input type="hidden" name="acao" value="salvar" />
          <label class="field"><span class="field__label">Título</span>
            <input type="text" name="titulo" value="<?= e($ev['titulo']) ?>" maxlength="120" placeholder="Ex.: Colheita da Uva Goethe" required data-prev="titulo" />
            <span class="hint">A primeira palavra aparece em dourado e itálico no site.</span></label>
          <label class="field"><span class="field__label">Chamada curta (opcional)</span>
            <input type="text" name="linha" value="<?= e($ev['linha'] ?? '') ?>" maxlength="80" placeholder="Ex.: Vindima · grupo fechado" data-prev="linha" /></label>
          <div class="row">
            <label class="field"><span class="field__label">Data</span><input type="date" name="data" value="<?= e($ev['data']) ?>" required data-prev="data" /></label>
            <label class="field"><span class="field__label">Horário</span><input type="text" name="horario" value="<?= e($ev['horario'] ?? '') ?>" maxlength="20" placeholder="Ex.: 19h" data-prev="horario" /></label>
          </div>
          <div class="row">
            <label class="field"><span class="field__label">Local</span><input type="text" name="local" value="<?= e($ev['local'] ?? '') ?>" maxlength="80" data-prev="local" /></label>
            <label class="field"><span class="field__label">Destaque (opcional)</span><input type="text" name="info" value="<?= e($ev['info'] ?? '') ?>" maxlength="60" placeholder="Ex.: Vagas limitadas" data-prev="info" /></label>
          </div>
          <label class="field"><span class="field__label">Descrição</span>
            <textarea name="descricao" rows="4" maxlength="600" required data-prev="descricao" placeholder="Conte em duas ou três linhas o que vai acontecer."><?= e($ev['descricao']) ?></textarea></label>
          <label class="field"><span class="field__label">Foto (JPG, PNG ou WebP, até 12 MB)</span>
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" id="ev-foto" />
            <span class="hint">Use uma foto deitada (horizontal). Ela é reduzida e otimizada automaticamente.</span></label>
          <?php if ($ev['foto']): ?><label class="check"><input type="checkbox" name="remover_foto" value="1" /> Remover a foto atual e usar a foto padrão dos parreirais</label><?php endif; ?>
          <label class="check"><input type="checkbox" name="publicado" value="1"<?= $ev['publicado'] ? ' checked' : '' ?> /> Publicar no site</label>
          <button class="btn btn--gold" type="submit"><?= $id ? 'Salvar alterações' : 'Salvar evento' ?></button>
        </form>
      </section>

      <aside class="preview" aria-label="Prévia do card no site">
        <p class="field__label">Prévia no site</p>
        <article class="ev ev--preview">
          <div class="ev__img"><img id="pv-foto" src="../<?= e($foto) ?>" alt="" /><span class="ev__date" id="pv-data"><?= $ev['data'] ? e(data_card($ev['data'])) : 'Data' ?></span></div>
          <div class="ev__body">
            <p class="ev__line" id="pv-linha"><?= e($ev['linha'] ?? '') ?></p>
            <h3 class="ev__title" id="pv-titulo"></h3>
            <p class="ev__desc" id="pv-descricao"><?= e($ev['descricao']) ?></p>
            <p class="ev__meta" id="pv-meta"></p>
          </div>
        </article>
      </aside>
    </div>

    <?php if ($id): ?>
      <form method="post" class="danger-zone" onsubmit="return confirm('Excluir este evento? Ele sai do site na hora.');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>" /><input type="hidden" name="acao" value="excluir" />
        <button class="link link--danger" type="submit">Excluir este evento</button>
      </form>
    <?php endif; ?>

    <script>
      // Prévia ao vivo do card, com o mesmo formato do site.
      (() => {
        const f = document.getElementById('ev-form');
        const dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        const $ = (id) => document.getElementById(id);
        const render = () => {
          const t = f.titulo.value.trim() || 'Título do evento';
          const i = t.indexOf(' ');
          const h = $('pv-titulo');
          h.textContent = '';
          const em = document.createElement('em');
          em.textContent = i > 0 ? t.slice(0, i) : t;
          h.append(em, i > 0 ? t.slice(i) : '');
          $('pv-linha').textContent = f.linha.value;
          $('pv-descricao').textContent = f.descricao.value || 'Descrição do evento.';
          $('pv-meta').textContent = [f.horario.value, f.local.value, f.info.value].filter(Boolean).join(' · ');
          if (f.data.value) {
            const [y, m, d] = f.data.value.split('-').map(Number);
            const dt = new Date(y, m - 1, d);
            $('pv-data').textContent = `${dias[dt.getDay()]} · ${d} ${meses[m - 1]} ${y}`;
          }
        };
        f.addEventListener('input', render);
        document.getElementById('ev-foto').addEventListener('change', (e) => {
          const file = e.target.files[0];
          if (file) $('pv-foto').src = URL.createObjectURL(file);
        });
        render();
      })();
    </script>
<?php painel_footer();
