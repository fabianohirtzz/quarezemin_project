<?php
declare(strict_types=1);

require __DIR__ . '/../app/reels.php';

header('Cache-Control: public, max-age=300');
$lista = [];
foreach (reels_lista(true) as $r) {
    $ig = $r['instagram'] ? instagram_link($r['instagram']) : null;
    if (!$r['video'] && !$ig) {
        continue; // sem vídeo e sem link válido não tem o que tocar
    }
    $lista[] = [
        'titulo' => $r['titulo'],
        'video' => $r['video'],
        'instagram' => $ig['url'] ?? null,
        'embed' => $ig['embed'] ?? null,
        'capa' => $r['capa'],
        'alt' => $r['descricao_capa'] ?: $r['titulo'],
    ];
}
json_out(['ok' => true, 'reels' => $lista]);
