<?php
declare(strict_types=1);

/*
 * Catálogo para revendedores: os rótulos com código, EAN, caixa e preço de revenda.
 * O painel monta a partir daqui o PDF que a equipe envia aos revendedores.
 * Não mexe nas páginas de produto do site, que continuam estáticas.
 */

require_once __DIR__ . '/agenda.php';

const CATALOGO_LINHAS = ['Margherita', 'Mesa', 'Goethe', 'Fino', 'Espumante', 'Suco de Uva'];

// slug, nome, linha, estilo, uva, volume, álcool, servir, descrição, prêmio
const PRODUTOS_ORIGINAIS = [
    ['margherita-branco-seco', 'Margherita Branco Seco', 'Margherita', '100% Goethe · Seco', 'Goethe', '750ml', '11,5%', '6 a 9°C', 'Vinho delicado, de coloração amarelo palha com reflexos dourados. Bouquet finíssimo que combina mel, flores brancas, frutas cítricas e um toque mineral.', 'Medalha de Prata · Brasil Wine Cup 2026'],
    ['margherita-demi-sec', 'Margherita Demi-Sec', 'Margherita', '100% Goethe · Demi-sec', 'Goethe', '750ml', '11,5%', '6 a 9°C', 'Vinho delicado, de coloração amarelo palha com reflexos dourados. Bouquet finíssimo que combina mel, flores brancas, frutas cítricas e um toque mineral.', 'Medalha de Bronze · Brasil Wine Cup 2026'],
    ['branco-seco-goethe', 'Branco Seco Goethe', 'Mesa', 'Goethe e Niágara · Seco', 'Goethe e Niágara', '750ml', '11,5%', '5 a 8°C', 'Corte das uvas Goethe e Niágara, em tonalidade amarelo palha, com aroma intenso e frutado e um leve toque mineral.', ''],
    ['branco-suave-goethe', 'Branco Suave Goethe', 'Mesa', 'Goethe e Niágara · Suave', 'Goethe e Niágara', '750ml', '11,5%', '5 a 8°C', 'Corte das uvas Goethe e Niágara, em tonalidade amarelo palha, com aroma intenso e frutado e um leve toque mineral.', ''],
    ['tinto-seco-bordo', 'Tinto Seco Bordô', 'Mesa', 'Bordô · Seco', 'Bordô', '750ml', '11,5%', '12 a 16°C', 'Coloração que vai do vermelho vivo ao violeta intenso, com aromas de frutas vermelhas maduras.', ''],
    ['tinto-suave-bordo', 'Tinto Suave Bordô', 'Mesa', 'Bordô · Suave', 'Bordô', '750ml', '11,5%', '12 a 16°C', 'Coloração que vai do vermelho vivo ao violeta intenso, com aromas de frutas vermelhas maduras.', ''],
    ['rose-goethe', 'Rosé Goethe', 'Goethe', '100% Goethe · Demi-sec', 'Goethe', '750ml', '11%', '6 a 9°C', 'Coloração salmão claro. Aromas finos de morango, framboesa e pêssego, com toques cítricos e minerais.', 'Medalha de Prata · Concurso Brasileiro de Vinhos de Mesa 2024'],
    ['cabernet-sauvignon', 'Cabernet Sauvignon', 'Fino', '100% Cabernet Sauvignon · Seco', 'Cabernet Sauvignon', '750ml', '12%', '16 a 18°C', 'Cor vermelho rubi intensa e aroma de frutas vermelhas. Encorpado, de boa estrutura e grande persistência em boca.', ''],
    ['merlot', 'Merlot', 'Fino', '100% Merlot · Seco', 'Merlot', '750ml', '12,5%', '16 a 18°C', 'Vermelho rubi, bem estruturado, com taninos macios e ótimo equilíbrio.', 'Prata · Brasil Wine Cup 2025'],
    ['espumante-moscatel-branco', 'Espumante Moscatel Branco', 'Espumante', 'Moscatel · Branco', 'Moscatéis brancas', '750ml', '7,5%', '2 a 5°C', 'Amarelo palha com reflexos esverdeados, borbulhas finas e intensas e aroma floral que lembra flores brancas, com um toque inconfundível de mel.', ''],
    ['espumante-moscatel-rose', 'Espumante Moscatel Rosé', 'Espumante', 'Moscatel · Rosé', 'Moscatéis', '750ml', '7,5%', '2 a 5°C', 'Coloração rosada brilhante, de boa intensidade e reflexos rubi. No aroma, frutas vermelhas frescas como cereja e morango, com notas cítricas.', ''],
    ['suco-uva-integral', 'Suco de Uva Integral', 'Suco de Uva', 'Isabel e Bordô · Integral', 'Isabel e Bordô', '1 litro', 'Sem álcool', '4 a 6°C', 'Aspecto vívido e brilhante, coloração vermelho violeta e aroma intenso e fresco que lembra frutos vermelhos maduros.', ''],
];

