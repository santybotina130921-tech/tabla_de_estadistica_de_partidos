<?php
/**
 * core/clima.php
 *
 * Clima del próximo partido entre dos equipos, con Open-Meteo (gratis, sin key,
 * solo uso NO comercial; hay que mostrar "Weather data by Open-Meteo.com").
 *
 * Flujo: partido → estadio/ciudad (Highlightly, 1 petición, caché 24 h)
 *        → coordenadas (geocodificación de Open-Meteo, caché 60 días)
 *        → pronóstico por horas hasta 16 días (caché 3 h).
 * Open-Meteo no usa tu cupo de Highlightly; solo el detalle del partido gasta 1.
 */
require_once __DIR__ . '/alineaciones.php'; // trae hlLlamar() y la caché en archivos

/** GET sencillo SIN la key de Highlightly (esto va a otro servidor). */
function clHttpGet(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $body === false ? '' : (string) $body, 'http' => $http];
}

/** Estadio y ciudad del partido (vienen en el detalle del partido de Highlightly). */
function clDetallePartido(?string $apiKey, int $matchId): ?array
{
    if (!$apiKey || !$matchId) {
        return null;
    }
    $clave = 'detalle_' . $matchId;
    $c = alCacheLeer($clave, 24 * 3600);
    if ($c['hit']) {
        return $c['dato'];
    }

    $r = hlLlamar("https://soccer.highlightly.net/matches/$matchId", $apiKey);
    if ($r['http'] !== 200) {
        return null; // los fallos no se guardan
    }
    $d = json_decode((string) $r['body'], true);
    if (is_array($d) && isset($d[0]) && is_array($d[0])) {
        $d = $d[0]; // a veces viene envuelto en un arreglo
    }
    $venue = is_array($d) ? ($d['venue'] ?? null) : null;

    $res = [
        'estadio'   => $venue['name'] ?? null,
        'ciudad'    => $venue['city'] ?? null,
        'pais'      => $venue['country'] ?? null,
        'capacidad' => $venue['capacity'] ?? null,
    ];
    alCacheEscribir($clave, $res);
    return $res;
}

/** Ciudad → coordenadas y zona horaria (geocodificación de Open-Meteo). */
function clCoordenadas(string $ciudad, ?string $pais): ?array
{
    $clave = 'geo_' . strtolower($ciudad . '|' . (string) $pais);
    $c = alCacheLeer($clave, 60 * 24 * 3600);
    if ($c['hit'] && $c['dato']) {
        return $c['dato'];
    }

    $r = clHttpGet('https://geocoding-api.open-meteo.com/v1/search?name=' . urlencode($ciudad) . '&count=10&language=en&format=json');
    if ($r['http'] !== 200) {
        return null;
    }
    $d = json_decode($r['body'], true);
    $resultados = $d['results'] ?? [];
    if (!$resultados) {
        return null;
    }

    // Highlightly escribe el país en inglés ("Spain", "USA"...); igual que la geocodificación
    $alias = [
        'usa' => 'united states', 'england' => 'united kingdom', 'scotland' => 'united kingdom',
        'wales' => 'united kingdom', 'northern ireland' => 'united kingdom',
    ];
    $paisNorm = strtolower(trim((string) $pais));
    $paisNorm = $alias[$paisNorm] ?? $paisNorm;

    $elegido = $resultados[0];
    if ($paisNorm !== '') {
        foreach ($resultados as $x) {
            if (strtolower((string) ($x['country'] ?? '')) === $paisNorm) {
                $elegido = $x;
                break;
            }
        }
    }

    $res = [
        'lat'    => (float) $elegido['latitude'],
        'lon'    => (float) $elegido['longitude'],
        'nombre' => $elegido['name'] ?? $ciudad,
        'tz'     => $elegido['timezone'] ?? 'UTC',
    ];
    alCacheEscribir($clave, $res);
    return $res;
}

/** Pronóstico por horas (UTC) de hoy a 16 días. */
function clPronostico(float $lat, float $lon): ?array
{
    $latTxt = number_format($lat, 4, '.', '');
    $lonTxt = number_format($lon, 4, '.', '');
    $clave = 'pron_' . $latTxt . '_' . $lonTxt;
    $c = alCacheLeer($clave, 3 * 3600);
    if ($c['hit'] && $c['dato']) {
        return $c['dato'];
    }

    $url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $latTxt . '&longitude=' . $lonTxt
         . '&hourly=temperature_2m,apparent_temperature,relative_humidity_2m,precipitation_probability,precipitation,weather_code,wind_speed_10m,wind_gusts_10m'
         . '&timezone=UTC&forecast_days=16&wind_speed_unit=kmh';
    $r = clHttpGet($url);
    if ($r['http'] !== 200) {
        return null;
    }
    $d = json_decode($r['body'], true);
    if (empty($d['hourly']['time'])) {
        return null;
    }
    alCacheEscribir($clave, $d['hourly']);
    return $d['hourly'];
}

