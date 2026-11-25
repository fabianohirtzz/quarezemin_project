<?php
declare(strict_types=1);

/*
 * Reels do site, separados por página: "home" (Bastidores da cantina) e
 * "personalize" (vídeos e fotos de rótulos personalizados).
 * Cada item é um link do Instagram (abre no player oficial), um vídeo hospedado
 * no site (os originais) ou, na página Personalize, só uma foto.
 */

require_once __DIR__ . '/agenda.php';
require_once __DIR__ . '/imagens.php';

const REELS_PAGINAS = [
    'home' => 'Página inicial',
    'personalize' => 'Personalize seu rótulo',
];

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

/** A página Personalize começa com o vídeo de rótulos que já estava no site. */
function reels_semear_personalize(): void
{
    if (ajuste('reels_personalize_semeados', false)) {
        return;
    }
    db()->prepare("INSERT INTO reels (titulo, video, capa, descricao_capa, pagina, ordem, ativo, criado_em) VALUES (?, ?, ?, ?, 'personalize', 10, 1, ?)")
        ->execute(['Sua marca no rótulo', 'videos/reels/sua-marca-no-rotulo.mp4', 'imagens/reels/sua-marca-no-rotulo.webp', 'Garrafas com rótulo personalizado para empresas', now()]);
    salvar_ajuste('reels_personalize_semeados', true);
}

function reels_pagina(?string $p): string
{
    return array_key_exists((string) $p, REELS_PAGINAS) ? (string) $p : 'home';
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

function reels_lista(bool $soAtivos, string $pagina = 'home'): array
{
    reels_semear();
    reels_semear_personalize();
    $st = db()->prepare('SELECT * FROM reels WHERE pagina = ?' . ($soAtivos ? ' AND ativo = 1' : '') . ' ORDER BY ordem, id');
    $st->execute([reels_pagina($pagina)]);
    return $st->fetchAll();
}

/** Renumera a ordem de 10 em 10, para mover para cima/baixo sempre funcionar. */
function reels_renumerar(string $pagina = 'home'): void
{
    $pdo = db();
    $up = $pdo->prepare('UPDATE reels SET ordem = ? WHERE id = ?');
    foreach (reels_ids($pagina) as $i => $id) {
        $up->execute([($i + 1) * 10, $id]);
    }
}

function reels_ids(string $pagina): array
{
    $st = db()->prepare('SELECT id FROM reels WHERE pagina = ? ORDER BY ordem, id');
    $st->execute([reels_pagina($pagina)]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

function reels_mover(int $id, int $direcao, string $pagina = 'home'): void
{
    reels_renumerar($pagina);
    $pdo = db();
    $ids = reels_ids($pagina);
    $pos = array_search($id, $ids, true);
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
