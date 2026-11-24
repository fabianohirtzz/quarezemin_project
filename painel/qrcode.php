<?php
declare(strict_types=1);

/* QR Code de avaliação no Google: placa para o balcão e arquivos para baixar. */

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/avaliacoes.php';

require_login();
$resumo = avaliacoes_resumo();
$link = avaliacoes_link_qr($resumo);
$direto = $resumo['link_avaliar'] !== '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex, nofollow" />
  <title>QR Code de avaliação · Vinhos Quarezemin</title>
  <link rel="icon" type="image/png" href="../imagens/logo-quarezemin.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/placa.css" />
</head>
<body>
  <div class="toolbar">
    <a class="toolbar__back" href="avaliacoes.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5M11 19l-7-7 7-7"/></svg>
      Avaliações
    </a>
    <div class="toolbar__actions">
      <button type="button" class="btn" id="baixar-png">Baixar QR (PNG)</button>
      <button type="button" class="btn" id="baixar-svg">Baixar QR (SVG)</button>
      <button type="button" class="btn btn--vinho" onclick="window.print()">Imprimir placa</button>
    </div>
  </div>

  <div class="info">
    <p><strong>O QR Code abre:</strong> <a href="<?= e($link) ?>" target="_blank" rel="noopener"><?= e($link) ?></a></p>
    <?php if (!$direto): ?>
      <p class="info__aviso">Hoje ele abre a página da vinícola no Google, e o visitante precisa tocar em "Escrever avaliação". Para abrir direto a janela de avaliar, cadastre o link de "Pedir avaliações" em <a href="avaliacoes.php">Avaliações &gt; Nota no Google</a>.</p>
    <?php endif; ?>
    <p class="info__dica">Teste sempre com a câmera do celular antes de imprimir. A placa sai em A5 (meia folha A4); a impressora pode ajustar a escala.</p>
  </div>

  <article class="placa" aria-label="Placa de avaliação">
    <img class="placa__logo" src="../imagens/logo-quarezemin.png" alt="Vinhos Quarezemin" />
    <p class="placa__eyebrow"><span aria-hidden="true"></span>Avalie no Google<span aria-hidden="true"></span></p>
    <h1 class="placa__title"><em>Gostou</em> da visita?</h1>
    <p class="placa__text">Aponte a câmera do celular e conte como foi. Sua avaliação ajuda outras pessoas a conhecerem a cantina.</p>
    <div class="placa__qr" id="qr" role="img" aria-label="QR Code para avaliar a Vinhos Quarezemin no Google"></div>
    <p class="placa__stars" aria-hidden="true">
      <?php for ($i = 0; $i < 5; $i++): ?><svg viewBox="0 0 24 24"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z"/></svg><?php endfor; ?>
    </p>
    <p class="placa__foot">Vinhos Quarezemin · Içara · SC</p>
  </article>

  <script src="assets/vendor/qrcode.js"></script>
  <script>
    (() => {
      const LINK = <?= json_encode($link, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
      const COR = '#1d1714';
      const MARGEM = 4; // zona livre exigida pelo padrão QR, em módulos
      const qr = qrcode(0, 'Q');
      qr.addData(LINK);
      qr.make();
      const n = qr.getModuleCount();
      const total = n + MARGEM * 2;

      // Um único path com um quadradinho por módulo escuro: nítido em qualquer tamanho.
      let d = '';
      for (let r = 0; r < n; r++) {
        for (let c = 0; c < n; c++) {
          if (qr.isDark(r, c)) d += `M${c + MARGEM} ${r + MARGEM}h1v1h-1z`;
        }
      }
      const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}" shape-rendering="crispEdges"><rect width="${total}" height="${total}" fill="#fff"/><path fill="${COR}" d="${d}"/></svg>`;
      document.getElementById('qr').innerHTML = svg;

      const baixar = (href, nome) => {
        const a = document.createElement('a');
        a.href = href;
        a.download = nome;
        document.body.appendChild(a);
        a.click();
        a.remove();
      };

      document.getElementById('baixar-svg').addEventListener('click', () => {
        const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
        baixar(url, 'qrcode-avaliacao-quarezemin.svg');
        setTimeout(() => URL.revokeObjectURL(url), 2000);
      });

      document.getElementById('baixar-png').addEventListener('click', () => {
        const escala = Math.ceil(1600 / total);
        const canvas = document.createElement('canvas');
        canvas.width = canvas.height = total * escala;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = COR;
        for (let r = 0; r < n; r++) {
          for (let c = 0; c < n; c++) {
            if (qr.isDark(r, c)) ctx.fillRect((c + MARGEM) * escala, (r + MARGEM) * escala, escala, escala);
          }
        }
        baixar(canvas.toDataURL('image/png'), 'qrcode-avaliacao-quarezemin.png');
      });
    })();
  </script>
</body>
</html>
