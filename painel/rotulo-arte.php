<?php
declare(strict_types=1);

/* Entrega a arte de um pedido de rótulo, só para quem está logado no painel. */

require __DIR__ . '/../app/painel-layout.php';
require_once __DIR__ . '/../app/rotulos.php';

require_login();
$st = db()->prepare('SELECT arte, arte_nome FROM rotulos WHERE id = ?');
$st->execute([(int) ($_GET['id'] ?? 0)]);
$r = $st->fetch();
$caminho = $r ? rotulo_arte_caminho($r['arte']) : null;
if (!$caminho) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Arquivo não encontrado.';
    exit;
}

$ext = pathinfo($caminho, PATHINFO_EXTENSION);
$tipo = array_search($ext, ROTULO_ARTE_TIPOS, true) ?: 'application/octet-stream';
$nome = preg_replace('/[^\w.\- ]+/u', '_', (string) ($r['arte_nome'] ?: 'arte.' . $ext));

header('Content-Type: ' . $tipo);
header('Content-Length: ' . filesize($caminho));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
header('Content-Disposition: ' . (isset($_GET['baixar']) ? 'attachment' : 'inline') . '; filename="' . $nome . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
readfile($caminho);
