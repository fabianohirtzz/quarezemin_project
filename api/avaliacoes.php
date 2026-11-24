<?php
declare(strict_types=1);

require __DIR__ . '/../app/avaliacoes.php';

header('Cache-Control: public, max-age=300');
$resumo = avaliacoes_resumo();
$lista = [];
foreach (array_slice(avaliacoes_lista(true), 0, AVALIACOES_MAX_SITE) as $a) {
    $lista[] = ['autor' => $a['autor'], 'texto' => $a['texto'], 'estrelas' => (int) $a['estrelas']];
}
json_out([
    'ok' => true,
    'nota' => nota_br($resumo['nota']),
    'nota_valor' => $resumo['nota'],
    'total' => $resumo['total'],
    'link' => $resumo['link_google'],
    'avaliar' => avaliacoes_link_qr($resumo),
    'avaliacoes' => $lista,
]);
