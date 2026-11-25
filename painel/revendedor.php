<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';

$user = require_login();
$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$st = $pdo->prepare('SELECT * FROM revendedores WHERE id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    http_response_code(404);
    flash('Ficha não encontrada.');
    header('Location: revendedores.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'salvar') {
        $sit = $_POST['situacao'] ?? '';
        if (!array_key_exists($sit, SITUACOES_REVENDEDOR)) {
            $sit = $r['situacao'];
        }
        $notas = clean_text(str_replace(["\r\n", "\r"], "\n", (string) ($_POST['notas'] ?? '')), 5000);
        $pdo->prepare('UPDATE revendedores SET situacao = ?, notas = ?, atualizado_em = ? WHERE id = ?')
            ->execute([$sit, $notas !== '' ? $notas : null, now(), $id]);
        flash('Ficha atualizada.');
        header('Location: revendedor.php?id=' . $id);
        exit;
    }
    if ($acao === 'excluir') {
        $pdo->prepare('DELETE FROM revendedores WHERE id = ?')->execute([$id]);
        flash('Ficha de ' . $r['empresa'] . ' excluída.');
        header('Location: revendedores.php');
        exit;
    }
}

$primeiro = explode(' ', trim($r['nome']))[0];
$msgWhats = "Olá, {$primeiro}! Aqui é da Vinícola Quarezemin. Recebemos a sua ficha de revendedor pelo site e queremos conversar sobre a revenda dos nossos produtos.";

painel_header($r['empresa'], 'revendedores', $user);
?>
    <a class="back" href="revendedores.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Revendedores
    </a>

    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span>Recebida em <?= e(data_br($r['criado_em'])) ?><span aria-hidden="true"></span></p>
        <h1 class="title"><?= e($r['empresa']) ?></h1>
        <p class="sub"><?= e($r['tipo']) ?> · <?= e($r['cidade']) ?>/<?= e($r['uf']) ?></p>
      </div>
      <div class="page-top__actions">
        <a class="btn btn--gold" href="<?= e(whatsapp_link($r['whatsapp'], $msgWhats)) ?>" target="_blank" rel="noopener">Chamar no WhatsApp</a>
        <a class="btn" href="mailto:<?= e($r['email']) ?>">Enviar e-mail</a>
      </div>
    </header>

    <div class="detail">
      <section class="panel">
        <h2 class="panel__title">Dados da ficha</h2>
        <dl class="dl">
          <dt>Responsável</dt><dd><?= e($r['nome']) ?></dd>
          <dt>Empresa</dt><dd><?= e($r['empresa']) ?></dd>
          <dt>CNPJ</dt><dd><?= e($r['cnpj'] ?: '-') ?></dd>
          <dt>WhatsApp</dt><dd><a href="<?= e(whatsapp_link($r['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e(format_whatsapp($r['whatsapp'])) ?></a></dd>
          <dt>E-mail</dt><dd><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></dd>
          <dt>Cidade</dt><dd><?= e($r['cidade']) ?>/<?= e($r['uf']) ?></dd>
          <dt>Tipo</dt><dd><?= e($r['tipo']) ?></dd>
          <dt>Mensagem</dt><dd class="dl__text"><?= $r['mensagem'] ? nl2br(e($r['mensagem'])) : '-' ?></dd>
        </dl>
      </section>

      <section class="panel">
        <h2 class="panel__title">Acompanhamento</h2>
        <form method="post" class="form">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
          <input type="hidden" name="acao" value="salvar" />
          <fieldset class="choices">
            <legend class="field__label">Situação</legend>
            <?php foreach (SITUACOES_REVENDEDOR as $k => $rot): ?>
              <label class="choice">
                <input type="radio" name="situacao" value="<?= e($k) ?>"<?= $r['situacao'] === $k ? ' checked' : '' ?> />
                <span class="choice__box badge--<?= e($k) ?>"><?= e($rot) ?></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
          <label class="field">
            <span class="field__label">Notas internas</span>
            <textarea name="notas" rows="6" maxlength="5000" placeholder="Ex.: enviado catálogo dia 25, retornar na próxima semana."><?= e($r['notas'] ?? '') ?></textarea>
          </label>
          <button class="btn btn--gold" type="submit">Salvar</button>
          <p class="hint">Última atualização em <?= e(data_br($r['atualizado_em'])) ?></p>
        </form>
      </section>
    </div>

    <form method="post" class="danger-zone" onsubmit="return confirm('Excluir a ficha de <?= e(addslashes($r['empresa'])) ?>? Essa ação não pode ser desfeita.');">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>" />
      <input type="hidden" name="acao" value="excluir" />
      <button class="link link--danger" type="submit">Excluir esta ficha</button>
    </form>
<?php painel_footer();
