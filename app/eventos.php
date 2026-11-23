<?php
declare(strict_types=1);

/* Eventos publicados pela vinícola: aparecem em visitas-eventos.html até a data passar. */

require_once __DIR__ . '/agenda.php';

const EVENTO_FOTO_PADRAO = 'imagens/parreirais-uva-goethe.webp';
const EVENTO_FOTO_LARGURA = 1600;
const EVENTO_FOTO_MAX_BYTES = 12 * 1024 * 1024;

/** Eventos que o site mostra: publicados e de hoje em diante. */
function eventos_publicos(int $limite = 6): array
{
    $st = db()->prepare('SELECT id, titulo, linha, data, horario, local, info, descricao, foto
        FROM eventos WHERE publicado = 1 AND data >= ? ORDER BY data, id LIMIT ' . max(1, $limite));
    $st->execute([date('Y-m-d')]);
    return $st->fetchAll();
}

/** "Domingo · 11 Jan 2026", o mesmo formato dos cards do site. */
function data_card(string $d): string
{
    $t = strtotime($d);
    $mes = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'][(int) date('n', $t)];
    return DIAS_SEMANA[(int) date('w', $t)] . ' · ' . date('j', $t) . ' ' . $mes . ' ' . date('Y', $t);
}

/**
 * Recebe o upload ($_FILES['foto']), valida pelo conteúdo real do arquivo,
 * redimensiona e grava como WebP em uploads/eventos/. Retorna o caminho relativo ao site.
 * Lança RuntimeException com mensagem amigável quando algo não serve.
 */
function salvar_foto_evento(array $f): string
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('A foto é grande demais. Envie uma imagem de até 12 MB.');
    }
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException('Não foi possível receber a foto. Tente de novo.');
    }
    if ($f['size'] > EVENTO_FOTO_MAX_BYTES) {
        throw new RuntimeException('A foto é grande demais. Envie uma imagem de até 12 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        'image/png' => @imagecreatefrompng($f['tmp_name']),
        'image/webp' => @imagecreatefromwebp($f['tmp_name']),
        default => false,
    };
    if (!$img) {
        throw new RuntimeException('Formato não aceito. Envie uma foto em JPG, PNG ou WebP.');
    }

    // Fotos de celular vêm deitadas: respeita a orientação gravada no EXIF.
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($f['tmp_name'])['Orientation'] ?? 1);
        $ang = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($ang) {
            $img = imagerotate($img, $ang, 0);
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 600 || $h < 400) {
        imagedestroy($img);
        throw new RuntimeException('A foto é pequena demais. Use uma imagem com pelo menos 600 × 400 pixels.');
    }
    if ($w > EVENTO_FOTO_LARGURA) {
        $nh = (int) round($h * EVENTO_FOTO_LARGURA / $w);
        $novo = imagecreatetruecolor(EVENTO_FOTO_LARGURA, $nh);
        imagecopyresampled($novo, $img, 0, 0, 0, 0, EVENTO_FOTO_LARGURA, $nh, $w, $h);
        imagedestroy($img);
        $img = $novo;
    }

    $dir = dirname(__DIR__) . '/uploads/eventos';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Não foi possível salvar a foto no servidor.');
    }
    $nome = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.webp';
    $ok = imagewebp($img, $dir . '/' . $nome, 80);
    imagedestroy($img);
    if (!$ok) {
        throw new RuntimeException('Não foi possível salvar a foto no servidor.');
    }
    return 'uploads/eventos/' . $nome;
}

/** Apaga do disco uma foto enviada pelo painel (nunca as imagens fixas do site). */
function apagar_foto_evento(?string $caminho): void
{
    if ($caminho && preg_match('#^uploads/eventos/[A-Za-z0-9-]+\.webp$#', $caminho)) {
        @unlink(dirname(__DIR__) . '/' . $caminho);
    }
}