/** Resume las 3 horas que dura la parte importante del partido (pitazo + 2 h). */
function clExtraer(array $h, int $inicioTs): ?array
{
    $base = $inicioTs - ($inicioTs % 3600);
    $posiciones = [];
    foreach ([0, 1, 2] as $k) {
        $etiqueta = gmdate('Y-m-d\TH:i', $base + $k * 3600);
        $pos = array_search($etiqueta, $h['time'], true);
        if ($pos !== false) {
            $posiciones[] = $pos;
        }
    }
    if (!$posiciones) {
        return null;
    }

    $primera = $posiciones[0];
    $probLluvia = 0.0;
    $lluviaMm = 0.0;
    $viento = 0.0;
    $racha = 0.0;
    $posMasLluvia = $primera;
    $maxMm = -1.0;

    foreach ($posiciones as $p) {
        $probLluvia = max($probLluvia, (float) ($h['precipitation_probability'][$p] ?? 0));
        $mm = (float) ($h['precipitation'][$p] ?? 0);
        $lluviaMm += $mm;
        if ($mm > $maxMm) {
            $maxMm = $mm;
            $posMasLluvia = $p;
        }
        $viento = max($viento, (float) ($h['wind_speed_10m'][$p] ?? 0));
        $racha  = max($racha, (float) ($h['wind_gusts_10m'][$p] ?? 0));
    }

    $posCodigo = ($lluviaMm >= 0.5) ? $posMasLluvia : $primera;

    return [
        'temp'       => (float) ($h['temperature_2m'][$primera] ?? 0),
        'sensacion'  => (float) ($h['apparent_temperature'][$primera] ?? 0),
        'humedad'    => (float) ($h['relative_humidity_2m'][$primera] ?? 0),
        'prob_lluvia' => $probLluvia,
        'lluvia_mm'  => round($lluviaMm, 1),
        'viento'     => $viento,
        'racha'      => $racha,
        'codigo'     => (int) ($h['weather_code'][$posCodigo] ?? 0),
    ];
}

/** Código WMO de Open-Meteo → emoji + descripción. */
function clDescribirCodigo(int $c): array
{
    if ($c === 0) return ['☀️', 'Despejado'];
    if ($c === 1) return ['🌤️', 'Mayormente despejado'];
    if ($c === 2) return ['⛅', 'Parcialmente nublado'];
    if ($c === 3) return ['☁️', 'Nublado'];
    if ($c === 45 || $c === 48) return ['🌫️', 'Niebla'];
    if ($c >= 51 && $c <= 57) return ['🌦️', 'Llovizna'];
    if ($c >= 61 && $c <= 67) return ['🌧️', 'Lluvia'];
    if ($c >= 71 && $c <= 77) return ['❄️', 'Nieve'];
    if ($c >= 80 && $c <= 82) return ['🌧️', 'Aguaceros'];
    if ($c === 85 || $c === 86) return ['🌨️', 'Chubascos de nieve'];
    if ($c >= 95) return ['⛈️', 'Tormenta eléctrica'];
    return ['🌡️', 'Sin descripción'];
}

/** Avisos simples (contexto, no cambian la predicción). */
function clConsejos(array $t): array
{
    $c = [];
    if ($t['lluvia_mm'] >= 2 || $t['prob_lluvia'] >= 70) {
        $c[] = '🌧️ Lluvia probable: cancha rápida y resbalosa, más errores de control y de pase.';
    } elseif ($t['prob_lluvia'] >= 40) {
        $c[] = '☔ Hay chance de lluvia; vuelve a mirar el pronóstico cerca del partido.';
    }
    if ($t['racha'] >= 40 || $t['viento'] >= 30) {
        $c[] = '💨 Viento fuerte: los balones largos, los centros y los tiros de lejos se complican.';
    }
    if ($t['temp'] >= 30 || $t['sensacion'] >= 32) {
        $c[] = '🥵 Calor alto: más desgaste físico, suele bajar el ritmo en el segundo tiempo.';
    }
    if ($t['temp'] <= 3) {
        $c[] = '🥶 Mucho frío: cancha y balón más duros.';
    }
    if (!$c) {
        $c[] = '✅ Condiciones normales para jugar; el clima no debería ser un factor.';
    }
    $c[] = 'ℹ️ Es solo contexto: no modifica los porcentajes de la predicción.';
    return $c;
}