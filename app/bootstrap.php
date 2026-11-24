<?php
declare(strict_types=1);

/*
 * Núcleo do backend Vinhos Quarezemin.
 * Incluído por api/*.php e painel/*.php. Sem framework: PDO + sessões nativas.
 */

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

function config(string $key)
{
    static $cfg = null;
    if ($cfg === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('Configuração ausente: copie app/config.example.php para app/config.php');
        }
        $cfg = require $file;
    }
    return $cfg[$key] ?? null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO((string) config('db_dsn'), (string) config('db_user'), (string) config('db_pass'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if (db_driver($pdo) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        migrate($pdo);
    }
    return $pdo;
}

function db_driver(PDO $pdo): string
{
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/** Cria as tabelas que ainda não existem. Idempotente. */
function migrate(PDO $pdo): void
{
    $sqlite = db_driver($pdo) === 'sqlite';
    $id = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $tail = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $tables = [
        "CREATE TABLE IF NOT EXISTS usuarios (
            id $id,
            nome VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL UNIQUE,
            senha_hash VARCHAR(255) NOT NULL,
            ultimo_acesso DATETIME NULL,
            criado_em DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS revendedores (
            id $id,
            nome VARCHAR(120) NOT NULL,
            empresa VARCHAR(160) NOT NULL,
            cnpj VARCHAR(18) NULL,
            whatsapp VARCHAR(20) NOT NULL,
            email VARCHAR(160) NOT NULL,
            cidade VARCHAR(100) NOT NULL,
            uf CHAR(2) NOT NULL,
            tipo VARCHAR(60) NOT NULL,
            mensagem TEXT NULL,
            situacao VARCHAR(20) NOT NULL DEFAULT 'novo',
            notas TEXT NULL,
            criado_em DATETIME NOT NULL,
            atualizado_em DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS agendamentos (
            id $id,
            tipo VARCHAR(10) NOT NULL,
            nome VARCHAR(120) NOT NULL,
            whatsapp VARCHAR(20) NOT NULL,
            email VARCHAR(160) NOT NULL,
            experiencia VARCHAR(40) NOT NULL,
            pessoas INT NOT NULL,
            data DATE NOT NULL,
            horario VARCHAR(10) NULL,
            observacoes TEXT NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'pendente',
            notas TEXT NULL,
            confirmado_em DATETIME NULL,
            criado_em DATETIME NOT NULL,
            atualizado_em DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS bloqueios (
            id $id,
            data DATE NOT NULL,
            turno VARCHAR(10) NOT NULL DEFAULT 'dia',
            motivo VARCHAR(120) NULL,
            criado_em DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS eventos (
            id $id,
            titulo VARCHAR(120) NOT NULL,
            linha VARCHAR(80) NULL,
            data DATE NOT NULL,
            horario VARCHAR(20) NULL,
            local VARCHAR(80) NULL,
            info VARCHAR(60) NULL,
            descricao TEXT NOT NULL,
            foto VARCHAR(200) NULL,
            publicado TINYINT NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL,
            atualizado_em DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS reels (
            id $id,
            titulo VARCHAR(80) NOT NULL,
            instagram VARCHAR(200) NULL,
            video VARCHAR(200) NULL,
            capa VARCHAR(200) NULL,
            descricao_capa VARCHAR(160) NULL,
            ordem INT NOT NULL DEFAULT 0,
            ativo TINYINT NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS ajustes (
            chave VARCHAR(60) NOT NULL PRIMARY KEY,
            valor TEXT NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS tentativas (
            id $id,
            acao VARCHAR(30) NOT NULL,
            ip_hash CHAR(64) NOT NULL,
            criado_em DATETIME NOT NULL
        )$tail",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash('sha256', config('ip_salt') . '|' . $ip);
}

/**
 * Limite simples por IP: no máximo $max ações em $minutos.
 * Retorna true se ainda pode; registra a tentativa quando $registrar.
 */
function rate_limit(string $acao, int $max, int $minutos, bool $registrar = true): bool
{
    $pdo = db();
    $desde = date('Y-m-d H:i:s', time() - $minutos * 60);
    $pdo->prepare('DELETE FROM tentativas WHERE criado_em < ?')
        ->execute([date('Y-m-d H:i:s', time() - 86400)]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM tentativas WHERE acao = ? AND ip_hash = ? AND criado_em >= ?');
    $st->execute([$acao, ip_hash(), $desde]);
    if ((int) $st->fetchColumn() >= $max) {
        return false;
    }
    if ($registrar) {
        $pdo->prepare('INSERT INTO tentativas (acao, ip_hash, criado_em) VALUES (?, ?, ?)')
            ->execute([$acao, ip_hash(), now()]);
    }
    return true;
}

function clear_attempts(string $acao): void
{
    db()->prepare('DELETE FROM tentativas WHERE acao = ? AND ip_hash = ?')->execute([$acao, ip_hash()]);
}

/* ---------- Texto ---------- */

function clean_text($v, int $max): string
{
    $v = is_string($v) ? $v : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim(preg_replace('/[ \t]+/u', ' ', $v) ?? '');
    return mb_substr($v, 0, $max);
}

function only_digits(string $v): string
{
    return preg_replace('/\D+/', '', $v) ?? '';
}

function format_whatsapp(string $digits): string
{
    $d = only_digits($digits);
    if (strlen($d) > 11 && str_starts_with($d, '55')) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11) {
        return sprintf('(%s) %s %s-%s', substr($d, 0, 2), $d[2], substr($d, 3, 4), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return $d;
}

function format_cnpj(string $v): string
{
    $d = only_digits($v);
    if (strlen($d) !== 14) {
        return '';
    }
    return sprintf('%s.%s.%s/%s-%s', substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12));
}

function whatsapp_link(string $tel, string $msg = ''): string
{
    $d = only_digits($tel);
    if (!str_starts_with($d, '55')) {
        $d = '55' . $d;
    }
    return 'https://api.whatsapp.com/send?phone=' . $d . ($msg !== '' ? '&text=' . rawurlencode($msg) : '');
}

/* ---------- E-mail ---------- */

function send_mail(array $to, string $subject, string $text): bool
{
    $driver = config('mail_driver');
    if ($driver === 'log') {
        $log = sprintf("[%s] Para: %s\nAssunto: %s\n\n%s\n%s\n", now(), implode(', ', $to), $subject, $text, str_repeat('-', 60));
        return file_put_contents(__DIR__ . '/storage/mail.log', $log, FILE_APPEND | LOCK_EX) !== false;
    }
    $from = (string) config('mail_from');
    $fromName = '=?UTF-8?B?' . base64_encode((string) config('mail_from_name')) . '?=';
    $headers = [
        'From: ' . $fromName . ' <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = true;
    foreach ($to as $addr) {
        $ok = mail($addr, $subj, $text, implode("\r\n", $headers), '-f' . $from) && $ok;
    }
    return $ok;
}

/* ---------- JSON (api) ---------- */

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_input(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 20000) ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    return $data;
}
