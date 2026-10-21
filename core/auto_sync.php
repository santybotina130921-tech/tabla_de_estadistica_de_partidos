<?php
/**
 * core/auto_sync.php
 *
 * Sincroniza TODAS las tablas de posiciones solo, sin que tengas que darle
 * clic a los botones SYNC. Pensado para correrlo con el Programador de tareas
 * de Windows (ver instrucciones), pero también sirve abrirlo en el navegador.
 *
 * Usa los endpoints "una liga a la vez" que ya tienes (api_fetch.php y
 * api_fetch_highlightly.php), así que no duplica lógica ni se pasa del tiempo
 * máximo de PHP.
 *
 * Gasto de cupo por corrida: ~12 peticiones de Highlightly (de 100/día) y
 * ~9 de football-data.org (que tiene cupo aparte). Recomendado: 2 veces al día.
 */

// Cambia esto si tu carpeta se llama distinto:
$BASE = 'http://localhost/tabla_de_estadistica_de_partidos/core/';

$europa = ['CL', 'PL', 'PD', 'BL1', 'SA', 'FL1', 'PPL', 'DED', 'BSA'];
$latam  = ['HL_COL', 'HL_ARG', 'HL_CHI', 'HL_URU', 'HL_PAR', 'HL_PER', 'HL_ECU', 'HL_MEX', 'HL_MLS', 'HL_SAU', 'HL_LIB', 'HL_SUD'];

function llamarLocal(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r === false ? '{"ok":false,"msg":"sin respuesta del servidor local (¿Apache apagado?)"}' : $r;
}

function log_linea(string $texto): void
{
    $linea = '[' . date('Y-m-d H:i:s') . '] ' . $texto . PHP_EOL;
    file_put_contents(__DIR__ . '/auto_sync.log', $linea, FILE_APPEND);
    echo $linea;
}

set_time_limit(0);
log_linea('--- Inicio de sincronización automática ---');

foreach ($europa as $liga) {
    $d = json_decode(llamarLocal($BASE . 'api_fetch.php?liga=' . $liga), true);
    log_linea(($d['ok'] ?? false ? 'OK   ' : 'FALLO') . " $liga: " . ($d['msg'] ?? 'respuesta inválida'));
    sleep(7); // football-data.org: máx. ~10 peticiones por minuto
}

foreach ($latam as $liga) {
    $d = json_decode(llamarLocal($BASE . 'api_fetch_highlightly.php?liga=' . $liga), true);
    log_linea(($d['ok'] ?? false ? 'OK   ' : 'FALLO') . " $liga: " . ($d['msg'] ?? 'respuesta inválida'));
    sleep(2);
}

log_linea('--- Fin ---');