<?php
declare(strict_types=1);

/*
 * Regras da agenda de visitas: turnos, lotação, dias de funcionamento e bloqueios.
 * Usado pela API pública e pelo painel, para os dois enxergarem a mesma disponibilidade.
 */

require_once __DIR__ . '/bootstrap.php';

const TURNOS = [
    'manha' => 'Manhã (9h às 11h30)',
    'tarde' => 'Tarde (14h às 18h)',
];
const TURNOS_CURTO = ['manha' => 'Manhã', 'tarde' => 'Tarde'];

const EXPERIENCIAS = [
    'tour-degustacao' => 'Tour + Degustação',
    'tour-adega' => 'Tour pela Adega',
    'degustacao' => 'Degustação de Vinhos',
    'vindima' => 'Colheita na Vindima',
];

const TIPOS_EVENTO = [
    'vindima' => 'Vindima · Colheita da Goethe',
    'pinta-brinda' => 'Pinta e Brinda',
    'degustacao-privada' => 'Degustação privada',
    'corporativo' => 'Evento corporativo',
    'casamento' => 'Casamento / Bodas',
    'aniversario' => 'Aniversário',
];

const STATUS_AGENDAMENTO = [
    'pendente' => 'Aguardando',
    'confirmada' => 'Confirmada',
    'cancelada' => 'Cancelada',
];

const DIAS_SEMANA = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
const MESES = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

const ENDERECO_CANTINA = 'Rod. SC-445, Km 66, Içara/SC';
const MAPA_CANTINA = 'https://maps.google.com/?cid=7729349891538412445';
const WHATSAPP_CANTINA = '(48) 9 9954-6255';

/* ---------- Ajustes (chave/valor) ---------- */

function ajuste(string $chave, $padrao = null)
{
    $st = db()->prepare('SELECT valor FROM ajustes WHERE chave = ?');
    $st->execute([$chave]);
    $v = $st->fetchColumn();
    return $v === false ? $padrao : json_decode((string) $v, true);
}

function salvar_ajuste(string $chave, $valor): void
{
    $pdo = db();
    $json = json_encode($valor, JSON_UNESCAPED_UNICODE);
    $pdo->prepare('DELETE FROM ajustes WHERE chave = ?')->execute([$chave]);
    $pdo->prepare('INSERT INTO ajustes (chave, valor) VALUES (?, ?)')->execute([$chave, $json]);
}

/** Linhas de horário mostradas no rodapé e na página de visitas (editáveis no painel). */
const HORARIOS_SITE_PADRAO = [
    ['dias' => 'Seg a Sex', 'horas' => '9h às 11h30 e 14h às 18h'],
    ['dias' => 'Sábado', 'horas' => '9h às 11h30'],
    ['dias' => 'Domingo', 'horas' => 'mediante agendamento'],
];

function horarios_site(): array
{
    $v = ajuste('horarios_site', HORARIOS_SITE_PADRAO);
    return is_array($v) ? $v : HORARIOS_SITE_PADRAO;
}

/** Configuração efetiva da agenda, com os padrões do horário de visitação da cantina. */
function agenda_config(): array
{
    return [
        'limite' => ajuste('limite_turno', ['manha' => 30, 'tarde' => 30]),
        // índice = date('w'): 0 domingo ... 6 sábado
        'dias' => ajuste('dias_turnos', [
            0 => ['manha', 'tarde'],
            1 => ['manha', 'tarde'],
            2 => ['manha', 'tarde'],
            3 => ['manha', 'tarde'],
            4 => ['manha', 'tarde'],
            5 => ['manha', 'tarde'],
            6 => ['manha'],
        ]),
        'antecedencia_dias' => (int) ajuste('antecedencia_dias', 1),
        'janela_meses' => 12,
    ];
}

/* ---------- Datas ---------- */

