<?php
declare(strict_types=1);

/* Editor de um rótulo do catálogo. Muda só o PDF; as páginas de produto do site não mudam. */

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/catalogo.php';
require_once __DIR__ . '/../app/imagens.php';

$user = require_login();
$pdo = db();
$id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$erro = '';

$p = ['id' => 0, 'nome' => '', 'linha' => 'Mesa', 'estilo' => '', 'uva' => '', 'volume' => '750ml', 'alcool' => '', 'servir' => '',
    'descricao' => '', 'premio' => '', 'foto' => '', 'codigo' => '', 'ean' => '', 'caixa' => null, 'preco' => null, 'ativo' => 1];
if ($id) {
    catalogo_semear();
    $p = catalogo_produto($id);
    if (!$p) {
        flash('Produto não encontrado.');
        header('Location: catalogo.php');
        exit;
    }
}
$precoTxt = preco_br($p['preco'] === null ? null : (int) $p['preco']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $erro = 'A foto é grande demais. Envie uma imagem de até 12 MB.';
    } else {
        csrf_check();
        $acao = $_POST['acao'] ?? 'salvar';

        if ($acao === 'excluir' && $id) {
            $pdo->prepare('DELETE FROM produtos WHERE id = ?')->execute([$id]);
            apagar_upload($p['foto']);
            flash($p['nome'] . ' saiu do catálogo.');
            header('Location: catalogo.php');
            exit;
        }

        $linha = clean_text($_POST['linha'] ?? '', 40);
        $novo = [
            'nome' => clean_text($_POST['nome'] ?? '', 80),
            'linha' => $linha,
            'estilo' => clean_text($_POST['estilo'] ?? '', 80),
            'uva' => clean_text($_POST['uva'] ?? '', 60),
            'volume' => clean_text($_POST['volume'] ?? '', 20),
            'alcool' => clean_text($_POST['alcool'] ?? '', 20),
            'servir' => clean_text($_POST['servir'] ?? '', 20),
            'descricao' => clean_text(preg_replace('/\s*\R\s*/u', ' ', (string) ($_POST['descricao'] ?? '')) ?? '', 320),
            'premio' => clean_text($_POST['premio'] ?? '', 100),
            'codigo' => clean_text($_POST['codigo'] ?? '', 20),
            'ean' => only_digits((string) ($_POST['ean'] ?? '')),
            'caixa' => only_digits((string) ($_POST['caixa'] ?? '')),
        ];
        $precoTxt = clean_text($_POST['preco'] ?? '', 20);
        $preco = preco_de_texto($precoTxt);
        $p = array_merge($p, $novo);

        if ($novo['nome'] === '') {
            $erro = 'Informe o nome do produto.';
        } elseif (!in_array($linha, CATALOGO_LINHAS, true)) {
            $erro = 'Escolha a linha do produto.';
        } elseif (mb_strlen($novo['descricao']) < 20) {
            $erro = 'Escreva uma descrição curta, de uma ou duas frases.';
        } elseif ($novo['codigo'] !== '' && !codigo_valido($novo['codigo'])) {
            $erro = 'Código: use letras, números, ponto, barra ou hífen (até 20).';
        } elseif ($novo['ean'] !== '' && !ean_valido($novo['ean'])) {
            $erro = 'EAN inválido: confira os números (o último dígito não confere).';
        } elseif ($novo['caixa'] !== '' && ((int) $novo['caixa'] < 1 || (int) $novo['caixa'] > 99)) {
            $erro = 'Unidades por caixa: entre 1 e 99.';
        } elseif ($preco === false) {
            $erro = 'Preço inválido. Escreva como 45,90.';
        } elseif (!$id && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $erro = 'Envie a foto da garrafa.';
        }

        $fotoNova = null;
        if (!$erro && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $fotoNova = salvar_imagem_upload($_FILES['foto'], 'produtos', 800, 300, 300);
            } catch (RuntimeException $ex) {
                $erro = $ex->getMessage();
            }
        }

        if (!$erro) {
            $foto = $fotoNova ?: $p['foto'];
            $valores = [$novo['nome'], $linha, $novo['estilo'], $novo['uva'], $novo['volume'], $novo['alcool'], $novo['servir'],
                $novo['descricao'], $novo['premio'], $foto, $novo['codigo'], $novo['ean'],
                $novo['caixa'] === '' ? null : (int) $novo['caixa'], $preco];
            if ($id) {
                $pdo->prepare('UPDATE produtos SET nome = ?, linha = ?, estilo = ?, uva = ?, volume = ?, alcool = ?, servir = ?, descricao = ?, premio = ?, foto = ?, codigo = ?, ean = ?, caixa = ?, preco = ? WHERE id = ?')
                    ->execute([...$valores, $id]);
                if ($fotoNova) {
                    apagar_upload($p['foto']);
                }
                flash($novo['nome'] . ' atualizado.');
            } else {
                catalogo_semear();
                $ordem = (int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) FROM produtos')->fetchColumn() + 10;
                $pdo->prepare('INSERT INTO produtos (nome, linha, estilo, uva, volume, alcool, servir, descricao, premio, foto, codigo, ean, caixa, preco, ordem, ativo, criado_em)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)')->execute([...$valores, $ordem, now()]);
                flash($novo['nome'] . ' entrou no catálogo, no fim da lista. Use as setas para mudar a posição.');
            }
            header('Location: catalogo.php');
            exit;
        }
        if ($fotoNova) {
            apagar_upload($fotoNova);
        }
    }
}

