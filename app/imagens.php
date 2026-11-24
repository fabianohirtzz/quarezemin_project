<?php
declare(strict_types=1);

/* Upload de imagens do painel: valida pelo conteúdo, corrige orientação, reduz e salva em WebP. */

const IMAGEM_MAX_BYTES = 12 * 1024 * 1024;

/**
 * Processa $_FILES[...] e grava em uploads/$pasta/. Retorna o caminho relativo ao site.
 * Lança RuntimeException com mensagem amigável quando o arquivo não serve.
 */
function salvar_imagem_upload(array $f, string $pasta, int $largura, int $minW, int $minH): string
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > IMAGEM_MAX_BYTES) {
        throw new RuntimeException('A imagem é grande demais. Envie um arquivo de até 12 MB.');
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        throw new RuntimeException('Não foi possível receber a imagem. Tente de novo.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
        'image/png' => @imagecreatefrompng($f['tmp_name']),
        'image/webp' => @imagecreatefromwebp($f['tmp_name']),
        default => false,
    };
    if (!$img) {
        throw new RuntimeException('Formato não aceito. Envie uma imagem em JPG, PNG ou WebP.');
    }
    // PNG de paleta não vira WebP; e a transparência (garrafas recortadas) precisa sobreviver.
    if (!imageistruecolor($img)) {
        imagepalettetotruecolor($img);
    }
    imagesavealpha($img, true);

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
    if ($w < $minW || $h < $minH) {
        imagedestroy($img);
        throw new RuntimeException("A imagem é pequena demais. Use pelo menos {$minW} × {$minH} pixels.");
    }
    if ($w > $largura) {
        $nh = (int) round($h * $largura / $w);
        $novo = imagecreatetruecolor($largura, $nh);
        imagealphablending($novo, false);
        imagesavealpha($novo, true);
        imagefill($novo, 0, 0, imagecolorallocatealpha($novo, 0, 0, 0, 127));
        imagecopyresampled($novo, $img, 0, 0, 0, 0, $largura, $nh, $w, $h);
        imagedestroy($img);
        $img = $novo;
    }

    $dir = dirname(__DIR__) . '/uploads/' . $pasta;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Não foi possível salvar a imagem no servidor.');
    }
    $nome = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.webp';
    $ok = imagewebp($img, $dir . '/' . $nome, 80);
    imagedestroy($img);
    if (!$ok) {
        throw new RuntimeException('Não foi possível salvar a imagem no servidor.');
    }
    return 'uploads/' . $pasta . '/' . $nome;
}

/** Apaga do disco uma imagem enviada pelo painel (nunca as imagens fixas do site). */
function apagar_upload(?string $caminho): void
{
    if ($caminho && preg_match('#^uploads/[a-z]+/[A-Za-z0-9-]+\.webp$#', $caminho)) {
        @unlink(dirname(__DIR__) . '/' . $caminho);
    }
}
