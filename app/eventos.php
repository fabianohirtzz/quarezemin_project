<?php
declare(strict_types=1);

/* Eventos publicados pela vinícola: aparecem em visitas-eventos.html até a data passar. */

require_once __DIR__ . '/agenda.php';
require_once __DIR__ . '/imagens.php';

const EVENTO_FOTO_PADRAO = 'imagens/parreirais-uva-goethe.webp';
const EVENTO_FOTO_LARGURA = 1600;

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

function salvar_foto_evento(array $f): string
{
    return salvar_imagem_upload($f, 'eventos', EVENTO_FOTO_LARGURA, 600, 400);
}

function apagar_foto_evento(?string $caminho): void
{
    apagar_upload($caminho);
}