painel_header($id ? 'Editar produto' : 'Adicionar produto', 'catalogo', $user);
?>
    <a class="back" href="catalogo.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Catálogo
    </a>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span>Catálogo para revendedores<span aria-hidden="true"></span></p>
      <h1 class="title"><em><?= $id ? 'Editar' : 'Adicionar' ?></em> <?= $id ? e($p['nome']) : 'produto' ?></h1>
      <p class="sub">Muda só o catálogo em PDF. As páginas de produto do site continuam como estão.</p>
    </header>

    <div class="detail detail--editor">
      <section class="panel">
        <?php if ($erro): ?><p class="form-error" role="alert"><?= e($erro) ?></p><?php endif; ?>
        <form method="post" class="form" enctype="multipart/form-data" id="prod-form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $id ?>" />
          <div class="row">
            <label class="field"><span class="field__label">Nome</span>
              <input type="text" name="nome" maxlength="80" value="<?= e($p['nome']) ?>" placeholder="Ex.: Margherita Branco Seco" required /></label>
            <label class="field"><span class="field__label">Linha</span>
              <select name="linha">
                <?php foreach (CATALOGO_LINHAS as $l): ?><option<?= $l === $p['linha'] ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
              </select></label>
          </div>
          <label class="field"><span class="field__label">Estilo</span>
            <input type="text" name="estilo" maxlength="80" value="<?= e($p['estilo']) ?>" placeholder="Ex.: 100% Goethe · Seco" /></label>
          <div class="row">
            <label class="field"><span class="field__label">Uva</span>
              <input type="text" name="uva" maxlength="60" value="<?= e($p['uva']) ?>" placeholder="Goethe" /></label>
            <label class="field field--sm"><span class="field__label">Volume</span>
              <input type="text" name="volume" maxlength="20" value="<?= e($p['volume']) ?>" placeholder="750ml" /></label>
            <label class="field field--sm"><span class="field__label">Álcool</span>
              <input type="text" name="alcool" maxlength="20" value="<?= e($p['alcool']) ?>" placeholder="11,5%" /></label>
            <label class="field field--sm"><span class="field__label">Servir a</span>
              <input type="text" name="servir" maxlength="20" value="<?= e($p['servir']) ?>" placeholder="6 a 9°C" /></label>
          </div>
          <label class="field"><span class="field__label">Descrição</span>
            <textarea name="descricao" rows="3" maxlength="320" required placeholder="Cor, aroma e paladar, em uma ou duas frases."><?= e($p['descricao']) ?></textarea></label>
          <label class="field"><span class="field__label">Prêmio (opcional)</span>
            <input type="text" name="premio" maxlength="100" value="<?= e($p['premio']) ?>" placeholder="Ex.: Medalha de Prata · Brasil Wine Cup 2026" /></label>
          <div class="row">
            <label class="field"><span class="field__label">Código</span>
              <input type="text" name="codigo" maxlength="20" value="<?= e($p['codigo']) ?>" spellcheck="false" /></label>
            <label class="field"><span class="field__label">EAN (código de barras)</span>
              <input type="text" name="ean" maxlength="14" inputmode="numeric" value="<?= e($p['ean']) ?>" /></label>
            <label class="field field--sm"><span class="field__label">Unid. por caixa</span>
              <input type="text" name="caixa" maxlength="2" inputmode="numeric" value="<?= e($p['caixa'] === null ? '' : (string) $p['caixa']) ?>" placeholder="6" /></label>
            <label class="field field--sm"><span class="field__label">Preço revenda (R$)</span>
              <input type="text" name="preco" maxlength="12" inputmode="decimal" value="<?= e($precoTxt) ?>" placeholder="0,00" /></label>
          </div>
          <label class="field"><span class="field__label">Foto da garrafa<?= $id ? ' (envie só para trocar)' : '' ?></span>
            <input type="file" name="foto" accept="image/png,image/webp,image/jpeg" id="prod-foto" />
            <span class="hint">De preferência PNG com fundo transparente, garrafa em pé e centralizada, como as do site.</span></label>
          <div><button class="btn btn--gold" type="submit"><?= $id ? 'Salvar alterações' : 'Adicionar ao catálogo' ?></button></div>
        </form>
      </section>

      <aside class="preview" aria-label="Foto da garrafa">
        <p class="field__label">Garrafa</p>
        <div class="prod-preview"><img id="pv-foto" src="<?= $p['foto'] ? '../' . e($p['foto']) : '' ?>" alt=""<?= $p['foto'] ? '' : ' hidden' ?> /></div>
      </aside>
    </div>

    <?php if ($id): ?>
      <form method="post" class="danger-zone" onsubmit="return confirm('Tirar este produto do catálogo? Para só esconder do PDF, use a chave na lista.');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $id ?>" /><input type="hidden" name="acao" value="excluir" />
        <button class="link link--danger" type="submit">Excluir este produto do catálogo</button>
      </form>
    <?php endif; ?>
    <script>
      document.getElementById('prod-foto').addEventListener('change', (e) => {
        const file = e.target.files[0];
        const img = document.getElementById('pv-foto');
        if (file) { img.src = URL.createObjectURL(file); img.hidden = false; }
      });
    </script>
<?php painel_footer();
