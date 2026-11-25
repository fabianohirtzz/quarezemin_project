<?php
declare(strict_types=1);

require __DIR__ . '/../app/reels.php';

header('Cache-Control: public, max-age=300');
$lista = [];
$pagina = reels_pagina($_GET['pagina'] ?? 'home');
foreach (reels_lista(true, $pagina) as $r) {
    $ig = $r['instagram'] ? instagram_link($r['instagram']) : null;
    $soFoto = !$r['video'] && !$ig;
    if ($soFoto && !($pagina === 'personalize' && $r['capa'])) {
        continue; // sem vídeo, sem link válido e sem foto não tem o que mostrar
    }
    $lista[] = [
        'titulo' => $r['titulo'],
        'video' => $r['video'],
        'instagram' => $ig['url'] ?? null,
        'embed' => $ig['embed'] ?? null,
        'capa' => $r['capa'],
        'foto' => $soFoto ? $r['capa'] : null,
        'alt' => $r['descricao_capa'] ?: $r['titulo'],
    ];
}
json_out(['ok' => true, 'reels' => $lista]);
