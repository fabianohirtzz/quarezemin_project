<?php
declare(strict_types=1);

require __DIR__ . '/../app/painel-layout.php';

$user = require_login();
$pdo = db();

$f = [
    'q' => clean_text($_GET['q'] ?? '', 100),
    'situacao' => array_key_exists($_GET['situacao'] ?? '', SITUACOES_REVENDEDOR) ? $_GET['situacao'] : '',
    'cidade' => clean_text($_GET['cidade'] ?? '', 100),
];

$where = [];
$args = [];
if ($f['q'] !== '') {
    $where[] = "(nome LIKE ? ESCAPE '!' OR empresa LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!' OR cidade LIKE ? ESCAPE '!' OR cnpj LIKE ? ESCAPE '!' OR whatsapp LIKE ? ESCAPE '!')";
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $f['q']) . '%';
    $digits = only_digits($f['q']);
    array_push($args, $like, $like, $like, $like, $like, $digits !== '' ? '%' . $digits . '%' : $like);
}
if ($f['situacao'] !== '') {
    $where[] = 'situacao = ?';
    $args[] = $f['situacao'];
}
if ($f['cidade'] !== '') {
    // Filtro no formato "Cidade/UF"
    [$cid, $uf] = array_pad(explode('/', $f['cidade'], 2), 2, '');
    $where[] = 'cidade = ? AND uf = ?';
    array_push($args, $cid, $uf);
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ---------- Exportar planilha (CSV que abre no Excel) ---------- */
if (($_GET['exportar'] ?? '') === 'csv') {
    $st = $pdo->prepare("SELECT * FROM revendedores $sqlWhere ORDER BY criado_em DESC, id DESC");
    $st->execute($args);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="revendedores-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para o Excel reconhecer acentos
    $cols = ['Recebida em', 'Situação', 'Empresa', 'Responsável', 'CNPJ', 'WhatsApp', 'E-mail', 'Cidade', 'UF', 'Tipo', 'Mensagem', 'Notas internas'];
    fputcsv($out, $cols, ';', '"', '');
    while ($r = $st->fetch()) {
        $linha = [
            data_br($r['criado_em']), SITUACOES_REVENDEDOR[$r['situacao']] ?? $r['situacao'], $r['empresa'], $r['nome'],
            $r['cnpj'] ?? '', format_whatsapp($r['whatsapp']), $r['email'], $r['cidade'], $r['uf'], $r['tipo'],
            $r['mensagem'] ?? '', $r['notas'] ?? '',
        ];
        // Evita que o Excel interprete células como fórmula
        $linha = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : $v, $linha);
        fputcsv($out, $linha, ';', '"', '');
    }
    fclose($out);
    exit;
}

/* ---------- Listagem ---------- */
$porPagina = 25;
$pagina = max(1, (int) ($_GET['p'] ?? 1));
$cnt = $pdo->prepare("SELECT COUNT(*) FROM revendedores $sqlWhere");
$cnt->execute($args);
$total = (int) $cnt->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);

$st = $pdo->prepare("SELECT id, empresa, nome, whatsapp, cidade, uf, tipo, situacao, criado_em FROM revendedores $sqlWhere ORDER BY criado_em DESC, id DESC LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina));
$st->execute($args);
$linhas = $st->fetchAll();

$cidades = $pdo->query('SELECT cidade, uf, COUNT(*) AS n FROM revendedores GROUP BY cidade, uf ORDER BY cidade')->fetchAll();

$qs = fn(array $extra = []) => http_build_query(array_filter(array_merge($f, $extra), fn($v) => $v !== '' && $v !== null));

painel_header('Revendedores', 'revendedores', $user);
?>
    <header class="page-top page-top--row">
      <div>
        <p class="eyebrow"><span aria-hidden="true"></span>Seja um Revendedor<span aria-hidden="true"></span></p>
        <h1 class="title"><em>Fichas</em> de revendedores</h1>
      </div>
      <a class="btn" href="revendedores.php?<?= e($qs(['exportar' => 'csv'])) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exportar planilha
      </a>
    </header>

    <form class="filters" method="get" role="search">
      <label class="field field--grow">
        <span class="field__label">Buscar</span>
        <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Empresa, nome, e-mail, CNPJ, telefone..." />
      </label>
      <label class="field">
        <span class="field__label">Cidade</span>
        <select name="cidade">
          <option value="">Todas</option>
          <?php foreach ($cidades as $c): $v = $c['cidade'] . '/' . $c['uf']; ?>
            <option value="<?= e($v) ?>"<?= $f['cidade'] === $v ? ' selected' : '' ?>><?= e($v) ?> (<?= (int) $c['n'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field">
        <span class="field__label">Situação</span>
        <select name="situacao">
          <option value="">Todas</option>
          <?php foreach (SITUACOES_REVENDEDOR as $k => $rot): ?>
            <option value="<?= e($k) ?>"<?= $f['situacao'] === $k ? ' selected' : '' ?>><?= e($rot) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="filters__actions">
        <button class="btn btn--gold" type="submit">Filtrar</button>
        <?php if ($f['q'] !== '' || $f['situacao'] !== '' || $f['cidade'] !== ''): ?><a class="link" href="revendedores.php">Limpar</a><?php endif; ?>
      </div>
    </form>

    <p class="count"><?= $total ?> <?= $total === 1 ? 'ficha encontrada' : 'fichas encontradas' ?></p>

    <?php if (!$linhas): ?>
      <p class="empty">Nenhuma ficha com esses filtros.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Recebida</th><th>Empresa</th><th>Cidade</th><th>Tipo</th><th>Situação</th><th><span class="sr-only">Ações</span></th></tr>
          </thead>
          <tbody>
            <?php foreach ($linhas as $r): ?>
              <tr>
                <td data-label="Recebida"><?= e(data_br($r['criado_em'])) ?></td>
                <td data-label="Empresa"><a class="table__main" href="revendedor.php?id=<?= (int) $r['id'] ?>"><strong><?= e($r['empresa']) ?></strong><span><?= e($r['nome']) ?></span></a></td>
                <td data-label="Cidade"><?= e($r['cidade']) ?>/<?= e($r['uf']) ?></td>
                <td data-label="Tipo"><?= e($r['tipo']) ?></td>
                <td data-label="Situação"><?= badge_situacao($r['situacao']) ?></td>
                <td class="table__actions">
                  <a class="icon-btn" href="<?= e(whatsapp_link($r['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="Chamar <?= e($r['empresa']) ?> no WhatsApp" title="WhatsApp">
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
