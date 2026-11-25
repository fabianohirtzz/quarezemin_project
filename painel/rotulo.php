<?php
declare(strict_types=1);

/* Ficha de um pedido de rótulo personalizado: dados, arte enviada, situação e notas. */

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/rotulos.php';

$user = require_login();
$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$st = $pdo->prepare('SELECT * FROM rotulos WHERE id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    http_response_code(404);
    flash('Pedido não encontrado.');
    header('Location: rotulos.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $sit = $_POST['situacao'] ?? '';
        if (!array_key_exists($sit, ROTULO_SITUACOES)) {
            $sit = $r['situacao'];
        }
        $notas = clean_text(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['notas'] ?? '')), 5000);
        $pdo->prepare('UPDATE rotulos SET situacao = ?, notas = ?, atualizado_em = ? WHERE id = ?')
            ->execute([$sit, $notas !== '' ? $notas : null, now(), $id]);
        flash('Pedido atualizado.');
        header('Location: rotulo.php?id=' . $id);
        exit;
    }
    if ($acao === 'excluir') {
        rotulo_apagar_arte($r['arte']);
        $pdo->prepare('DELETE FROM rotulos WHERE id = ?')->execute([$id]);
        flash('Pedido de ' . $r['nome'] . ' excluído.');
        header('Location: rotulos.php');
        exit;
    }
}

$primeiro = explode(' ', trim($r['nome']))[0];
$msgWhats = "Olá, {$primeiro}! Aqui é da Vinícola Quarezemin. Recebemos o seu pedido de rótulo personalizado ({$r['produto']}, {$r['quantidade']} unidades).";
$arteOk = rotulo_arte_caminho($r['arte']) !== null;
$arteImagem = $arteOk && preg_match('/\.(png|jpg)$/', (string) $r['arte']);

painel_header('Rótulo · ' . $r['nome'], 'rotulos', $user);
?>
    <a class="back" href="rotulos.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Rótulos personalizados
    </a>

    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span>Recebido em <?= e(data_br($r['criado_em'])) ?><span aria-hidden="true"></span></p>
        <h1 class="title"><?= e($r['nome']) ?></h1>
        <p class="sub"><?= e($r['produto']) ?> · <?= (int) $r['quantidade'] ?> unidades<?= $r['ocasiao'] ? ' · ' . e($r['ocasiao']) : '' ?></p>
      </div>
      <div class="page-top__actions">
        <a class="btn btn--gold" href="<?= e(whatsapp_link($r['whatsapp'], $msgWhats)) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a>
        <a class="btn" href="mailto:<?= e($r['email']) ?>?subject=<?= e(rawurlencode('Seu rótulo personalizado · Vinícola Quarezemin')) ?>">Enviar e-mail</a>
      </div>
    </header>

    <div class="detail">
      <div class="stack">
        <section class="panel">
          <h2 class="panel__title">Dados do pedido</h2>
          <dl class="dl">
            <dt>Nome</dt><dd><?= e($r['nome']) ?></dd>
            <dt>WhatsApp</dt><dd><a href="<?= e(whatsapp_link($r['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e(format_whatsapp($r['whatsapp'])) ?></a></dd>
            <dt>E-mail</dt><dd><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></dd>
            <dt>Empresa / evento</dt><dd><?= e($r['empresa'] ?: '-') ?></dd>
            <dt>Vinho</dt><dd><?= e($r['produto']) ?></dd>
            <dt>Quantidade</dt><dd><?= (int) $r['quantidade'] ?> unidades</dd>
            <dt>Ocasião</dt><dd><?= e($r['ocasiao'] ?: '-') ?></dd>
          </dl>
        </section>

        <section class="panel">
          <h2 class="panel__title">Arte enviada</h2>
          <?php if (!$r['arte']): ?>
            <p class="empty">O cliente não anexou arte. Peça pelo WhatsApp ou e-mail.</p>
          <?php elseif (!$arteOk): ?>
            <p class="empty">O arquivo da arte não está mais no servidor.</p>
          <?php else: ?>
            <?php if ($arteImagem): ?>
              <a class="arte-preview" href="rotulo-arte.php?id=<?= (int) $r['id'] ?>" target="_blank" rel="noopener">
                <img src="rotulo-arte.php?id=<?= (int) $r['id'] ?>" alt="Arte enviada por <?= e($r['nome']) ?>" />
              </a>
            <?php else: ?>
              <p class="arte-file">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></svg>
                <?= e($r['arte_nome'] ?: 'arte.pdf') ?>
              </p>
            <?php endif; ?>
            <div class="arte-actions">
              <a class="btn" href="rotulo-arte.php?id=<?= (int) $r['id'] ?>" target="_blank" rel="noopener">Abrir</a>
              <a class="btn" href="rotulo-arte.php?id=<?= (int) $r['id'] ?>&amp;baixar=1">Baixar arquivo</a>
            </div>
          <?php endif; ?>
        </section>
      </div>

      <section class="panel">
        <h2 class="panel__title">Acompanhamento</h2>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
          <input type="hidden" name="acao" value="salvar" />
          <fieldset class="choices">
            <legend class="field__label">Situação</legend>
            <?php foreach (ROTULO_SITUACOES as $k => $rot): ?>
              <label class="choice">
                <input type="radio" name="situacao" value="<?= e($k) ?>"<?= $r['situacao'] === $k ? ' checked' : '' ?> />
                <span class="choice__box badge--<?= e($k) ?>"><?= e($rot) ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <label class="field">
            <span class="field__label">Notas internas</span>
            <textarea name="notas" rows="6" maxlength="5000" placeholder="Ex.: orçamento enviado dia 26, R$ 38 por garrafa. Arte aprovada pela noiva."><?= e($r['notas'] ?? '') ?></textarea>
          </label>
          <button class="btn btn--gold" type="submit">Salvar</button>
          <p class="hint">Última atualização em <?= e(data_br($r['atualizado_em'])) ?></p>
        </form>
      </section>
    </div>

    <form method="post" class="danger-zone" onsubmit="return confirm('Excluir o pedido de <?= e(addslashes($r['nome'])) ?> e a arte enviada? Essa ação não pode ser desfeita.');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
      <input type="hidden" name="acao" value="excluir" />
      <button class="link link--danger" type="submit">Excluir este pedido</button>
    </form>
<?php painel_footer();
