<?php
declare(strict_types=1);

/* Catálogo para revendedores: preço e ordem de cada rótulo, e os dados do PDF. Código, EAN e caixa ficam no editor do produto. */

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/catalogo.php';

$user = require_login();
$pdo = db();
$cfg = catalogo_ajustes();
$erroCfg = '';
$erros = [];
$postados = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'ajustes') {
        $validade = trim((string) ($_POST['validade'] ?? ''));
        $whats = only_digits((string) ($_POST['whatsapp'] ?? ''));
        $email = clean_text($_POST['email'] ?? '', 160);
        $condicoes = trim(preg_replace('/\R{3,}/u', "\n\n", str_replace("\r", '', (string) ($_POST['condicoes'] ?? ''))) ?? '');
        $cfg = [
            'mostrar_precos' => !empty($_POST['mostrar_precos']),
            'validade' => $validade,
            'condicoes' => mb_substr($condicoes, 0, 1200),
            'whatsapp' => $whats,
            'email' => $email,
        ];
        $data = $validade === '' ? null : DateTime::createFromFormat('!Y-m-d', $validade);
        if ($validade !== '' && (!$data || $data->format('Y-m-d') !== $validade)) {
            $erroCfg = 'A data de validade não é válida.';
        } elseif ($whats !== '' && (strlen($whats) < 10 || strlen($whats) > 11)) {
            $erroCfg = 'O WhatsApp precisa ter DDD e número, como (48) 99954-6255.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erroCfg = 'O e-mail de contato não parece válido.';
        } else {
            salvar_ajuste('catalogo', $cfg);
            flash('Dados do PDF salvos.');
            header('Location: catalogo.php');
            exit;
        }
    } elseif ($acao === 'tabela') {
        // Salva a tabela inteira. Os botões de ordem estão no mesmo formulário: salvam e depois movem.
        $linhas = is_array($_POST['p'] ?? null) ? $_POST['p'] : [];
        $novos = [];
        foreach ($linhas as $id => $l) {
            $id = (int) $id;
            if (!$id || !is_array($l)) {
                continue;
            }
            $precoTxt = clean_text($l['preco'] ?? '', 20);
            $preco = preco_de_texto($precoTxt);
            $postados[$id] = ['preco' => $precoTxt, 'ativo' => !empty($l['ativo'])];
            if ($preco === false) {
                $erros[$id] = 'Preço inválido. Escreva como 45,90.';
            }
            $novos[$id] = [$preco === false ? null : $preco, empty($l['ativo']) ? 0 : 1];
        }
        if (!$erros) {
            $up = $pdo->prepare('UPDATE produtos SET preco = ?, ativo = ? WHERE id = ?');
            foreach ($novos as $id => $v) {
                $up->execute([...$v, $id]);
            }
            $mover = (string) ($_POST['mover'] ?? '');
            $ancora = '';
            if (preg_match('/^(\d+):(-1|1)$/', $mover, $m)) {
                catalogo_mover((int) $m[1], (int) $m[2]);
                $ancora = '#prod-' . (int) $m[1];
            } else {
                flash('Tabela do catálogo salva.');
            }
            header('Location: catalogo.php' . $ancora);
            exit;
        }
    }
}

$produtos = catalogo_produtos(false);
$ativos = array_values(array_filter($produtos, fn($p) => $p['ativo']));
$semPreco = count(array_filter($ativos, fn($p) => $p['preco'] === null));

function botao_mover(int $id, int $dir, bool $desativado): string
{
    $rotulo = $dir < 0 ? 'Mover para cima' : 'Mover para baixo';
    $icone = $dir < 0 ? '<path d="m18 15-6-6-6 6"/>' : '<path d="m6 9 6 6 6-6"/>';
    return '<button class="icon-btn" type="submit" name="mover" value="' . $id . ':' . $dir . '" aria-label="' . $rotulo . '" title="' . $rotulo . '"'
        . ($desativado ? ' disabled' : '') . '><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $icone . '</svg></button>';
}

