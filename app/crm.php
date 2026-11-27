<?php
declare(strict_types=1);

/**
 * Integração com o CRM Simples (https://app.crmsimples.com.br/docs-api).
 *
 * Cada ficha de revendedor vira um contato no CRM pelo método saveContato.
 * O idExterno "site-revendedor-{id}" faz o reenvio atualizar o mesmo contato
 * em vez de duplicar. Sem 'crm_token' no app/config.php nada é enviado.
 */

const CRM_URL = 'https://api.crmsimples.com.br/API?method=saveContato';

function crm_ativo(): bool
{
    return trim((string) config('crm_token')) !== '';
}

/**
 * Envia a ficha ao CRM e grava o resultado na própria ficha.
 * Retorna ['ok' => bool, 'msg' => string]. Nunca lança exceção:
 * uma falha do CRM não pode atrapalhar o cadastro no site.
 */
function crm_enviar_revendedor(int $id): array
{
    if (!crm_ativo()) {
        return ['ok' => false, 'msg' => 'Integração com o CRM desligada (sem token).'];
    }
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM revendedores WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        return ['ok' => false, 'msg' => 'Ficha não encontrada.'];
    }

    $res = crm_post(crm_payload_revendedor($r));

    if ($res['ok']) {
        $pdo->prepare('UPDATE revendedores SET crm_id = ?, crm_em = ?, crm_erro = NULL WHERE id = ?')
            ->execute([$res['id'] !== '' ? $res['id'] : 'ok', now(), $id]);
    } else {
        $pdo->prepare('UPDATE revendedores SET crm_erro = ?, crm_em = ? WHERE id = ?')
            ->execute([mb_substr($res['msg'], 0, 255), now(), $id]);
    }
    return $res;
}

function crm_payload_revendedor(array $r): array
{
    $cnpj = only_digits((string) ($r['cnpj'] ?? ''));
    $obs = [
        'Ficha recebida pelo site (Seja Revendedor) em ' . date('d/m/Y H:i', strtotime((string) $r['criado_em'])) . '.',
        'Responsável: ' . $r['nome'],
        'Tipo de estabelecimento: ' . $r['tipo'],
    ];
    if (!empty($r['mensagem'])) {
        $obs[] = 'Mensagem: ' . $r['mensagem'];
    }

    $p = [
        'idExterno'     => 'site-revendedor-' . $r['id'],
        'nome'          => $r['empresa'],
        'tipoPessoa'    => 'Jurídica',
        'fonteContato'  => (string) (config('crm_fonte') ?: 'Site'),
        'observacoes'   => implode("\n", $obs),
        'listEndereco'  => [[
            'selectTipoEndereco' => 'Comercial',
            'cidade'             => $r['cidade'],
            'uf'                 => $r['uf'],
        ]],
        'listFone'      => [[
            'descricao'  => format_whatsapp($r['whatsapp']),
            'selectTipo' => 'WhatsApp',
        ]],
        'listEmail'     => [[
            'descricao'  => $r['email'],
            'selectTipo' => 'Comercial',
        ]],
    ];
    if ($cnpj !== '') {
        $p['cnpjCpf'] = $cnpj;
    }
    $status = trim((string) config('crm_status'));
    if ($status !== '') {
        $p['statusContato'] = $status;
    }
    return $p;
}

/** POST JSON para o CRM. Timeout curto: o visitante está esperando a resposta do formulário. */
function crm_post(array $payload): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'id' => '', 'msg' => 'Extensão cURL do PHP indisponível.'];
    }
    $ch = curl_init(CRM_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => [
            'token: ' . trim((string) config('crm_token')),
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'id' => '', 'msg' => 'Sem resposta do CRM: ' . $err];
    }
    $json = json_decode((string) $body, true);
    $id = is_array($json) ? (string) ($json['idInterno'] ?? $json['id'] ?? '') : '';

    // 200 = salvo; 210 = salvo com alerta (ex.: campo ignorado)
    if ($code === 200 || $code === 210) {
        return ['ok' => true, 'id' => $id, 'msg' => $code === 210 ? 'Enviado com alerta: ' . crm_resumo($json, $body) : 'Enviado ao CRM.'];
    }
    if ($code === 405 || $code === 401) {
        return ['ok' => false, 'id' => '', 'msg' => 'Token do CRM recusado (HTTP ' . $code . ').'];
    }
    return ['ok' => false, 'id' => '', 'msg' => 'CRM respondeu HTTP ' . $code . ': ' . crm_resumo($json, $body)];
}

function crm_resumo($json, $body): string
{
    if (is_array($json)) {
        foreach (['mensagem', 'message', 'msg', 'erro', 'error'] as $k) {
            if (!empty($json[$k]) && is_string($json[$k])) {
                return mb_substr($json[$k], 0, 200);
            }
        }
    }
    return mb_substr(trim(strip_tags((string) $body)), 0, 200);
}