function data_valida(string $d): bool
{
    $dt = DateTime::createFromFormat('!Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function data_extenso(string $d, bool $comDiaSemana = true): string
{
    $t = strtotime($d);
    if (!$t) {
        return $d;
    }
    $txt = (int) date('j', $t) . ' de ' . mb_strtolower(MESES[(int) date('n', $t)]) . ' de ' . date('Y', $t);
    return $comDiaSemana ? mb_strtolower(DIAS_SEMANA[(int) date('w', $t)]) . ', ' . $txt : $txt;
}

/* ---------- Disponibilidade ---------- */

/** Pessoas já reservadas (aguardando + confirmadas) num dia/turno. */
function ocupacao(string $data, string $turno, ?int $ignorarId = null): int
{
    $sql = "SELECT COALESCE(SUM(pessoas), 0) FROM agendamentos
            WHERE tipo = 'visita' AND data = ? AND horario = ? AND status IN ('pendente', 'confirmada')";
    $args = [$data, $turno];
    if ($ignorarId) {
        $sql .= ' AND id <> ?';
        $args[] = $ignorarId;
    }
    $st = db()->prepare($sql);
    $st->execute($args);
    return (int) $st->fetchColumn();
}

function bloqueios_do_dia(string $data): array
{
    $st = db()->prepare('SELECT turno, motivo FROM bloqueios WHERE data = ?');
    $st->execute([$data]);
    return $st->fetchAll();
}

/**
 * Disponibilidade pública de um dia.
 * Retorna ['aberto' => bool, 'motivo' => ?string, 'turnos' => [turno => ['aberto' => bool, 'vagas' => int]]]
 */
function disponibilidade(string $data, ?int $ignorarId = null): array
{
    $cfg = agenda_config();
    $hoje = date('Y-m-d');
    $min = date('Y-m-d', strtotime("+{$cfg['antecedencia_dias']} day"));
    $max = date('Y-m-d', strtotime("+{$cfg['janela_meses']} month"));
    $res = ['data' => $data, 'aberto' => false, 'motivo' => null, 'turnos' => []];

    if ($data < $min) {
        $res['motivo'] = $data < $hoje ? 'Data já passou.' : 'Agendamentos com pelo menos ' . $cfg['antecedencia_dias'] . ' dia de antecedência.';
        return $res;
    }
    if ($data > $max) {
        $res['motivo'] = 'Agenda aberta até ' . date('d/m/Y', strtotime($max)) . '.';
        return $res;
    }

    $dow = (int) date('w', strtotime($data));
    $turnosDia = $cfg['dias'][$dow] ?? $cfg['dias'][(string) $dow] ?? [];
    $bloq = bloqueios_do_dia($data);
    $bloqTurnos = [];
    foreach ($bloq as $b) {
        if ($b['turno'] === 'dia') {
            $bloqTurnos = array_keys(TURNOS);
        } else {
            $bloqTurnos[] = $b['turno'];
        }
    }

    foreach (TURNOS as $t => $_) {
        $aberto = in_array($t, $turnosDia, true) && !in_array($t, $bloqTurnos, true);
        $vagas = $aberto ? max(0, (int) ($cfg['limite'][$t] ?? 0) - ocupacao($data, $t, $ignorarId)) : 0;
        $res['turnos'][$t] = ['aberto' => $aberto && $vagas > 0, 'vagas' => $vagas];
        if ($aberto && $vagas > 0) {
            $res['aberto'] = true;
        }
    }

    if (!$res['aberto']) {
        $motivoBloq = array_values(array_filter(array_column($bloq, 'motivo')));
        if (!$turnosDia) {
            $res['motivo'] = 'Não recebemos visitas neste dia da semana.';
        } elseif ($bloq && count(array_unique($bloqTurnos)) >= count($turnosDia)) {
            $res['motivo'] = 'Dia fechado para visitas' . ($motivoBloq ? ' (' . $motivoBloq[0] . ')' : '') . '.';
        } else {
            $res['motivo'] = 'Sem vagas neste dia.';
        }
    }
    return $res;
}

/* ---------- E-mails ao visitante ---------- */

function email_confirmacao(array $a): array
{
    $primeiro = explode(' ', trim($a['nome']))[0];
    $quando = data_extenso($a['data']);
    $linhas = [
        "Olá, {$primeiro}!",
        '',
        $a['tipo'] === 'visita'
            ? 'Sua visita à Vinícola Quarezemin está confirmada. Estamos esperando por você na cantina de pedra.'
            : 'Sua data na Vinícola Quarezemin está confirmada. Em breve falamos dos detalhes do evento.',
        '',
        'Data: ' . $quando,
    ];
    if ($a['horario']) {
        $linhas[] = 'Horário: ' . (TURNOS[$a['horario']] ?? $a['horario']);
    }
    $linhas[] = ($a['tipo'] === 'visita' ? 'Experiência: ' . (EXPERIENCIAS[$a['experiencia']] ?? $a['experiencia']) : 'Evento: ' . (TIPOS_EVENTO[$a['experiencia']] ?? $a['experiencia']));
    $linhas[] = ($a['tipo'] === 'visita' ? 'Pessoas: ' : 'Convidados: ') . (int) $a['pessoas'];
    array_push(
        $linhas,
        '',
        'Endereço: ' . ENDERECO_CANTINA,
        'Como chegar: ' . MAPA_CANTINA,
        '',
        'Precisa mudar alguma coisa? Fale com a gente pelo WhatsApp ' . WHATSAPP_CANTINA . '.',
        '',
        'Até breve,',
        'Família Quarezemin',
    );
    $assunto = ($a['tipo'] === 'visita' ? 'Visita confirmada' : 'Data confirmada') . ' · Vinícola Quarezemin · ' . date('d/m/Y', strtotime($a['data']));
    return [$assunto, implode("\n", $linhas)];
}

function email_cancelamento(array $a): array
{
    $primeiro = explode(' ', trim($a['nome']))[0];
    $texto = implode("\n", [
        "Olá, {$primeiro}.",
        '',
        'Infelizmente não conseguiremos receber o seu agendamento de ' . data_extenso($a['data'])
            . ($a['horario'] ? ', ' . mb_strtolower(TURNOS_CURTO[$a['horario']] ?? '') : '') . '.',
        '',
        'Que tal escolher outra data? Agende pelo site ou fale com a gente pelo WhatsApp ' . WHATSAPP_CANTINA . '.',
        rtrim((string) config('site_url'), '/') . '/visitas-eventos.html',
        '',
        'Família Quarezemin',
    ]);
    return ['Sobre o seu agendamento · Vinícola Quarezemin', $texto];
}

/* ---------- Trava da agenda ---------- */

/** Serializa quem verifica vagas e grava, em MySQL (GET_LOCK) e SQLite (BEGIN IMMEDIATE). */
function agenda_lock(PDO $pdo): void
{
    if (db_driver($pdo) === 'mysql') {
        $pdo->query("SELECT GET_LOCK('quarezemin_agenda', 10)")->fetchColumn();
    } else {
        $pdo->exec('BEGIN IMMEDIATE');
    }
}

function agenda_unlock(PDO $pdo): void
{
    if (db_driver($pdo) === 'mysql') {
        $pdo->query("SELECT RELEASE_LOCK('quarezemin_agenda')")->fetchColumn();
    } else {
        $pdo->exec('COMMIT');
    }
}
