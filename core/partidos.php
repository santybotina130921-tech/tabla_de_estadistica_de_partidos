<?php
require_once __DIR__ . '/ligas.php';
require_once __DIR__ . '/alineaciones.php';

function ptPresupuesto(int $maximo = 40): bool
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

function ptFootballData(string $desde, string $hasta): array
{
    $token = getenv('FOOTBALL_DATA_API_TOKEN');
    if (!$token) {
        return ['error' => 'Falta FOOTBALL_DATA_API_TOKEN', 'partidos' => []];
    }

    $clave = "fd_partidos_{$desde}_{$hasta}";
    $c = alCacheLeer($clave, 300);
    if ($c['hit']) {
        return $c['dato'];
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
        return ['error' => "football-data.org respondió HTTP $http", 'partidos' => []];
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
        ];
    }

    $res = ['error' => null, 'partidos' => $partidos];
    alCacheEscribir($clave, $res);
    return $res;
}

function ptHighlightlyLiga(?string $apiKey, string $codigo, array $liga, string $fecha, bool $esHoy, int &$llamadas, int $maxLlamadas): array
{
    $clave = 'hl_partidos_' . $liga['id'] . '_' . $fecha;
    $c = alCacheLeer($clave, $esHoy ? 3 * 3600 : 12 * 3600);
    if ($c['hit']) {
        return $c['dato'];
    }
    if (!$apiKey) {
        return ['error' => 'Falta HIGHLIGHTLY_API_KEY', 'partidos' => []];
    }
    if ($llamadas >= $maxLlamadas) {
        return ['error' => 'pendiente', 'partidos' => []];
    }
    if (!ptPresupuesto()) {
        return ['error' => 'presupuesto', 'partidos' => []];
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
        return ['error' => 'Highlightly respondió HTTP ' . $r['http'], 'partidos' => []];
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
        ];
    }

    $res = ['error' => null, 'partidos' => $partidos];
    alCacheEscribir($clave, $res);
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

function ptPartidosDelDia(string $dia): array
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

    $fd = ptFootballData(gmdate('Y-m-d', $inicioDia - 86400), gmdate('Y-m-d', $finDia + 86400));
    if ($fd['error']) {
        $avisos[] = $fd['error'];
    }
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
        $r = ptHighlightlyLiga($apiKey, $codigo, $liga, $fecha, $esHoy, $llamadas, 6);
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
            continue;
        }
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
    ];
}