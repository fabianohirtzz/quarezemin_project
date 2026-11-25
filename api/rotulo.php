<?php
declare(strict_types=1);

/* Recebe o pedido de rótulo personalizado (multipart, com a arte opcional) e avisa a vinícola por e-mail. */

require __DIR__ . '/../app/rotulos.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_out(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

// Arquivo acima do post_max_size do PHP chega com $_POST vazio.
if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    json_out(['ok' => false, 'error' => 'O arquivo da arte é grande demais. Envie um de até 10 MB.'], 413);
}

// Honeypot: robôs preenchem o campo escondido. Fingimos sucesso.
if (!empty($_POST['site'])) {
    json_out(['ok' => true]);
}

$d = [
    'nome'       => clean_text($_POST['nome'] ?? '', 120),
    'whatsapp'   => only_digits(clean_text($_POST['whatsapp'] ?? '', 20)),
    'email'      => mb_strtolower(clean_text($_POST['email'] ?? '', 160)),
    'empresa'    => clean_text($_POST['empresa'] ?? '', 160),
    'produto'    => clean_text($_POST['vinho'] ?? '', 80),
    'quantidade' => (int) only_digits((string) ($_POST['quantidade'] ?? '')),
    'ocasiao'    => clean_text($_POST['ocasiao'] ?? '', 160),
];

foreach (['nome', 'whatsapp', 'email', 'produto'] as $campo) {
    if ($d[$campo] === '') {
        json_out(['ok' => false, 'error' => 'Preencha nome, WhatsApp, e-mail e o vinho escolhido.'], 422);
    }
}
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
    json_out(['ok' => false, 'error' => 'Confira o e-mail informado.'], 422);
}
if (strlen($d['whatsapp']) < 10 || strlen($d['whatsapp']) > 13) {
    json_out(['ok' => false, 'error' => 'Confira o número de WhatsApp com DDD.'], 422);
}
if (!in_array($d['produto'], ROTULO_PRODUTOS, true)) {
    json_out(['ok' => false, 'error' => 'Escolha um dos vinhos da lista.'], 422);
}
if ($d['quantidade'] < ROTULO_MINIMO || $d['quantidade'] > 100000) {
    json_out(['ok' => false, 'error' => 'O pedido mínimo é de ' . ROTULO_MINIMO . ' unidades.'], 422);
}

if (!rate_limit('rotulo', 5, 60)) {
    json_out(['ok' => false, 'error' => 'Recebemos muitos pedidos deste endereço. Tente mais tarde ou fale pelo WhatsApp.'], 429);
}

$arte = null;
$arteNome = null;
if (isset($_FILES['arte']) && ($_FILES['arte']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    try {
        [$arte, $arteNome] = rotulo_salvar_arte($_FILES['arte']);
    } catch (RuntimeException $e) {
        json_out(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

$agora = now();
db()->prepare(
    'INSERT INTO rotulos (nome, whatsapp, email, empresa, produto, quantidade, ocasiao, arte, arte_nome, situacao, criado_em, atualizado_em)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $d['nome'], $d['whatsapp'], $d['email'], $d['empresa'] ?: null, $d['produto'], $d['quantidade'],
    $d['ocasiao'] ?: null, $arte, $arteNome, 'novo', $agora, $agora,
]);
$id = (int) db()->lastInsertId();

$texto = implode("\n", [
    'Novo pedido de rótulo personalizado recebido pelo site.',
    '',
    'Nome: ' . $d['nome'],
    'WhatsApp: ' . format_whatsapp($d['whatsapp']),
    'E-mail: ' . $d['email'],
    'Empresa / evento: ' . ($d['empresa'] ?: '-'),
    'Vinho: ' . $d['produto'],
    'Quantidade: ' . $d['quantidade'] . ' unidades',
    'Ocasião: ' . ($d['ocasiao'] ?: '-'),
    'Arte: ' . ($arteNome ? $arteNome . ' (baixe no painel)' : 'não enviou'),
    '',
    'Ver no painel: ' . rtrim((string) config('site_url'), '/') . '/painel/rotulo.php?id=' . $id,
]);
// Falha de e-mail não pode perder o pedido: ele já está salvo no banco.
@send_mail((array) config('mail_notify'), 'Rótulo personalizado: ' . $d['produto'] . ' · ' . $d['quantidade'] . ' un. · ' . $d['nome'], $texto);

json_out(['ok' => true]);
