<?php
declare(strict_types=1);

/*
 * Avaliações da home. A vinícola copia do Google as avaliações que quer mostrar
 * e atualiza a nota geral pelo painel. Não há busca automática no Google.
 */

require_once __DIR__ . '/agenda.php';

const AVALIACOES_ORIGINAIS = [
    ['Heloisa Pires', 'Tivemos uma experiência maravilhosa na Vinícola Quarezemin. O lugar é muito acolhedor e transmite muita paixão pelo vinho e pela tradição familiar.'],
    ['Tamita Oliveira', 'O melhor espumante Goethe que provamos! A Beatriz nos encantou com um atendimento caloroso, que foi além das expectativas.'],
    ['Anna Flávia Neri de Almeida', 'Nunca tinha provado vinhos de uva Goethe, minha primeira experiência foi com essa empresa e adorei!'],
    ['André Eleuterio', 'Utilizo os espumantes e vinhos da Quarezemin para presentear nossos clientes. São super atenciosos, e nos ajudam bastante colocando a nossa marca no rótulo.'],
    ['Daiana Luz Censi Leripio', 'Os vinhos são maravilhosos e a vinícola é incrível. Vale muito a visita e a degustação! Meu preferido é o Margherita!'],
    ['Arthur Bianchini', 'A qualidade dos vinhos é incomparável, com sabores ricos e bem equilibrados que agradam até os paladares mais exigentes.'],
];

const AVALIACOES_LINK_PADRAO = 'https://maps.google.com/?cid=7729349891538412445';
const AVALIACOES_MAX_SITE = 12;

/** Na primeira vez, cadastra as avaliações que já estavam no site. Só roda uma vez. */
function avaliacoes_semear(): void
{
    if (ajuste('avaliacoes_semeadas', false)) {
        return;
    }
    $pdo = db();
    if ((int) $pdo->query('SELECT COUNT(*) FROM avaliacoes')->fetchColumn() === 0) {
        $ins = $pdo->prepare('INSERT INTO avaliacoes (autor, texto, estrelas, ordem, ativo, criado_em) VALUES (?, ?, 5, ?, 1, ?)');
        foreach (AVALIACOES_ORIGINAIS as $i => [$autor, $texto]) {
            $ins->execute([$autor, $texto, ($i + 1) * 10, now()]);
        }
    }
    salvar_ajuste('avaliacoes_semeadas', true);
}

/** Nota geral, total e links do Google, como aparecem no topo da seção. */
function avaliacoes_resumo(): array
{
    $r = ajuste('avaliacoes_resumo', []);
    return [
        'nota' => isset($r['nota']) ? (float) $r['nota'] : 5.0,
        'total' => isset($r['total']) ? (int) $r['total'] : 69,
        'link_google' => $r['link_google'] ?? AVALIACOES_LINK_PADRAO,
        'link_avaliar' => $r['link_avaliar'] ?? '',
    ];
}

/** Link que o QR Code abre: o de "deixar avaliação", ou a página da vinícola no Google. */
function avaliacoes_link_qr(array $resumo): string
{
    return $resumo['link_avaliar'] !== '' ? $resumo['link_avaliar'] : $resumo['link_google'];
}

/** Aceita só links https do Google (Maps, Perfil da Empresa, g.page e encurtadores). */
function link_google_valido(string $url): bool
{
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
        return false;
    }
    $host = strtolower($p['host']);
    foreach (['google.com', 'google.com.br', 'g.page', 'goo.gl', 'g.co'] as $dom) {
        if ($host === $dom || str_ends_with($host, '.' . $dom)) {
            return true;
        }
    }
    return false;
}

/** "4.8" vira "4,8". */
function nota_br(float $nota): string
{
    return number_format($nota, 1, ',', '');
}

function avaliacoes_lista(bool $soAtivas): array
{
    avaliacoes_semear();
    $sql = 'SELECT * FROM avaliacoes' . ($soAtivas ? ' WHERE ativo = 1' : '') . ' ORDER BY ordem, id';
    return db()->query($sql)->fetchAll();
}

function avaliacoes_renumerar(): void
{
    $pdo = db();
    $up = $pdo->prepare('UPDATE avaliacoes SET ordem = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id FROM avaliacoes ORDER BY ordem, id')->fetchAll(PDO::FETCH_COLUMN) as $i => $id) {
        $up->execute([($i + 1) * 10, $id]);
    }
}

function avaliacoes_mover(int $id, int $direcao): void
{
    avaliacoes_renumerar();
    $pdo = db();
    $ids = array_map('intval', $pdo->query('SELECT id FROM avaliacoes ORDER BY ordem, id')->fetchAll(PDO::FETCH_COLUMN));
    $pos = array_search($id, $ids, true);
    $alvo = $pos === false ? false : $pos + $direcao;
    if ($pos === false || $alvo < 0 || $alvo >= count($ids)) {
        return;
    }
    $up = $pdo->prepare('UPDATE avaliacoes SET ordem = ? WHERE id = ?');
    $up->execute([($alvo + 1) * 10, $id]);
    $up->execute([($pos + 1) * 10, $ids[$alvo]]);
}
