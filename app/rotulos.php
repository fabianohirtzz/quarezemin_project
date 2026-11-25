<?php
declare(strict_types=1);

/* Pedidos de rótulo personalizado: chegam pelo formulário de personalize.html e ficam no painel. */

require_once __DIR__ . '/bootstrap.php';

const ROTULO_SITUACOES = [
    'novo' => 'Novo',
    'orcamento' => 'Orçamento enviado',
    'aprovado' => 'Aprovado',
    'entregue' => 'Entregue',
    'descartado' => 'Descartado',
];

const ROTULO_PRODUTOS = [
    'Margherita Branco Seco', 'Margherita Demi-Sec', 'Branco Seco Goethe', 'Branco Suave Goethe',
    'Rosé Goethe', 'Tinto Seco Bordô', 'Tinto Suave Bordô', 'Cabernet Sauvignon', 'Merlot',
    'Espumante Moscatel Branco', 'Espumante Moscatel Rosé',
];

const ROTULO_MINIMO = 36;
const ROTULO_ARTE_MAX = 10 * 1024 * 1024;
/** Tipo real do arquivo => extensão gravada. */
const ROTULO_ARTE_TIPOS = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'];

/** Pasta das artes: dentro de app/storage, que não é acessível pela web. */
function rotulo_pasta(): string
{
    $dir = __DIR__ . '/storage/rotulos';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

/**
 * Valida e guarda a arte enviada. Retorna [nome do arquivo gravado, nome original].
 * Lança RuntimeException com mensagem para o visitante.
 */
function rotulo_salvar_arte(array $f): array
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > ROTULO_ARTE_MAX) {
        throw new RuntimeException('O arquivo da arte é grande demais. Envie um de até 10 MB.');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException('Não foi possível receber o arquivo da arte. Tente de novo.');
    }
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
    $ext = ROTULO_ARTE_TIPOS[$tipo] ?? null;
    if ($ext === null) {
        throw new RuntimeException('Envie a arte em PDF, PNG ou JPG.');
    }
    $nome = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], rotulo_pasta() . '/' . $nome)) {
        throw new RuntimeException('Não foi possível guardar o arquivo da arte. Tente de novo.');
    }
    $original = clean_text(basename((string) ($f['name'] ?? '')), 160) ?: 'arte.' . $ext;
    return [$nome, $original];
}

function rotulo_arte_caminho(?string $nome): ?string
{
    if (!$nome || !preg_match('/^[0-9]{8}-[0-9a-f]{16}\.(pdf|png|jpg)$/', $nome)) {
        return null;
    }
    $p = rotulo_pasta() . '/' . $nome;
    return is_file($p) ? $p : null;
}

function rotulo_apagar_arte(?string $nome): void
{
    $p = rotulo_arte_caminho($nome);
    if ($p) {
        @unlink($p);
    }
}

function badge_rotulo(string $sit): string
{
    return '<span class="badge badge--' . e($sit) . '">' . e(ROTULO_SITUACOES[$sit] ?? $sit) . '</span>';
}
