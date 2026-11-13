<?php
require_once __DIR__ . '/ligas.php';
require_once __DIR__ . '/alineaciones.php';

function ptPresupuesto(int $maximo = 60): bool
{
    $clave = 'presupuesto_partidos_' . gmdate('Ymd');
    $c = alCacheLeer($clave, 86400);
    $usado = $c['hit'] ? (int) $c['dato'] : 0;
    if ($usado >= $maximo) {
        return false;
    }
    alCacheEscribir($clave, $usado + 1);
    return true;
}

function ptEstadoFD(string $estado): string
{
    if (in_array($estado, ['IN_PLAY', 'PAUSED', 'LIVE'], true)) {
        return 'vivo';
    }
    if (in_array($estado, ['FINISHED', 'AWARDED'], true)) {
        return 'final';
    }
    if (in_array($estado, ['POSTPONED', 'SUSPENDED', 'CANCELLED'], true)) {
        return 'aplazado';
    }
    return 'programado';
}

function ptEstadoHL(string $descripcion): string
{
    $d = strtolower($descripcion);
    if ($d === '' || strpos($d, 'not started') !== false) {
        return 'programado';
    }
    if (strpos($d, 'finish') !== false || strpos($d, 'full time') !== false || strpos($d, 'ended') !== false) {
        return 'final';
    }
    if (strpos($d, 'postpon') !== false || strpos($d, 'cancel') !== false || strpos($d, 'abandon') !== false || strpos($d, 'suspend') !== false) {
        return 'aplazado';
    }
    return 'vivo';
}

function ptHayAccion(array $partidos): bool
{
    $ahora = time();
    foreach ($partidos as $p) {
        if (($p['estado'] ?? '') === 'vivo') {
            return true;
        }
        $falta = (int) ($p['inicio'] ?? 0) - $ahora;
        if (($p['estado'] ?? '') === 'programado' && $falta <= 900 && $falta > -10800) {
            return true;
        }
    }
    return false;
}

function ptFootballData(string $desde, string $hasta, bool $soloCache = false): array
{
    if ($soloCache) {
        $guardado = alCacheLeer("fd_partidos_{$desde}_{$hasta}", 7 * 86400);
        return $guardado['hit'] ? $guardado['dato'] + ['edad' => 0] : ['error' => null, 'partidos' => [], 'edad' => 0];
    }

    $token = getenv('FOOTBALL_DATA_API_TOKEN');
    if (!$token) {
        return ['error' => 'Falta FOOTBALL_DATA_API_TOKEN', 'partidos' => [], 'edad' => 0];
    }

    $clave = "fd_partidos_{$desde}_{$hasta}";
    $c = alCacheLeer($clave, 3600);
    $edad = $c['hit'] ? alCacheEdad($clave) : PHP_INT_MAX;
    if ($c['hit'] && is_array($c['dato'])) {
        $limite = ptHayAccion($c['dato']['partidos'] ?? []) ? 60 : 300;
        if ($edad < $limite) {
            $c['dato']['edad'] = $edad;
            return $c['dato'];
        }
    }

    $ch = curl_init("https://api.football-data.org/v4/matches?dateFrom=$desde&dateTo=$hasta");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["X-Auth-Token: $token"],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) {
        if ($c['hit'] && is_array($c['dato'])) {
            $c['dato']['error'] = "football-data.org respondió HTTP $http; se muestran los últimos datos guardados";
            $c['dato']['edad'] = $edad;
            return $c['dato'];
        }
        return ['error' => "football-data.org respondió HTTP $http", 'partidos' => [], 'edad' => 0];
    }

    $d = json_decode((string) $body, true);
    $partidos = [];
    foreach ($d['matches'] ?? [] as $m) {
        $partidos[] = [
            'liga_codigo'    => (string) ($m['competition']['code'] ?? ''),
            'liga'           => (string) ($m['competition']['name'] ?? ''),
            'inicio'         => (int) strtotime((string) ($m['utcDate'] ?? '')),
            'estado'         => ptEstadoFD((string) ($m['status'] ?? '')),
            'minuto'         => $m['minute'] ?? null,
            'local'          => (string) ($m['homeTeam']['name'] ?? ''),
            'local_logo'     => (string) ($m['homeTeam']['crest'] ?? ''),
            'visitante'      => (string) ($m['awayTeam']['name'] ?? ''),
            'visitante_logo' => (string) ($m['awayTeam']['crest'] ?? ''),
            'gl'             => $m['score']['fullTime']['home'] ?? null,
            'gv'             => $m['score']['fullTime']['away'] ?? null,
            'hl_id'          => 0,
        ];
    }

    $res = ['error' => null, 'partidos' => $partidos];
    alCacheEscribir($clave, $res);
    $res['edad'] = 0;
    return $res;
}

