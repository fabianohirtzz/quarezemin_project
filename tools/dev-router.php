<?php
/*
 * Roteador do servidor de desenvolvimento (php -S). Faz o papel do .htaccess,
 * que o servidor embutido ignora: bloqueia pastas internas e arquivos de dados.
 *   php -S 127.0.0.1:8080 tools/dev-router.php
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$bloqueado = preg_match('#^/(app|tools|propostas|reference|node_modules|\.git|\.claude)(/|$)#i', $path)
    || preg_match('#/\.[^/]*$#', $path)                       // arquivos ocultos
    || preg_match('#^/uploads/.*\.(php|phtml|phar)$#i', $path) // nada executável nos uploads
    || preg_match('#\.(sqlite|log|md|json|lock|py)$#i', $path); // dados e fontes

if ($bloqueado) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não encontrado.';
    return true;
}

return false; // deixa o servidor embutido entregar o arquivo normalmente
