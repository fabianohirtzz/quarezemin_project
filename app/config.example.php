<?php
// Copie para app/config.php e ajuste. Nunca versione o config.php.
return [
    // Produção (Erehost): 'mysql:host=localhost;dbname=NOME;charset=utf8mb4'
    // Desenvolvimento local: 'sqlite:' . __DIR__ . '/storage/dev.sqlite'
    'db_dsn'  => 'sqlite:' . __DIR__ . '/storage/dev.sqlite',
    'db_user' => '',
    'db_pass' => '',

    // Endereço público do site, sem barra no final (usado nos e-mails)
    'site_url' => 'http://localhost:8080',

    // E-mail: 'log' grava em app/storage/mail.log (dev); 'mail' usa o mail() do servidor
    'mail_driver' => 'log',
    'mail_from'   => 'site@vinhosquarezemin.com.br',
    'mail_from_name' => 'Site Vinhos Quarezemin',
    // Quem recebe os avisos de nova ficha / nova visita
    'mail_notify' => ['vinhosquazemin@gmail.com'],

    // Sal para anonimizar IPs (gere um valor aleatório longo)
    'ip_salt' => 'troque-por-um-valor-aleatorio',

    // true em produção (cookie de sessão só via HTTPS)
    'secure_cookies' => false,
];
