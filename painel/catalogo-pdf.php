<?php
declare(strict_types=1);

/*
 * Catálogo para revendedores em A4, pronto para "Salvar como PDF".
 * Peça pública: usa a identidade completa do site (Cormorant, Jost, dourado e vinho).
 */

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/catalogo.php';

require_login();
$cfg = catalogo_ajustes();
$produtos = catalogo_produtos(true);
$comPreco = $cfg['mostrar_precos'] && (bool) array_filter($produtos, fn($p) => $p['preco'] !== null);
$temCaixa = (bool) array_filter($produtos, fn($p) => $p['caixa'] !== null);
$temEan = (bool) array_filter($produtos, fn($p) => $p['ean'] !== '');
$temCodigo = (bool) array_filter($produtos, fn($p) => $p['codigo'] !== '');

$meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$hoje = new DateTimeImmutable('today');
$edicao = ucfirst($meses[(int) $hoje->format('n') - 1]) . ' de ' . $hoje->format('Y');
$validade = '';
if ($cfg['validade'] !== '' && ($d = DateTimeImmutable::createFromFormat('!Y-m-d', $cfg['validade']))) {
    $validade = $d->format('d/m/Y');
}
$condicoes = array_values(array_filter(array_map('trim', explode("\n", $cfg['condicoes']))));
$whatsLink = $cfg['whatsapp'] !== ''
    ? 'https://wa.me/55' . $cfg['whatsapp'] . '?text=' . rawurlencode('Olá! Recebi o catálogo da Quarezemin e quero fazer um pedido.')
    : '';
$site = (string) config('site_url');
$site = str_starts_with($site, 'https://') ? preg_replace('#^https://(www\.)?#', '', rtrim($site, '/')) : '';
$arquivo = 'Catalogo-Quarezemin-' . $hoje->format('Y-m');

// Garrafas da capa: uma de cada linha, até cinco, com a do meio em destaque.
$capa = [];
foreach ($produtos as $p) {
    if ($p['foto'] && !isset($capa[$p['linha']])) {
        $capa[$p['linha']] = $p;
    }
}
$capa = array_slice(array_values($capa), 0, 5);

$paginasProdutos = array_chunk($produtos, 4);
$paginasTabela = array_chunk($produtos, 12) ?: [[]];
$pagina = 1;

function topo_folha(string $edicao): string
{
    return '<header class="folha__topo"><img src="../imagens/logo-quarezemin.png" alt="Vinhos Quarezemin" />'
        . '<span>Catálogo para revendedores · ' . e($edicao) . '</span></header>';
}

