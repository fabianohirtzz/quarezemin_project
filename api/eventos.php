<?php
declare(strict_types=1);

require __DIR__ . '/../app/eventos.php';

header('Cache-Control: public, max-age=300');
$lista = array_map(fn($e) => [
    'titulo' => $e['titulo'],
    'linha' => $e['linha'],
    'data' => $e['data'],
    'data_card' => data_card($e['data']),
    'horario' => $e['horario'],
    'local' => $e['local'],
    'info' => $e['info'],
    'descricao' => $e['descricao'],
    'foto' => $e['foto'] ?: EVENTO_FOTO_PADRAO,
], eventos_publicos());

json_out(['ok' => true, 'eventos' => $lista]);
