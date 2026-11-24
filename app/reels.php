<?php
declare(strict_types=1);

/*
 * Reels da home. Cada reel é um link do Instagram (abre no player oficial) ou um
 * vídeo hospedado no site (os 9 originais). A capa é opcional para links.
 */

require_once __DIR__ . '/agenda.php';
require_once __DIR__ . '/imagens.php';

const REELS_ORIGINAIS = [
    ['Para desacelerar', 'para-desacelerar', 'Merlot Quarezemin servido ao lado da lareira'],
    ['Sua marca no rótulo', 'sua-marca-no-rotulo', 'Garrafas com rótulo personalizado para empresas'],
    ['Dentro da cantina', 'dentro-da-cantina', 'Visita guiada na cantina em pedra'],
    ['Os parreirais de Goethe', 'parreirais', 'Cachos de uva Goethe nos parreirais'],
    ['Gelado, do jeito certo', 'gelado', 'Espumantes Quarezemin no balde de gelo'],
    ['Suco de uva integral', 'suco-de-uva', 'Suco de uva integral Quarezemin'],
    ['O céu de Içara', 'ceu-de-icara', 'Céu azul sobre os parreirais em Içara'],
    ['Sexta-feira na vinícola', 'sexta-feira', 'Entrada da cantina da vinícola'],
    ['Obrigada por confiar', 'obrigada', 'Garrafas na linha de produção da vinícola'],
];

/** Na primeira vez, cadastra os reels que já estavam no site. Só roda uma vez. */
function reels_semear(): void
{
    if (ajuste('reels_semeados', false)) {
        return;
    }
    $pdo = db();
    if ((int) $pdo->query('SELECT COUNT(*) FROM reels')->fetchColumn() === 0) {
        $ins = $pdo->prepare('INSERT INTO reels (titulo, video, capa, descricao_capa, ordem, ativo, criado_em) VALUES (?, ?, ?, ?, ?, 1, ?)');
        foreach (REELS_ORIGINAIS as $i => [$titulo, $slug, $alt]) {
            $ins->execute([$titulo, "videos/reels/{$slug}.mp4", "imagens/reels/{$slug}.webp", $alt, ($i + 1) * 10, now()]);
        }
    }
    salvar_ajuste('reels_semeados', true);
}

/**
 * Normaliza um link do Instagram. Aceita reel, post e tv, com ou sem www,
 * parâmetros (?igsh=...) e nome de perfil no caminho.
 * Retorna ['url' => link limpo, 'embed' => url do player] ou null.
 */
function instagram_link(string $url): ?array
{
    $url = trim($url);
    if (!preg_match('#^(?:https?://)?(?:www\.|m\.)?instagram\.com/(?:[A-Za-z0-9._]+/)?(reels?|p|tv)/([A-Za-z0-9_-]{5,40})#i', $url, $m)) {
        return null;
    }
    $tipo = strtolower($m[1]) === 'p' ? 'p' : (strtolower($m[1]) === 'tv' ? 'tv' : 'reel');
    $limpo = "https://www.instagram.com/{$tipo}/{$m[2]}/";
    return ['url' => $limpo, 'embed' => $limpo . 'embed/'];
}

function reels_lista(bool $soAtivos): array
{
    reels_semear();
    $sql = 'SELECT * FROM reels' . ($soAtivos ? ' WHERE ativo = 1' : '') . ' ORDER BY ordem, id';
    return db()->query($sql)->fetchAll();
}

/** Renumera a ordem de 10 em 10, para mover para cima/baixo sempre funcionar. */
function reels_renumerar(): void
{
    $pdo = db();
    $up = $pdo->prepare('UPDATE reels SET ordem = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id FROM reels ORDER BY ordem, id')->fetchAll(PDO::FETCH_COLUMN) as $i => $id) {
        $up->execute([($i + 1) * 10, $id]);
    }
}

function reels_mover(int $id, int $direcao): void
{
    reels_renumerar();
    $pdo = db();
    $ids = $pdo->query('SELECT id FROM reels ORDER BY ordem, id')->fetchAll(PDO::FETCH_COLUMN);
    $pos = array_search($id, array_map('intval', $ids), true);
    $alvo = $pos === false ? false : $pos + $direcao;
    if ($pos === false || $alvo < 0 || $alvo >= count($ids)) {
        return;
    }
    [$ids[$pos], $ids[$alvo]] = [$ids[$alvo], $ids[$pos]];
    $up = $pdo->prepare('UPDATE reels SET ordem = ? WHERE id = ?');
    foreach ($ids as $i => $rid) {
        $up->execute([($i + 1) * 10, $rid]);
    }
}
