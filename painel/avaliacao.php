<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/avaliacoes.php';

$user = require_login();
$pdo = db();
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$erro = '';

$a = ['id' => 0, 'autor' => '', 'texto' => '', 'estrelas' => 5, 'ativo' => 1];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM avaliacoes WHERE id = ?');
    $st->execute([$id]);
    $a = $st->fetch();
    if (!$a) {
        flash('Avaliação não encontrada.');
        header('Location: avaliacoes.php');
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $autor = clean_text($_POST['autor'] ?? '', 80);
    // Aspas no começo/fim são colocadas pelo site; remove se vierem coladas do Google.
    $texto = preg_replace('/\s*\R\s*/u', ' ', (string) ($_POST['texto'] ?? '')) ?? '';
    $texto = trim(clean_text($texto, 600), " \"“”'");
    $estrelas = (int) ($_POST['estrelas'] ?? 5);
    $a = array_merge($a, ['autor' => $autor, 'texto' => $texto, 'estrelas' => $estrelas]);

    if ($autor === '') {
        $erro = 'Informe o nome de quem avaliou, como aparece no Google.';
    } elseif (mb_strlen($texto) < 10) {
        $erro = 'Cole o texto da avaliação.';
    } elseif ($estrelas < 1 || $estrelas > 5) {
        $erro = 'Escolha de 1 a 5 estrelas.';
    } else {
        if ($id) {
            $pdo->prepare('UPDATE avaliacoes SET autor = ?, texto = ?, estrelas = ? WHERE id = ?')->execute([$autor, $texto, $estrelas, $id]);
            flash('Avaliação atualizada.');
        } else {
            avaliacoes_semear();
            $ordem = (int) $pdo->query('SELECT COALESCE(MIN(ordem), 10) FROM avaliacoes')->fetchColumn() - 10;
            $pdo->prepare('INSERT INTO avaliacoes (autor, texto, estrelas, ordem, ativo, criado_em) VALUES (?, ?, ?, ?, 1, ?)')
                ->execute([$autor, $texto, $estrelas, $ordem, now()]);
            avaliacoes_renumerar();
            flash('Avaliação adicionada. Ela aparece em primeiro lugar no site.');
        }
        header('Location: avaliacoes.php');
        exit;
    }
}

painel_header($id ? 'Editar avaliação' : 'Adicionar avaliação', 'avaliacoes', $user);
?>
    <a class="back" href="avaliacoes.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Avaliações
    </a>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span>Copiada do Google<span aria-hidden="true"></span></p>
      <h1 class="title"><em><?= $id ? 'Editar' : 'Adicionar' ?></em> avaliação</h1>
    </header>

    <div class="detail detail--editor">
      <section class="panel">
        <?php if ($erro): ?><p class="form-error" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" class="form" id="aval-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $id ?>" />
          <label class="field"><span class="field__label">Nome de quem avaliou</span>
            <input type="text" name="autor" id="aval-autor" maxlength="80" value="<?= e($a['autor']) ?>" placeholder="Ex.: Heloisa Pires" required /></label>
          <label class="field"><span class="field__label">Texto da avaliação</span>
            <textarea name="texto" id="aval-texto" rows="6" maxlength="600" required placeholder="Cole aqui o comentário, do jeito que está no Google."><?= e($a['texto']) ?></textarea>
            <span class="hint">Pode resumir um comentário longo, sem mudar o sentido. As aspas o site coloca sozinho. <span id="aval-conta"></span></span></label>
          <fieldset class="choices">
            <legend class="field__label">Estrelas</legend>
            <?php for ($n = 5; $n >= 1; $n--): ?>
              <label class="choice"><input type="radio" name="estrelas" value="<?= $n ?>"<?= (int) $a['estrelas'] === $n ? ' checked' : '' ?> /><span class="choice__box"><?= $n ?> <?= $n === 1 ? 'estrela' : 'estrelas' ?></span></label>
            <?php endfor; ?>
          </fieldset>
          <div><button class="btn btn--gold" type="submit"><?= $id ? 'Salvar alterações' : 'Adicionar avaliação' ?></button></div>
        </form>
      </section>

      <aside class="preview" aria-label="Prévia do card">
        <p class="field__label">Prévia na página inicial</p>
        <div class="aval-card">
          <blockquote id="pv-texto">“<?= e($a['texto'] ?: 'O texto da avaliação aparece aqui.') ?>”</blockquote>
          <p class="aval-card__author"><span id="pv-autor"><?= e($a['autor'] ?: 'Nome de quem avaliou') ?></span><small id="pv-estrelas">Google · <?= (int) $a['estrelas'] ?> <?= (int) $a['estrelas'] === 1 ? 'estrela' : 'estrelas' ?></small></p>
        </div>
      </aside>
    </div>
    <script>
      (() => {
        const autor = document.getElementById('aval-autor');
        const texto = document.getElementById('aval-texto');
        const conta = document.getElementById('aval-conta');
        const limpa = (v) => v.trim().replace(/^["“”']+|["“”']+$/g, '');
        const atualiza = () => {
          document.getElementById('pv-autor').textContent = autor.value.trim() || 'Nome de quem avaliou';
          document.getElementById('pv-texto').textContent = '“' + (limpa(texto.value) || 'O texto da avaliação aparece aqui.') + '”';
          const n = texto.value.trim().length;
          conta.textContent = n > 260 ? `(${n} caracteres: textos até uns 250 ficam mais bonitos no card)` : '';
        };
        autor.addEventListener('input', atualiza);
        texto.addEventListener('input', atualiza);
        document.querySelectorAll('input[name=estrelas]').forEach((r) => r.addEventListener('change', () => {
          document.getElementById('pv-estrelas').textContent = `Google · ${r.value} ${r.value === '1' ? 'estrela' : 'estrelas'}`;
        }));
        atualiza();
      })();
    </script>
<?php painel_footer();