function ptHighlightlyLiga(?string $apiKey, string $codigo, array $liga, string $fecha, bool $esHoy, int &$llamadas, int $maxLlamadas, bool $soloCache = false): array
{
    $clave = 'hl_partidos_' . $liga['id'] . '_' . $fecha;
    if ($soloCache) {
        $guardado = alCacheLeer($clave, 7 * 86400);
        return $guardado['hit'] ? $guardado['dato'] + ['edad' => 0] : ['error' => null, 'partidos' => [], 'edad' => 0];
    }
    $c = alCacheLeer($clave, 12 * 3600);
    $edad = $c['hit'] ? alCacheEdad($clave) : PHP_INT_MAX;

    if ($c['hit'] && is_array($c['dato'])) {
        $base = $esHoy ? 3 * 3600 : 12 * 3600;
        $limite = ptHayAccion($c['dato']['partidos'] ?? []) ? 300 : $base;
        if ($edad < $limite) {
            $c['dato']['edad'] = $edad;
            return $c['dato'];
        }
    }

    $cacheViejo = ($c['hit'] && is_array($c['dato'])) ? $c['dato'] + ['edad' => $edad] : null;

    if (!$apiKey) {
        return ['error' => 'Falta HIGHLIGHTLY_API_KEY', 'partidos' => [], 'edad' => 0];
    }
    if ($llamadas >= $maxLlamadas) {
        return $cacheViejo ?: ['error' => 'pendiente', 'partidos' => [], 'edad' => 0];
    }
    if (!ptPresupuesto()) {
        return $cacheViejo ?: ['error' => 'presupuesto', 'partidos' => [], 'edad' => 0];
    }
    $llamadas++;

    $base = 'https://soccer.highlightly.net/matches?';
    $zona = rawurlencode('America/Bogota');
    $intentos = [
        $base . "leagueId={$liga['id']}&date=$fecha&timezone=$zona&limit=100",
        $base . "leagueId={$liga['id']}&date=$fecha&limit=100",
        $base . "countryCode={$liga['pais']}&date=$fecha&limit=100",
    ];

    $r = ['http' => 0, 'body' => ''];
    foreach ($intentos as $url) {
        $r = hlLlamar($url, $apiKey);
        if ($r['http'] !== 400) {
            break;
        }
    }
    if ($r['http'] !== 200) {
        return $cacheViejo ?: ['error' => 'Highlightly respondió HTTP ' . $r['http'], 'partidos' => [], 'edad' => 0];
    }

    $d = json_decode((string) $r['body'], true);
    $partidos = [];
    foreach ($d['data'] ?? [] as $m) {
        if ((int) ($m['league']['id'] ?? 0) !== (int) $liga['id']) {
            continue;
        }
        $gl = null;
        $gv = null;
        if (preg_match('/(\d+)\s*-\s*(\d+)/', (string) ($m['state']['score']['current'] ?? ''), $g)) {
            $gl = (int) $g[1];
            $gv = (int) $g[2];
        }
        $partidos[] = [
            'liga_codigo'    => $codigo,
            'liga'           => $liga['nombre'],
            'inicio'         => (int) strtotime((string) ($m['date'] ?? '')),
            'estado'         => ptEstadoHL((string) ($m['state']['description'] ?? '')),
            'minuto'         => $m['state']['clock'] ?? null,
            'local'          => (string) ($m['homeTeam']['name'] ?? ''),
            'local_logo'     => (string) ($m['homeTeam']['logo'] ?? ''),
            'visitante'      => (string) ($m['awayTeam']['name'] ?? ''),
            'visitante_logo' => (string) ($m['awayTeam']['logo'] ?? ''),
            'gl'             => $gl,
            'gv'             => $gv,
            'hl_id'          => (int) ($m['id'] ?? 0),
        ];
    }

    $res = ['error' => null, 'partidos' => $partidos];
    alCacheEscribir($clave, $res);
    $res['edad'] = 0;
    return $res;
}