/** Na primeira vez, cadastra os 12 rótulos do site. Só roda uma vez. */
function catalogo_semear(): void
{
    if (ajuste('produtos_semeados', false)) {
        return;
    }
    $pdo = db();
    if ((int) $pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn() === 0) {
        $ins = $pdo->prepare('INSERT INTO produtos (slug, nome, linha, estilo, uva, volume, alcool, servir, descricao, premio, foto, ordem, ativo, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
        foreach (PRODUTOS_ORIGINAIS as $i => $p) {
            $ins->execute([...$p, 'imagens/produto-' . $p[0] . '.png', ($i + 1) * 10, now()]);
        }
    }
    salvar_ajuste('produtos_semeados', true);
}

function catalogo_produtos(bool $soAtivos): array
{
    catalogo_semear();
    $sql = 'SELECT * FROM produtos' . ($soAtivos ? ' WHERE ativo = 1' : '') . ' ORDER BY ordem, id';
    return db()->query($sql)->fetchAll();
}

function catalogo_produto(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM produtos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Ajustes do PDF: preços, validade, condições comerciais e contato. */
function catalogo_ajustes(): array
{
    $a = ajuste('catalogo', []);
    return [
        'mostrar_precos' => (bool) ($a['mostrar_precos'] ?? true),
        'validade' => (string) ($a['validade'] ?? ''),
        'condicoes' => (string) ($a['condicoes'] ?? ''),
        'whatsapp' => (string) ($a['whatsapp'] ?? '48999546255'),
        'email' => (string) ($a['email'] ?? 'vinhosquazemin@gmail.com'),
    ];
}

function catalogo_renumerar(): void
{
    $pdo = db();
    $up = $pdo->prepare('UPDATE produtos SET ordem = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id FROM produtos ORDER BY ordem, id')->fetchAll(PDO::FETCH_COLUMN) as $i => $id) {
        $up->execute([($i + 1) * 10, $id]);
    }
}

function catalogo_mover(int $id, int $direcao): void
{
    catalogo_renumerar();
    $pdo = db();
    $ids = array_map('intval', $pdo->query('SELECT id FROM produtos ORDER BY ordem, id')->fetchAll(PDO::FETCH_COLUMN));
    $pos = array_search($id, $ids, true);
    $alvo = $pos === false ? false : $pos + $direcao;
    if ($pos === false || $alvo < 0 || $alvo >= count($ids)) {
        return;
    }
    $up = $pdo->prepare('UPDATE produtos SET ordem = ? WHERE id = ?');
    $up->execute([($alvo + 1) * 10, $id]);
    $up->execute([($pos + 1) * 10, $ids[$alvo]]);
}

/**
 * "45,90", "R$ 1.234,50" ou "45.9" viram centavos. Vazio vira null.
 * Retorna false quando o texto não é um preço.
 */
function preco_de_texto(string $txt): int|null|false
{
    $t = trim(str_ireplace('R$', '', $txt));
    $t = str_replace(' ', '', $t);
    if ($t === '') {
        return null;
    }
    if (str_contains($t, ',')) {
        $t = str_replace('.', '', $t);
        $t = str_replace(',', '.', $t);
    }
    if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $t)) {
        return false;
    }
    return (int) round((float) $t * 100);
}

/** 4590 vira "45,90". */
function preco_br(?int $centavos, bool $simbolo = false): string
{
    if ($centavos === null) {
        return '';
    }
    return ($simbolo ? 'R$ ' : '') . number_format($centavos / 100, 2, ',', '.');
}

/** Código de barras GTIN (EAN-8, UPC-12, EAN-13 ou DUN-14) com dígito verificador certo. */
function ean_valido(string $ean): bool
{
    if (!preg_match('/^\d{8}$|^\d{12,14}$/', $ean)) {
        return false;
    }
    $digitos = array_map('intval', str_split($ean));
    $verificador = array_pop($digitos);
    $soma = 0;
    foreach (array_reverse($digitos) as $i => $d) {
        $soma += $d * ($i % 2 === 0 ? 3 : 1);
    }
    return (10 - $soma % 10) % 10 === $verificador;
}

function codigo_valido(string $codigo): bool
{
    return (bool) preg_match('#^[A-Za-z0-9][A-Za-z0-9./-]{0,19}$#', $codigo);
}

/** "Cabernet Sauvignon" vira "<em>Cabernet</em> Sauvignon", como nos títulos do site. */
function nome_com_destaque(string $nome): string
{
    $partes = explode(' ', $nome, 2);
    return '<em>' . e($partes[0]) . '</em>' . (isset($partes[1]) ? ' ' . e($partes[1]) : '');
}