function pe_folha(int $n): string
{
    return '<footer class="folha__pe"><span>Vinhos Quarezemin · Içara · SC</span><span>' . sprintf('%02d', $n) . '</span></footer>';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= e($arquivo) ?></title>
  <link rel="icon" type="image/png" href="../imagens/logo-quarezemin.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/catalogo.css" />
</head>
<body>
  <div class="toolbar">
    <a class="toolbar__back" href="catalogo.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Voltar ao painel
    </a>
    <button type="button" class="toolbar__btn" id="salvar-pdf">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Salvar PDF
    </button>
  </div>
  <div class="info">
    <p><strong>Como salvar:</strong> clique em "Salvar PDF" e, na janela que abrir, escolha <strong>Destino: Salvar como PDF</strong>. Se a capa sair branca, marque "Gráficos de plano de fundo" em "Mais definições".</p>
    <p>O arquivo fica pronto para enviar pelo WhatsApp ou e-mail. <?= count($produtos) ?> <?= count($produtos) === 1 ? 'rótulo' : 'rótulos' ?><?= $comPreco ? ', com preços' : ', sem preços' ?>.</p>
  </div>

  <main class="catalogo">
    <!-- Capa -->
    <section class="folha folha--capa" aria-label="Capa">
      <img class="capa__foto" src="../imagens/parreirais-campo.webp" alt="" />
      <div class="capa__atmosfera" aria-hidden="true"></div>
      <div class="capa__moldura" aria-hidden="true"></div>
      <p class="capa__rail" aria-hidden="true">Lat. 28°42′S — Long. 49°18′W</p>
      <img class="capa__logo" src="../imagens/logo-quarezemin.png" alt="Vinhos Quarezemin" />
      <div class="capa__centro">
        <p class="eyebrow eyebrow--claro"><span aria-hidden="true"></span>Catálogo para revendedores<span aria-hidden="true"></span></p>
        <h1 class="capa__titulo"><em>Do parreiral</em><br />à sua taça</h1>
        <p class="capa__sub">Vinhos, espumantes e suco de uva da D.O. Vales da Uva Goethe, direto da cantina em Içara, Santa Catarina.</p>
      </div>
      <?php if ($capa): ?>
        <div class="capa__garrafas" aria-hidden="true">
          <?php foreach ($capa as $i => $p): ?>
            <?php $dist = abs($i - (count($capa) - 1) / 2); ?>
            <img src="../<?= e($p['foto']) ?>" alt="" style="--h: <?= 44 - $dist * 6 ?>cqw; z-index: <?= 10 - (int) ceil($dist) ?>" />
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <dl class="capa__pe">
        <div><dt>Edição</dt><dd><?= e($edicao) ?></dd></div>
        <div><dt>Rótulos</dt><dd><?= count($produtos) ?></dd></div>
        <div><dt>Origem</dt><dd>D.O. Vales da Uva Goethe</dd></div>
      </dl>
    </section>

    <!-- A cantina -->
    <section class="folha" aria-label="A cantina">
      <?= topo_folha($edicao) ?>
      <div class="cantina">
        <figure class="cantina__foto"><img src="../imagens/barris-cantina.webp" alt="Barris na cantina de pedra" /></figure>
        <div class="cantina__cabeca">
          <p class="eyebrow"><span aria-hidden="true"></span>Desde 2002<span aria-hidden="true"></span></p>
          <h2 class="titulo"><em>Direto</em> de quem produz</h2>
        </div>
        <div class="cantina__texto">
          <p class="cantina__lede">Das regiões de Toscana e Vêneto ao Sul Catarinense, a família Quarezemin cultiva a arte do vinho desde 2002.</p>
          <p>Uvas selecionadas, poda e vindima manuais e vinhos envelhecidos em cantina de pedra. Estamos em Içara, coração da Denominação de Origem Vales da Uva Goethe, a primeira D.O. de vinhos de Santa Catarina e a única região do mundo a cultivar a uva Goethe com Indicação Geográfica certificada.</p>
        </div>
      </div>
      <div class="motivos">
        <p class="eyebrow"><span aria-hidden="true"></span>Por que revender Quarezemin<span aria-hidden="true"></span></p>
        <ol class="motivos__lista">
          <li><span class="motivos__n">01</span><h3><em>Origem</em> certificada</h3><p>Selo da D.O. Vales da Uva Goethe: identidade e procedência que o cliente reconhece na prateleira.</p></li>
          <li><span class="motivos__n">02</span><h3><em>Premiados</em></h3><p>Medalhas no Brasil Wine Cup 2026 e no Concurso Brasileiro de Vinhos de Mesa 2024.</p></li>
          <li><span class="motivos__n">03</span><h3><em>Direto</em> da cantina</h3><p>Pedido e atendimento com quem produz, sem intermediários entre a vinícola e a sua loja.</p></li>
          <li><span class="motivos__n">04</span><h3><em>Rótulo</em> personalizado</h3><p>Vinhos e espumantes com a marca do seu negócio, para presentes, datas e eventos.</p></li>
        </ol>
      </div>
      <?= pe_folha(++$pagina) ?>
    </section>

    <!-- Produtos, quatro por página -->
    <?php foreach ($paginasProdutos as $grupo): ?>
      <section class="folha" aria-label="Produtos">
        <?= topo_folha($edicao) ?>
        <div class="produtos">
          <?php foreach ($grupo as $p): ?>
            <article class="prod">
              <div class="prod__garrafa"><?php if ($p['foto']): ?><img src="../<?= e($p['foto']) ?>" alt="" /><?php endif; ?></div>
              <div class="prod__info">
                <p class="prod__linha"><?= $p['linha'] === 'Suco de Uva' ? 'Suco de Uva' : 'Linha ' . e($p['linha']) ?></p>
                <h3 class="prod__nome"><?= nome_com_destaque($p['nome']) ?></h3>
                <?php if ($p['estilo']): ?><p class="prod__estilo"><?= e($p['estilo']) ?></p><?php endif; ?>
                <p class="prod__desc"><?= e($p['descricao']) ?></p>
                <dl class="prod__specs">
                  <?php foreach (['Uva' => $p['uva'], 'Volume' => $p['volume'], 'Álcool' => $p['alcool'], 'Servir' => $p['servir']] as $k => $val): if ($val === '') continue; ?>
                    <div><dt><?= $k ?></dt><dd><?= e($val) ?></dd></div>
                  <?php endforeach; ?>
                </dl>
                <?php if ($p['premio']): ?><p class="prod__premio"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="9" r="6"/><path d="M8.5 13.5 7 22l5-3 5 3-1.5-8.5"/></svg><?= e($p['premio']) ?></p><?php endif; ?>
              </div>
              <?php if ($temCodigo || $temEan || $temCaixa || $comPreco): ?>
              <dl class="prod__cod">
                <?php if ($temCodigo): ?><div><dt>Código</dt><dd><?= $p['codigo'] !== '' ? e($p['codigo']) : '—' ?></dd></div><?php endif; ?>
                <?php if ($temEan): ?><div><dt>EAN</dt><dd><?= $p['ean'] !== '' ? e($p['ean']) : '—' ?></dd></div><?php endif; ?>
                <?php if ($temCaixa): ?><div><dt>Caixa</dt><dd><?= $p['caixa'] !== null ? (int) $p['caixa'] . ' un.' : '—' ?></dd></div><?php endif; ?>
                <?php if ($comPreco): ?><div class="prod__preco"><dt>Revenda</dt><dd><?= $p['preco'] !== null ? e(preco_br((int) $p['preco'], true)) : 'Consulte' ?></dd></div><?php endif; ?>
              </dl>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
        <?= pe_folha(++$pagina) ?>
      </section>
    <?php endforeach; ?>

    <!-- Tabela de pedidos, condições e contato -->
    <?php foreach ($paginasTabela as $t => $linhas): $ultima = $t === count($paginasTabela) - 1; ?>
      <section class="folha" aria-label="Tabela de pedidos">
        <?= topo_folha($edicao) ?>
        <div class="pedido">
          <?php if ($t === 0): ?>
            <p class="eyebrow"><span aria-hidden="true"></span>Para o seu pedido<span aria-hidden="true"></span></p>
            <h2 class="titulo"><em>Tabela</em> de pedidos</h2>
            <?php if ($comPreco): ?><p class="pedido__nota">Preços por unidade, para revenda<?= $validade ? ', válidos até ' . e($validade) : '' ?>.</p><?php endif; ?>
          <?php endif; ?>
          <table class="tabela">
            <thead><tr>
              <?php if ($temCodigo): ?><th>Código</th><?php endif; ?><th>Produto</th><th>Vol.</th>
              <?php if ($temCaixa): ?><th class="num">Cx</th><?php endif; ?>
              <?php if ($temEan): ?><th>EAN</th><?php endif; ?>
              <?php if ($comPreco): ?><th class="num">Unidade</th><?php if ($temCaixa): ?><th class="num">Caixa</th><?php endif; ?><?php endif; ?>
            </tr></thead>
            <tbody>
              <?php foreach ($linhas as $p): ?>
                <tr>
                  <?php if ($temCodigo): ?><td class="tabela__cod"><?= $p['codigo'] !== '' ? e($p['codigo']) : '—' ?></td><?php endif; ?>
                  <td><strong><?= e($p['nome']) ?></strong></td>
                  <td><?= e($p['volume']) ?></td>
                  <?php if ($temCaixa): ?><td class="num"><?= $p['caixa'] !== null ? (int) $p['caixa'] : '—' ?></td><?php endif; ?>
                  <?php if ($temEan): ?><td class="tabela__ean"><?= $p['ean'] !== '' ? e($p['ean']) : '—' ?></td><?php endif; ?>
                  <?php if ($comPreco): ?>
                    <td class="num"><?= $p['preco'] !== null ? e(preco_br((int) $p['preco'], true)) : 'Consulte' ?></td>
                    <?php if ($temCaixa): ?><td class="num"><?= $p['preco'] !== null && $p['caixa'] !== null ? e(preco_br((int) $p['preco'] * (int) $p['caixa'], true)) : '—' ?></td><?php endif; ?>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <?php if ($ultima): ?>
            <div class="fechamento">
              <?php if ($condicoes): ?>
                <div class="condicoes">
                  <h3 class="subtitulo"><em>Condições</em> comerciais</h3>
                  <ul><?php foreach ($condicoes as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
                </div>
              <?php endif; ?>
              <div class="contato<?= $condicoes ? '' : ' contato--largo' ?>">
                <div class="contato__dados">
                  <h3 class="subtitulo"><em>Faça</em> seu pedido</h3>
                  <dl>
                    <?php if ($cfg['whatsapp'] !== ''): ?><div><dt>WhatsApp</dt><dd><?= e(format_whatsapp($cfg['whatsapp'])) ?></dd></div><?php endif; ?>
                    <div><dt>Telefone</dt><dd>(48) 3432-5483</dd></div>
                    <?php if ($cfg['email'] !== ''): ?><div><dt>E-mail</dt><dd><?= e($cfg['email']) ?></dd></div><?php endif; ?>
                    <div><dt>Cantina</dt><dd>SC-445, Km 66 · Içara/SC</dd></div>
                    <div><dt>Instagram</dt><dd>@vinhos.quarezemin</dd></div>
                    <?php if ($site): ?><div><dt>Site</dt><dd><?= e($site) ?></dd></div><?php endif; ?>
                  </dl>
                </div>
                <?php if ($whatsLink): ?>
                  <a class="contato__qr" href="<?= e($whatsLink) ?>">
                    <span id="qr-whats" role="img" aria-label="QR Code para pedir pelo WhatsApp"></span>
                    <small>Aponte a câmera e<br />peça pelo WhatsApp</small>
                  </a>
                <?php endif; ?>
              </div>
            </div>
            <p class="aviso">Aprecie com moderação. Venda proibida para menores de 18 anos.<?= $comPreco ? ' Preços sujeitos a alteração sem aviso prévio.' : '' ?></p>
          <?php endif; ?>
        </div>
        <?= pe_folha(++$pagina) ?>
      </section>
    <?php endforeach; ?>
  </main>

  <script src="assets/vendor/qrcode.js"></script>
  <script>
    (() => {
      const alvo = document.getElementById('qr-whats');
      if (alvo) {
        const qr = qrcode(0, 'M');
        qr.addData(<?= json_encode($whatsLink, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>);
        qr.make();
        const n = qr.getModuleCount();
        const m = 4; // zona livre do padrão QR
        let d = '';
        for (let r = 0; r < n; r++) {
          for (let c = 0; c < n; c++) {
            if (qr.isDark(r, c)) d += `M${c + m} ${r + m}h1v1h-1z`;
          }
        }
        const t = n + m * 2;
        alvo.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${t} ${t}" shape-rendering="crispEdges"><rect width="${t}" height="${t}" fill="#fff"/><path fill="#1d1714" d="${d}"/></svg>`;
      }
      // Espera as fontes e as fotos antes de abrir a impressão, para o PDF sair completo.
      document.getElementById('salvar-pdf').addEventListener('click', async () => {
        await document.fonts.ready;
        await Promise.all([...document.images].map((img) => (img.complete ? null : new Promise((ok) => { img.onload = img.onerror = ok; }))));
        window.print();
      });
    })();
  </script>
</body>
</html>