function ptHora(int $timestamp): string
{
    return (new DateTime('@' . $timestamp))->setTimezone(new DateTimeZone('America/Bogota'))->format('H:i');
}

function ptEtiquetaFecha(string $fecha): string
{
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $f = new DateTime($fecha, new DateTimeZone('America/Bogota'));
    return $dias[(int) $f->format('w')] . ' ' . $f->format('j') . ' de ' . $meses[(int) $f->format('n') - 1];
}

function ptPartidosDelDia(string $dia, bool $soloCache = false): array
{
    $zona = new DateTimeZone('America/Bogota');
    $base = new DateTime('now', $zona);
    if ($dia === 'ayer') {
        $base->modify('-1 day');
    }
    if ($dia === 'manana') {
        $base->modify('+1 day');
    }
    $fecha = $base->format('Y-m-d');
    $esHoy = ($dia === 'hoy');

    $inicioDia = (new DateTime($fecha . ' 00:00:00', $zona))->getTimestamp();
    $finDia = $inicioDia + 86400;

    $partidos = [];
    $avisos = [];
    $edades = [];

    $fd = ptFootballData(gmdate('Y-m-d', $inicioDia - 86400), gmdate('Y-m-d', $finDia + 86400), $soloCache);
    if ($fd['error']) {
        $avisos[] = $fd['error'];
    }
    $edades[] = (int) ($fd['edad'] ?? 0);
    foreach ($fd['partidos'] as $p) {
        if ($p['inicio'] >= $inicioDia && $p['inicio'] < $finDia) {
            $partidos[] = $p;
        }
    }

    $apiKey = getenv('HIGHLIGHTLY_API_KEY');
    $llamadas = 0;
    $pendientes = 0;
    $sinCupo = false;
    foreach (ligasHighlightly() as $codigo => $liga) {
        $r = ptHighlightlyLiga($apiKey, $codigo, $liga, $fecha, $esHoy, $llamadas, 6, $soloCache);
        if ($r['error'] === 'pendiente') {
            $pendientes++;
            continue;
        }
        if ($r['error'] === 'presupuesto') {
            $sinCupo = true;
            continue;
        }
        if ($r['error']) {
            $avisos[] = $liga['nombre'] . ': ' . $r['error'];
        }
        $edades[] = (int) ($r['edad'] ?? 0);
        foreach ($r['partidos'] as $p) {
            if ($p['inicio'] >= $inicioDia && $p['inicio'] < $finDia) {
                $partidos[] = $p;
            }
        }
    }
    if ($sinCupo) {
        $avisos[] = 'Algunas ligas no se actualizaron para cuidar el cupo diario de la API. Se muestran cuando haya datos guardados.';
    }

    $orden = array_flip(array_keys(ligaEtiquetas()));
    $grupos = [];
    $hayVivo = false;
    foreach ($partidos as $p) {
        $grupos[$p['liga_codigo']]['titulo'] = $p['liga'];
        $grupos[$p['liga_codigo']]['codigo'] = $p['liga_codigo'];
        $grupos[$p['liga_codigo']]['partidos'][] = $p;
        if ($p['estado'] === 'vivo') {
            $hayVivo = true;
        }
    }
    foreach ($grupos as &$g) {
        usort($g['partidos'], fn($a, $b) => $a['inicio'] <=> $b['inicio']);
    }
    unset($g);
    uksort($grupos, fn($a, $b) => ($orden[$a] ?? 999) <=> ($orden[$b] ?? 999));

    return [
        'fecha'      => $fecha,
        'grupos'     => $grupos,
        'avisos'     => $avisos,
        'total'      => count($partidos),
        'pendientes' => $pendientes,
        'hayVivo'    => $hayVivo,
        'edad'       => $edades ? max($edades) : 0,
    ];
}