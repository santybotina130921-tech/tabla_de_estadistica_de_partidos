<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/ligas.php';
require_once __DIR__ . '/core/alineaciones.php';
require_once __DIR__ . '/core/predicciones.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

$claveAdmin = (string) getenv('ADMIN_CLAVE');
if ($claveAdmin === '' || !hash_equals($claveAdmin, (string) ($_GET['clave'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acceso restringido.']);
    exit;
}

set_time_limit(60);

function cronLlamar(string $ruta): array
{
    $ch = curl_init('http://127.0.0.1/' . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 45]);
    $cuerpo = curl_exec($ch);
    curl_close($ch);
    $d = json_decode((string) $cuerpo, true);
    return is_array($d) ? $d : ['ok' => false, 'msg' => 'respuesta no válida'];
}

$europa = ['CL', 'PL', 'PD', 'BL1', 'SA', 'FL1', 'PPL', 'DED', 'BSA', 'ELC'];

$ultimaTabla = [];
foreach ($conexion->query("SELECT liga_id, MAX(actualizado_en) AS u FROM estadisticas_reales GROUP BY liga_id")->fetchAll(PDO::FETCH_ASSOC) as $f) {
    $ultimaTabla[$f['liga_id']] = (int) strtotime((string) $f['u']);
}

$ultimaGoles = [];
try {
    foreach ($conexion->query("SELECT liga_id, MAX(actualizado_en) AS u FROM goleadores GROUP BY liga_id")->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $ultimaGoles[$f['liga_id']] = (int) strtotime((string) $f['u']);
    }
} catch (Throwable $e) {
    $ultimaGoles = [];
}

$tareas = [];
foreach ($europa as $liga) {
    $tareas[] = ['tipo' => 'tabla',     'liga' => $liga, 'intervalo' => 3 * 3600,  'ultima' => $ultimaTabla[$liga] ?? 0,  'ruta' => "core/api_fetch.php?liga=$liga"];
    $tareas[] = ['tipo' => 'goleadores', 'liga' => $liga, 'intervalo' => 12 * 3600, 'ultima' => $ultimaGoles[$liga] ?? 0, 'ruta' => "core/api_fetch.php?liga=$liga&tipo=goleadores"];
}
foreach (array_keys(ligasHighlightly()) as $liga) {
    $tareas[] = ['tipo' => 'tabla', 'liga' => $liga, 'intervalo' => 12 * 3600, 'ultima' => $ultimaTabla[$liga] ?? 0, 'ruta' => "core/api_fetch_highlightly.php?liga=$liga"];
}

foreach ($tareas as $i => $t) {
    $intento = alCacheLeer('cron_intento_' . $t['tipo'] . '_' . $t['liga'], 30 * 86400);
    if ($intento['hit']) {
        $tareas[$i]['ultima'] = max($t['ultima'], (int) $intento['dato']);
    }
}

$vencidas = array_values(array_filter($tareas, fn($t) => time() - $t['ultima'] >= $t['intervalo']));
usort($vencidas, fn($a, $b) => $a['ultima'] <=> $b['ultima']);

$hecho = null;
if ($vencidas) {
    $t = $vencidas[0];
    $r = cronLlamar($t['ruta']);
    $ok = !empty($r['ok']);
    $errorPermanente = !$ok && preg_match('/HTTP (403|404)/', (string) ($r['msg'] ?? '')) === 1;
    alCacheEscribir('cron_intento_' . $t['tipo'] . '_' . $t['liga'], ($ok || $errorPermanente) ? time() : time() - $t['intervalo'] + 3600);
    $hecho = ['liga' => $t['liga'], 'tipo' => $t['tipo'], 'ok' => $ok, 'msg' => $r['msg'] ?? ''];
}

$resueltos = 0;
try {
    $resueltos = resolverPendientes($conexion, getenv('HIGHLIGHTLY_API_KEY'), 2);
} catch (Throwable $e) {
    error_log('cron: ' . $e->getMessage());
}

echo json_encode([
    'ok'                 => true,
    'hecho'              => $hecho,
    'tareas_pendientes'  => max(count($vencidas) - ($hecho ? 1 : 0), 0),
    'resultados_nuevos'  => $resueltos,
], JSON_UNESCAPED_UNICODE);