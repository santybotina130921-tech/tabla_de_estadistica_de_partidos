<?php
require_once __DIR__ . '/config/database.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$liga = (string) ($_GET['liga'] ?? '');
if ($liga === '' || $liga === 'TODAS') {
    echo json_encode(['error' => 'Liga no válida.']);
    exit;
}

try {
    $st = $conexion->prepare(
        "SELECT equipo_nombre, posicion_tabla, partidos_jugados, ganados, empatados, perdidos,
                goles_favor, goles_contra, puntos, actualizado_en
         FROM estadisticas_reales
         WHERE liga_id = ?
         ORDER BY posicion_tabla ASC"
    );
    $st->execute([$liga]);
    $filas = [];
    $ultima = null;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $filas[] = [
            'equipo' => $f['equipo_nombre'],
            'pos'    => $f['posicion_tabla'] === null ? null : (int) $f['posicion_tabla'],
            'pj'     => (int) $f['partidos_jugados'],
            'g'      => (int) $f['ganados'],
            'e'      => (int) $f['empatados'],
            'p'      => (int) $f['perdidos'],
            'gf'     => (int) $f['goles_favor'],
            'gc'     => (int) $f['goles_contra'],
            'pts'    => $f['puntos'] === null ? null : (int) $f['puntos'],
        ];
        if ($ultima === null || $f['actualizado_en'] > $ultima) {
            $ultima = $f['actualizado_en'];
        }
    }
    $actualizado = $ultima ? (new DateTime($ultima))->setTimezone(new DateTimeZone('America/Bogota'))->format('d/m H:i') : null;
    echo json_encode(['filas' => $filas, 'actualizado' => $actualizado], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('tabla_json: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo leer la tabla.']);
}