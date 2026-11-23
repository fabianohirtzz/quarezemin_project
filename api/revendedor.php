<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_out(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$in = json_input();

// Honeypot: robôs preenchem o campo escondido. Fingimos sucesso.
if (!empty($in['site'])) {
    json_out(['ok' => true]);
}

$tipos = [
    'Mercado ou supermercado',
    'Empório ou loja de vinhos',
    'Restaurante, bar ou hotel',
    'Distribuidor ou representante',
    'Outro',
];
$ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

$d = [
    'nome'     => clean_text($in['nome'] ?? '', 120),
    'empresa'  => clean_text($in['empresa'] ?? '', 160),
    'cnpj'     => clean_text($in['cnpj'] ?? '', 18),
    'whatsapp' => only_digits(clean_text($in['whatsapp'] ?? '', 20)),
    'email'    => mb_strtolower(clean_text($in['email'] ?? '', 160)),
    'cidade'   => clean_text($in['cidade'] ?? '', 100),
    'uf'       => strtoupper(clean_text($in['uf'] ?? '', 2)),
    'tipo'     => clean_text($in['tipo'] ?? '', 60),
    'mensagem' => clean_text($in['mensagem'] ?? '', 1500),
];

foreach (['nome', 'empresa', 'whatsapp', 'email', 'cidade', 'uf', 'tipo'] as $campo) {
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
if ($d['cnpj'] !== '') {
    $d['cnpj'] = format_cnpj($d['cnpj']);
    if ($d['cnpj'] === '') {
        json_out(['ok' => false, 'error' => 'O CNPJ precisa ter 14 números.'], 422);
    }
}
if (!in_array($d['uf'], $ufs, true) || !in_array($d['tipo'], $tipos, true)) {
    json_out(['ok' => false, 'error' => 'Confira o estado e o tipo de estabelecimento.'], 422);
}

if (!rate_limit('revendedor', 5, 60)) {
    json_out(['ok' => false, 'error' => 'Recebemos muitas fichas deste endereço. Tente novamente mais tarde ou fale pelo WhatsApp.'], 429);
}

$agora = now();
$st = db()->prepare(
    'INSERT INTO revendedores (nome, empresa, cnpj, whatsapp, email, cidade, uf, tipo, mensagem, situacao, criado_em, atualizado_em)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$st->execute([
    $d['nome'], $d['empresa'], $d['cnpj'] ?: null, $d['whatsapp'], $d['email'],
    $d['cidade'], $d['uf'], $d['tipo'], $d['mensagem'] ?: null, 'novo', $agora, $agora,
]);
$id = (int) db()->lastInsertId();

$texto = implode("\n", [
    'Nova ficha de revendedor recebida pelo site.',
    '',
    'Nome: ' . $d['nome'],
    'Empresa: ' . $d['empresa'],
    'CNPJ: ' . ($d['cnpj'] ?: '-'),
    'WhatsApp: ' . format_whatsapp($d['whatsapp']),
    'E-mail: ' . $d['email'],
    'Cidade: ' . $d['cidade'] . '/' . $d['uf'],
    'Tipo: ' . $d['tipo'],
    'Mensagem: ' . ($d['mensagem'] ?: '-'),
    '',
    'Ver no painel: ' . rtrim((string) config('site_url'), '/') . '/painel/revendedor.php?id=' . $id,
]);
// Falha de e-mail não pode perder a ficha: ela já está salva no banco.
@send_mail((array) config('mail_notify'), 'Novo revendedor: ' . $d['empresa'] . ' (' . $d['cidade'] . '/' . $d['uf'] . ')', $texto);

json_out(['ok' => true]);
