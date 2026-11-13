<?php
/**
 * core/detalle.php
 *
 * Detalle completo de un partido de Highlightly (1 petición): cronología, estadísticas, xG,
 * árbitro, estadio, noticias y las probabilidades del modelo de Highlightly.
 * Lo comparten el clima, el resumen del partido y la comparación de modelos.
 * Caché: 24 h si ya terminó, 5 min si está cerca de jugarse, 6 h si falta mucho.
 */
require_once __DIR__ . '/alineaciones.php';
require_once __DIR__ . '/partidos.php';

function hlProbabilidad($texto): ?int
{
    if ($texto === null || $texto === '') {
        return null;
    }
    return (int) round((float) str_replace('%', '', (string) $texto));
}

function hlTtlDetalle(array $d): int
{
    if (($d['estado'] ?? '') === 'final') {
        return 86400;
    }
    $cerca = abs((int) ($d['inicio'] ?? 0) - time()) <= 3 * 3600;
    return $cerca ? 300 : 6 * 3600;
}

function hlDetalleCompleto(?string $apiKey, int $matchId): ?array
{
    if (!$apiKey || !$matchId) {
        return null;
    }

    $clave = 'detalle2_' . $matchId;
    $c = alCacheLeer($clave, 86400);
    if ($c['hit'] && is_array($c['dato']) && alCacheEdad($clave) < hlTtlDetalle($c['dato'])) {
        return $c['dato'];
    }

    $r = hlLlamar("https://soccer.highlightly.net/matches/$matchId", $apiKey);
    if ($r['http'] !== 200) {
        return ($c['hit'] && is_array($c['dato'])) ? $c['dato'] : null;
    }

    $d = json_decode((string) $r['body'], true);
    if (is_array($d) && isset($d[0]) && is_array($d[0])) {
        $d = $d[0];
    }
    if (!is_array($d)) {
        return null;
    }

    $homeId = (int) ($d['homeTeam']['id'] ?? 0);

    $stats = [];
    foreach ($d['statistics'] ?? [] as $bloque) {
        $lado = ((int) ($bloque['team']['id'] ?? 0) === $homeId) ? 0 : 1;
        foreach ($bloque['statistics'] ?? [] as $s) {
            $nombre = (string) ($s['displayName'] ?? '');
            if ($nombre !== '') {
                $stats[$nombre][$lado] = $s['value'] ?? null;
            }
        }
    }

    $eventos = [];
    foreach ($d['events'] ?? [] as $e) {
        $eventos[] = [
            'minuto'     => (string) ($e['time'] ?? ''),
            'tipo'       => (string) ($e['type'] ?? ''),
            'jugador'    => (string) ($e['player'] ?? ''),
            'asistencia' => (string) ($e['assist'] ?? ''),
            'cambio'     => (string) ($e['substituted'] ?? ''),
            'lado'       => ((int) ($e['team']['id'] ?? 0) === $homeId) ? 0 : 1,
        ];
    }

    $prediccion = null;
    $prematch = $d['predictions']['prematch'] ?? [];
    if ($prematch) {
        $ultima = end($prematch);
        $p = [
            'local'     => hlProbabilidad($ultima['probabilities']['home'] ?? null),
            'empate'    => hlProbabilidad($ultima['probabilities']['draw'] ?? null),
            'visitante' => hlProbabilidad($ultima['probabilities']['away'] ?? null),
        ];
        if ($p['local'] !== null && $p['empate'] !== null && $p['visitante'] !== null) {
            $prediccion = $p;
        }
    }

    $noticias = [];
    foreach (array_slice($d['news'] ?? [], 0, 6) as $n) {
        $noticias[] = [
            'titulo' => (string) ($n['title'] ?? ''),
            'url'    => (string) ($n['url'] ?? ''),
            'fecha'  => (string) ($n['datePublished'] ?? ''),
        ];
    }

    $destacados = [[], []];
    foreach ([0 => 'homeTeam', 1 => 'awayTeam'] as $lado => $campo) {
        foreach ($d[$campo]['topPlayers'] ?? [] as $j) {
            $lineas = [];
            foreach ($j['statistics'] ?? [] as $st) {
                $lineas[] = ($st['name'] ?? '') . ': ' . ($st['value'] ?? '');
            }
            $destacados[$lado][] = [
                'nombre'    => (string) ($j['name'] ?? ''),
                'posicion'  => (string) ($j['position'] ?? ''),
                'estadisticas' => implode(' · ', $lineas),
            ];
        }
    }

    $gl = null;
    $gv = null;
    if (preg_match('/(\d+)\s*-\s*(\d+)/', (string) ($d['state']['score']['current'] ?? ''), $g)) {
        $gl = (int) $g[1];
        $gv = (int) $g[2];
    }

    $res = [
        'id'        => (int) ($d['id'] ?? $matchId),
        'inicio'    => (int) strtotime((string) ($d['date'] ?? '')),
        'estado'    => ptEstadoHL((string) ($d['state']['description'] ?? '')),
        'minuto'    => $d['state']['clock'] ?? null,
        'gl'        => $gl,
        'gv'        => $gv,
        'liga'      => (string) ($d['league']['name'] ?? ''),
        'ronda'     => (string) ($d['round'] ?? ''),
        'local'     => ['id' => $homeId, 'nombre' => (string) ($d['homeTeam']['name'] ?? ''), 'logo' => (string) ($d['homeTeam']['logo'] ?? '')],
        'visitante' => ['id' => (int) ($d['awayTeam']['id'] ?? 0), 'nombre' => (string) ($d['awayTeam']['name'] ?? ''), 'logo' => (string) ($d['awayTeam']['logo'] ?? '')],
        'estadio'   => [
            'nombre'    => $d['venue']['name'] ?? null,
            'ciudad'    => $d['venue']['city'] ?? null,
            'pais'      => $d['venue']['country'] ?? null,
            'capacidad' => $d['venue']['capacity'] ?? null,
        ],
        'arbitro'    => ['nombre' => $d['referee']['name'] ?? null, 'pais' => $d['referee']['nationality'] ?? null],
        'eventos'    => $eventos,
        'stats'      => $stats,
        'prediccion' => $prediccion,
        'noticias'   => $noticias,
        'destacados' => $destacados,
    ];

    alCacheEscribir($clave, $res);
    return $res;
}