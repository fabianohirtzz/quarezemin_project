<?php
declare(strict_types=1);

/* Pedidos de rótulo personalizado recebidos pelo site. */

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/rotulos.php';

$user = require_login();
$pdo = db();

$f = [
    'q' => clean_text($_GET['q'] ?? '', 100),
    'situacao' => array_key_exists($_GET['situacao'] ?? '', ROTULO_SITUACOES) ? $_GET['situacao'] : '',
];

$where = [];
$args = [];
if ($f['q'] !== '') {
    $where[] = "(nome LIKE ? ESCAPE '!' OR empresa LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR produto LIKE ? ESCAPE '!' OR ocasiao LIKE ? ESCAPE '!' OR whatsapp LIKE ? ESCAPE '!')";
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $f['q']) . '%';
    $digits = only_digits($f['q']);
    array_push($args, $like, $like, $like, $like, $like, $digits !== '' ? '%' . $digits . '%' : $like);
}
if ($f['situacao'] !== '') {
    $where[] = 'situacao = ?';
    $args[] = $f['situacao'];
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$porPagina = 25;
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$cnt = $pdo->prepare("SELECT COUNT(*) FROM rotulos $sqlWhere");
$cnt->execute($args);
$total = (int) $cnt->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);

$st = $pdo->prepare("SELECT id, nome, empresa, whatsapp, produto, quantidade, ocasiao, arte, situacao, criado_em FROM rotulos $sqlWhere ORDER BY criado_em DESC, id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina));
$st->execute($args);
$linhas = $st->fetchAll();

$novos = (int) $pdo->query("SELECT COUNT(*) FROM rotulos WHERE situacao = 'novo'")->fetchColumn();

$qs = fn(array $extra = []) => http_build_query(array_filter(array_merge($f, $extra), fn($v) => $v !== '' && $v !== null));

painel_header('Rótulos personalizados', 'rotulos', $user);
?>
    <header class="page-top">
      <p class="eyebrow"><span aria-hidden="true"></span>Personalize seu Rótulo<span aria-hidden="true"></span></p>
      <h1 class="title"><em>Pedidos</em> de rótulo</h1>
      <p class="sub">Cada pedido feito no site chega aqui, com a arte que o cliente enviou, e avisa por e-mail.<?php if ($novos): ?> <strong><?= $novos ?> <?= $novos === 1 ? 'pedido novo' : 'pedidos novos' ?></strong> esperando resposta.<?php endif; ?></p>
    </header>

    <form class="filters" method="get" role="search">
      <label class="field field--grow">
        <span class="field__label">Buscar</span>
        <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Nome, empresa, vinho, ocasião, telefone..." />
      </label>
      <label class="field">
        <span class="field__label">Situação</span>
        <select name="situacao">
          <option value="">Todas</option>
          <?php foreach (ROTULO_SITUACOES as $k => $rot): ?>
            <option value="<?= e($k) ?>"<?= $f['situacao'] === $k ? ' selected' : '' ?>><?= e($rot) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="filters__actions">
        <button class="btn btn--gold" type="submit">Filtrar</button>
        <?php if ($f['q'] !== '' || $f['situacao'] !== ''): ?><a class="link" href="rotulos.php">Limpar</a><?php endif; ?>
      </div>
    </form>

    <p class="count"><?= $total ?> <?= $total === 1 ? 'pedido encontrado' : 'pedidos encontrados' ?></p>

    <?php if (!$linhas): ?>
      <p class="empty"><?= $f['q'] !== '' || $f['situacao'] !== '' ? 'Nenhum pedido com esses filtros.' : 'Nenhum pedido de rótulo ainda. Eles aparecem aqui assim que alguém preencher o formulário do site.' ?></p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Recebido</th><th>Cliente</th><th>Vinho</th><th>Qtd.</th><th>Arte</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr>
          </thead>
          <tbody>
            <?php foreach ($linhas as $r): ?>
              <tr>
                <td data-label="Recebido"><?= e(data_br($r['criado_em'])) ?></td>
                <td data-label="Cliente"><a class="table__main" href="rotulo.php?id=<?= (int) $r['id'] ?>"><strong><?= e($r['nome']) ?></strong><span><?= e($r['empresa'] ?: ($r['ocasiao'] ?: '')) ?></span></a></td>
                <td data-label="Vinho"><?= e($r['produto']) ?></td>
                <td data-label="Qtd."><?= (int) $r['quantidade'] ?> un.</td>
                <td data-label="Arte"><?= $r['arte'] ? 'Enviada' : 'Sem arte' ?></td>
                <td data-label="Situação"><?= badge_rotulo($r['situacao']) ?></td>
                <td class="table__actions">
                  <a class="icon-btn" href="<?= e(whatsapp_link($r['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="Chamar <?= e($r['nome']) ?> no WhatsApp" title="WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.7-.7 1.9-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3zM12 21.8h0a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4a9.8 9.8 0 0 1-1.5-5.3C2.2 6.5 6.6 2.2 12 2.2c2.6 0 5.1 1 7 2.9a9.8 9.8 0 0 1 2.9 7c0 5.4-4.4 9.7-9.9 9.7z"/></svg>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($paginas > 1): ?>
        <nav class="pager" aria-label="Páginas">
          <?php if ($pagina > 1): ?><a class="btn" href="?<?= e($qs(['p' => $pagina - 1])) ?>">Anterior</a><?php endif; ?>
          <span>Página <?= $pagina ?> de <?= $paginas ?></span>
          <?php if ($pagina < $paginas): ?><a class="btn" href="?<?= e($qs(['p' => $pagina + 1])) ?>">Próxima</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
<?php painel_footer();
