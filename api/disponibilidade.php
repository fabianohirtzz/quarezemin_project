<?php
declare(strict_types=1);

require __DIR__ . '/../app/agenda.php';

$data = (string) ($_GET['data'] ?? '');
if (!data_valida($data)) {
    json_out(['ok' => false, 'error' => 'Data inválida.'], 422);
}

$d = disponibilidade($data);
// Para o público não expomos a lotação exata, só se há lugar e quantos cabem.
json_out(['ok' => true] + $d);