painel_header('Catálogo', 'catalogo', $user);
?>
    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span>Para revendedores · PDF<span aria-hidden="true"></span></p>
        <h1 class="title"><em>Catálogo</em> de produtos</h1>
        <p class="sub"><?= count($ativos) ?> <?= count($ativos) === 1 ? 'rótulo entra' : 'rótulos entram' ?> no PDF, nesta ordem.</p>
      </div>
      <div class="page-top__actions">
        <a class="btn" href="produto.php">+ Adicionar produto</a>
        <a class="btn btn--gold" href="catalogo-pdf.php" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/></svg>
          Gerar PDF
        </a>
      </div>
    </header>

    <?php if ($cfg['mostrar_precos'] && $semPreco): ?>
      <p class="notice"><?= $semPreco ?> <?= $semPreco === 1 ? 'rótulo está' : 'rótulos estão' ?> sem preço: no PDF, <?= $semPreco === 1 ? 'aparece' : 'aparecem' ?> como "consulte".</p>
    <?php endif; ?>

    <?php if ($erros): ?>
      <p class="form-error" role="alert">Nada foi salvo. Corrija <?= count($erros) === 1 ? 'a linha marcada' : 'as ' . count($erros) . ' linhas marcadas' ?> abaixo.</p>
    <?php endif; ?>

    <form method="post" class="cat-form" id="tabela">
      <?= csrf_field() ?><input type="hidden" name="acao" value="tabela" />
      <?php /* Enter num campo usa o primeiro botão do formulário: este, que só salva (e não a seta). */ ?>
      <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true">Salvar tabela</button>
      <div class="cat-list" role="list">
        <div class="cat-row cat-row--head" aria-hidden="true">
          <span>Produto</span><span>Preço de revenda</span><span>No PDF · Editar · Ordem</span>
        </div>
        <?php foreach ($produtos as $i => $p):
            $id = (int) $p['id'];
            $v = $postados[$id] ?? [
                'preco' => preco_br($p['preco'] === null ? null : (int) $p['preco']),
                'ativo' => (bool) $p['ativo'],
            ];
            $erro = $erros[$id] ?? '';
        ?>
          <div class="cat-row<?= $v['ativo'] ? '' : ' is-off' ?><?= $erro ? ' has-error' : '' ?>" role="listitem" id="prod-<?= $id ?>">
            <div class="cat-row__thumb"><?php if ($p['foto']): ?><img src="../<?= e($p['foto']) ?>" alt="" loading="lazy" /><?php endif; ?></div>
            <div class="cat-row__name">
              <strong><?= e($p['nome']) ?></strong>
              <span><?= e($p['linha']) ?><?= $p['volume'] ? ' · ' . e($p['volume']) : '' ?></span>
            </div>
            <label class="cat-field cat-field--preco"><span class="cat-field__label">Preço de revenda</span>
              <span class="cat-field__money"><span aria-hidden="true">R$</span><input type="text" name="p[<?= $id ?>][preco]" value="<?= e($v['preco']) ?>" maxlength="12" inputmode="decimal" autocomplete="off" /></span></label>
            <div class="cat-row__actions">
              <label class="switch" title="Entra no PDF">
                <input type="checkbox" name="p[<?= $id ?>][ativo]" value="1"<?= $v['ativo'] ? ' checked' : '' ?> />
                <span class="switch__ui" aria-hidden="true"></span><span class="sr-only">Mostrar <?= e($p['nome']) ?> no PDF</span>
              </label>
              <a class="btn btn--sm" href="produto.php?id=<?= $id ?>">Editar</a>
              <?= botao_mover($id, -1, $i === 0) ?>
              <?= botao_mover($id, 1, $i === count($produtos) - 1) ?>
            </div>
            <?php if ($erro): ?><p class="cat-row__error" role="alert"><?= e($erro) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="cat-save">
        <p class="hint">Preço por unidade, para revenda. Código, EAN, unidades por caixa, foto e descrição ficam em Editar. As setas e a chave também salvam a tabela.</p>
        <button class="btn btn--gold" type="submit">Salvar tabela</button>
      </div>
    </form>

    <section class="panel cat-cfg">
      <h2 class="panel__title">Dados do PDF</h2>
      <?php if ($erroCfg): ?><p class="form-error" role="alert"><?= e($erroCfg) ?></p><?php endif; ?>
      <form method="post" class="form">
        <?= csrf_field() ?><input type="hidden" name="acao" value="ajustes" />
        <label class="cat-toggle">
          <span class="switch"><input type="checkbox" name="mostrar_precos" value="1"<?= $cfg['mostrar_precos'] ? ' checked' : '' ?> /><span class="switch__ui" aria-hidden="true"></span></span>
          <span><strong>Mostrar preços no PDF</strong><br /><span class="hint">Desligue para gerar um catálogo só com os produtos e códigos, sem valores.</span></span>
        </label>
        <div class="row">
          <label class="field"><span class="field__label">Preços válidos até (opcional)</span>
            <input type="date" name="validade" value="<?= e($cfg['validade']) ?>" /></label>
          <label class="field"><span class="field__label">WhatsApp para pedidos</span>
            <input type="text" name="whatsapp" inputmode="tel" value="<?= e($cfg['whatsapp'] === '' ? '' : format_whatsapp($cfg['whatsapp'])) ?>" placeholder="(48) 99954-6255" /></label>
          <label class="field"><span class="field__label">E-mail para pedidos</span>
            <input type="email" name="email" value="<?= e($cfg['email']) ?>" /></label>
        </div>
        <label class="field"><span class="field__label">Condições comerciais (opcional)</span>
          <textarea name="condicoes" rows="5" maxlength="1200" placeholder="Uma condição por linha. Ex.:&#10;Pedido mínimo de 2 caixas.&#10;Pagamento por PIX ou boleto em 28 dias.&#10;Frete por conta do comprador."><?= e($cfg['condicoes']) ?></textarea>
          <span class="hint">Cada linha vira um item na última página do PDF. Em branco, a seção não aparece.</span></label>
        <div><button class="btn btn--gold" type="submit">Salvar dados do PDF</button></div>
      </form>
    </section>
<?php painel_footer();
