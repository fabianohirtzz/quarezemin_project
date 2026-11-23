<?php
declare(strict_types=1);

require __DIR__ . '/../app/agenda.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_out(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$in = json_input();
if (!empty($in['site'])) {
    json_out(['ok' => true]);
}

$tipo = ($in['tipo_form'] ?? '') === 'eventos' ? 'evento' : 'visita';

$d = [
    'nome' => clean_text($in['nome'] ?? '', 120),
    'whatsapp' => only_digits(clean_text($in['whatsapp'] ?? '', 20)),
    'email' => mb_strtolower(clean_text($in['email'] ?? '', 160)),
    'data' => clean_text($in['data'] ?? '', 10),
    'observacoes' => clean_text(str_replace(["\r\n", "\r"], "\n", (string) ($in[$tipo === 'visita' ? 'observacoes' : 'descricao'] ?? '')), 1500),
];

if ($tipo === 'visita') {
    $d['experiencia'] = (string) ($in['experiencia'] ?? '');
    $d['pessoas'] = (int) ($in['pessoas'] ?? 0);
    $d['horario'] = (string) ($in['horario'] ?? '');
} else {
    $d['experiencia'] = (string) ($in['tipo'] ?? '');
    $d['pessoas'] = (int) ($in['convidados'] ?? 0);
    $d['horario'] = null;
}

foreach (['nome', 'whatsapp', 'email', 'data'] as $campo) {
    if ($d[$campo] === '') {
        json_out(['ok' => false, 'error' => 'Preencha os campos obrigatórios.'], 422);
    }
}
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Confira o e-mail informado.'], 422);
}
if (strlen($d['whatsapp']) < 10 || strlen($d['whatsapp']) > 13) {
    json_out(['ok' => false, 'error' => 'Confira o número de WhatsApp com DDD.'], 422);
}
if (!data_valida($d['data'])) {
    json_out(['ok' => false, 'error' => 'Confira a data escolhida.'], 422);
}

if ($tipo === 'visita') {
    if (!isset(EXPERIENCIAS[$d['experiencia']]) || !isset(TURNOS[$d['horario']])) {
        json_out(['ok' => false, 'error' => 'Escolha a experiência e o horário.'], 422);
    }
    if ($d['pessoas'] < 1 || $d['pessoas'] > 30) {
        json_out(['ok' => false, 'error' => 'Informe de 1 a 30 pessoas. Para grupos maiores, use a aba Eventos.'], 422);
    }
} else {
    if (!isset(TIPOS_EVENTO[$d['experiencia']])) {
        json_out(['ok' => false, 'error' => 'Escolha o tipo de evento.'], 422);
    }
    if ($d['pessoas'] < 1 || $d['pessoas'] > 2000) {
        json_out(['ok' => false, 'error' => 'Informe o número de convidados.'], 422);
    }
    if ($d['observacoes'] === '') {
        json_out(['ok' => false, 'error' => 'Conte um pouco sobre o evento.'], 422);
    }
    if ($d['data'] < date('Y-m-d', strtotime('+1 day'))) {
        json_out(['ok' => false, 'error' => 'Escolha uma data a partir de amanhã.'], 422);
    }
}

if (!rate_limit('agendamento', 6, 60)) {
    json_out(['ok' => false, 'error' => 'Recebemos muitos pedidos deste endereço. Fale com a gente pelo WhatsApp.'], 429);
}

$pdo = db();
// Verificação de vagas e gravação sob a mesma trava, para dois pedidos
// simultâneos não passarem do limite do turno.
agenda_lock($pdo);
$erro = null;
try {
    if ($tipo === 'visita') {
        $disp = disponibilidade($d['data']);
        $turno = $disp['turnos'][$d['horario']] ?? ['aberto' => false, 'vagas' => 0];
        if (!$disp['aberto'] || !$turno['aberto']) {
            $erro = [$disp['motivo'] ?? 'Este horário não está disponível. Escolha outro.', $disp];
        } elseif ($d['pessoas'] > $turno['vagas']) {
            $erro = ['Restam ' . $turno['vagas'] . ' vagas neste horário. Ajuste o número de pessoas ou escolha outro horário.', $disp];
        }
    }
    if (!$erro) {
        $agora = now();
        $pdo->prepare(
            'INSERT INTO agendamentos (tipo, nome, whatsapp, email, experiencia, pessoas, data, horario, observacoes, status, criado_em, atualizado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $tipo, $d['nome'], $d['whatsapp'], $d['email'], $d['experiencia'], $d['pessoas'],
            $d['data'], $d['horario'], $d['observacoes'] ?: null, 'pendente', $agora, $agora,
        ]);
        $id = (int) $pdo->lastInsertId();
    }
} finally {
    agenda_unlock($pdo);
}
if ($erro) {
    json_out(['ok' => false, 'error' => $erro[0], 'disponibilidade' => $erro[1]], 409);
}

$rotulo = $tipo === 'visita' ? (EXPERIENCIAS[$d['experiencia']] ?? '') : (TIPOS_EVENTO[$d['experiencia']] ?? '');
$texto = implode("\n", array_filter([
    $tipo === 'visita' ? 'Novo pedido de visita recebido pelo site.' : 'Novo pedido de proposta para evento recebido pelo site.',
    '',
    'Nome: ' . $d['nome'],
    'WhatsApp: ' . format_whatsapp($d['whatsapp']),
    'E-mail: ' . $d['email'],
    ($tipo === 'visita' ? 'Experiência: ' : 'Evento: ') . $rotulo,
    ($tipo === 'visita' ? 'Pessoas: ' : 'Convidados: ') . $d['pessoas'],
    'Data: ' . data_extenso($d['data']),
    $d['horario'] ? 'Horário: ' . TURNOS[$d['horario']] : null,
    ($tipo === 'visita' ? 'Observações: ' : 'Descrição: ') . ($d['observacoes'] ?: '-'),
    '',
    'Confirme ou cancele no painel: ' . rtrim((string) config('site_url'), '/') . '/painel/visita.php?id=' . $id,
], fn($l) => $l !== null));
@send_mail(
    (array) config('mail_notify'),
    ($tipo === 'visita' ? 'Nova visita: ' : 'Novo evento: ') . $d['nome'] . ' · ' . date('d/m', strtotime($d['data'])) . ($d['horario'] ? ' ' . TURNOS_CURTO[$d['horario']] : '') . ' · ' . $d['pessoas'] . ' pessoas',
    $texto
);

json_out(['ok' => true, 'tipo' => $tipo]);
