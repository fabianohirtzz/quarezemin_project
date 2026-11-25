<?php
declare(strict_types=1);

/* Horários de visitação mostrados no site (rodapé e página de visitas). */

require __DIR__ . '/../app/agenda.php';

header('Cache-Control: public, max-age=300');
json_out(['ok' => true, 'horarios' => horarios_site()]);
