<?php
/**
 * core/stats_reales.php
 *
 * Trae el promedio REAL (córners, tarjetas, atajadas, posesión, xG) de los
 * últimos partidos jugados de un equipo, usando Highlightly. Guarda el
 * resultado en Supabase (tabla equipo_stats_reales) por $diasCache días
 * para no gastar el cupo diario de la API cada vez que alguien lo consulta.
 *
 * Devuelve null si no se pudo obtener nada real (el que llama debe usar
 * los valores aleatorios de respaldo en ese caso).
 */

function hlLlamar(string $url, string $apiKey): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["x-rapidapi-key: $apiKey"],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $body, 'http' => $http];
}

function hlExtraerStat(array $statsEquipo, string $nombre): ?float
{
    foreach ($statsEquipo as $s) {
        if (($s['displayName'] ?? '') === $nombre) {
            return (float) $s['value'];
        }
    }
    return null;
}

/**
 * Alias manuales SOLO para casos que ni limpiando el nombre se resuelven
 * (ej. nombres en otro idioma, como "FC Bayern München" → "Bayern Munich").
 * Agrega aquí solo lo que veas fallar incluso después de la limpieza
 * automática (usa verificar-equipos.php para confirmarlo).
 */
function hlAliasManual(string $equipoNombre): ?string
{
    $alias = [
        'FC Bayern München' => 'Bayern Munich',
    ];
    return $alias[$equipoNombre] ?? null;
}

/**
 * Genera variantes del nombre para probar contra Highlightly: el nombre tal
 * cual, sin tildes/diéresis, y sin los prefijos/sufijos genéricos de club
 * (FC, AC, SSC, números de fundación, etc.) que Highlightly casi siempre
 * omite mientras que football-data.org sí los incluye.
 */
function hlNombresCandidatos(string $nombre): array
{
    $candidatos = [$nombre];

    $sinTildes = strtr($nombre, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','Ü'=>'U',
        'ä'=>'a','ö'=>'o','Ä'=>'A','Ö'=>'O','ç'=>'c','Ç'=>'C',
        'ã'=>'a','õ'=>'o','Ã'=>'A','Õ'=>'O','ê'=>'e','â'=>'a','à'=>'a','ô'=>'o',
    ]);
    if ($sinTildes !== $nombre) $candidatos[] = $sinTildes;

    $tokens = ['FC','CF','AC','ACF','SSC','AFC','SC','CD','RC','RCD','EC','AA','CA','SE','US','UD',
               'RB','VfB','VfL','TSG','SV','FSV','AJ','ES','AF','FBPA','FBC'];
    $patronTokens = '/\b(' . implode('|', array_map('preg_quote', $tokens)) . ')\b\.?/i';

    foreach ([$nombre, $sinTildes] as $base) {
        $limpio = preg_replace($patronTokens, '', $base);
        $limpio = preg_replace('/\b(18|19|20)\d{2}\b/', '', $limpio); // años (1909, 1913...)
        $limpio = preg_replace('/\b\d{1,2}\b/', '', $limpio);          // números sueltos (04, 07, 29...)
        $limpio = preg_replace('/^\d+\.\s*/', '', $limpio);            // "1. FC Köln" → "FC Köln"
        $limpio = trim(preg_replace('/\s+/', ' ', $limpio));
        if ($limpio && !in_array($limpio, $candidatos, true)) {
            $candidatos[] = $limpio;
        }
    }

    return array_values(array_unique($candidatos));
}

/**
 * Busca el equipo en Highlightly probando el alias manual (si existe) y
 * luego cada variante generada, hasta encontrar coincidencia.
 * Devuelve ['id'=>..., 'name'=>..., 'candidato_usado'=>...] o null.
 */
function hlResolverEquipo(string $equipoNombre, string $apiKey): ?array
{
    $aliasManual = hlAliasManual($equipoNombre);
    if ($aliasManual !== null) {
        $r = hlLlamar("https://soccer.highlightly.net/teams?name=" . urlencode($aliasManual), $apiKey);
        $d = json_decode($r['body'], true);
        if (!empty($d['data'][0])) {
            return $d['data'][0] + ['candidato_usado' => $aliasManual, 'ultimo_http' => $r['http']];
        }
    }

    $ultimoHttp = null;
    $ultimoBody = null;
    foreach (hlNombresCandidatos($equipoNombre) as $candidato) {
        $r = hlLlamar("https://soccer.highlightly.net/teams?name=" . urlencode($candidato), $apiKey);
        $ultimoHttp = $r['http'];
        $ultimoBody = $r['body'];
        $d = json_decode($r['body'], true);
        if (!empty($d['data'][0])) {
            return $d['data'][0] + ['candidato_usado' => $candidato, 'ultimo_http' => $r['http']];
        }
        usleep(150000); // pequeña pausa entre intentos para no disparar un límite de ráfaga
    }

    // No se encontró con ninguna variante: devolvemos el diagnóstico del último intento
    return ['id' => null, 'ultimo_http' => $ultimoHttp, 'ultimo_body' => $ultimoBody ? substr($ultimoBody, 0, 150) : null];
}

function obtenerStatsReales(PDO $conexion, ?string $apiKey, string $equipoNombre, int $diasCache = 3): ?array
{
    if (!$apiKey) {
        return null;
    }

    // 1. ¿Ya está en caché y sigue fresco?
    $stmt = $conexion->prepare("SELECT * FROM equipo_stats_reales WHERE equipo_nombre = :n");
    $stmt->execute([':n' => $equipoNombre]);
    $cache = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cache && strtotime($cache['actualizado_en']) > strtotime("-$diasCache days")) {
        return $cache;
    }

    // 2. ID del equipo en Highlightly (reutiliza el de caché si ya lo teníamos)
    $highlightlyId = $cache['highlightly_id'] ?? null;
    if (!$highlightlyId) {
        $encontrado = hlResolverEquipo($equipoNombre, $apiKey);
        $highlightlyId = $encontrado['id'] ?? null;
        if (!$highlightlyId) {
            return $cache ?: null; // no lo encontramos con ninguna variante
        }
    }

    // 3. Últimos partidos jugados (local + visitante)
    $rh = hlLlamar("https://soccer.highlightly.net/matches?homeTeamId=$highlightlyId&limit=15", $apiKey);
    $ra = hlLlamar("https://soccer.highlightly.net/matches?awayTeamId=$highlightlyId&limit=15", $apiKey);
    $todos = array_merge(json_decode($rh['body'], true)['data'] ?? [], json_decode($ra['body'], true)['data'] ?? []);

    $jugados = array_filter($todos, fn($m) => ($m['state']['score']['current'] ?? null) !== null);
    usort($jugados, fn($a, $b) => strcmp($b['date'], $a['date']));
    $jugados = array_slice($jugados, 0, 5);

    if (!$jugados) {
        return $cache ?: null;
    }

    // 4. Detalle + estadísticas de cada partido
    $corners = $amarillas = $rojas = $atajadas = $posesion = $xg = $penales = [];

    foreach ($jugados as $m) {
        $r = hlLlamar("https://soccer.highlightly.net/matches/{$m['id']}", $apiKey);
        $detalle = json_decode($r['body'], true);
        $detalle = $detalle[0] ?? $detalle; // a veces viene envuelto en un array

        foreach ($detalle['statistics'] ?? [] as $bloque) {
            if (($bloque['team']['id'] ?? null) != $highlightlyId) {
                continue;
            }
            $st = $bloque['statistics'];
            if (($v = hlExtraerStat($st, 'Corners')) !== null) $corners[] = $v;
            if (($v = hlExtraerStat($st, 'Yellow cards')) !== null) $amarillas[] = $v;
            if (($v = hlExtraerStat($st, 'Red cards')) !== null) $rojas[] = $v;
            if (($v = hlExtraerStat($st, 'Goalkeeper saves')) !== null) $atajadas[] = $v;
            if (($v = hlExtraerStat($st, 'Possession')) !== null) $posesion[] = $v * 100;
            if (($v = hlExtraerStat($st, 'Expected Goals')) !== null) $xg[] = $v;
        }

        // Penales: se cuentan a partir de los eventos del partido (no viene como stat aparte)
        $penalesPartido = 0;
        foreach ($detalle['events'] ?? [] as $ev) {
            if (($ev['team']['id'] ?? null) == $highlightlyId && stripos($ev['type'] ?? '', 'Penalty') !== false) {
                $penalesPartido++;
            }
        }
        $penales[] = $penalesPartido;
    }

    $prom = fn(array $a) => $a ? round(array_sum($a) / count($a), 2) : null;

    $resultado = [
        'equipo_nombre'               => $equipoNombre,
        'highlightly_id'              => $highlightlyId,
        'partidos_analizados'         => count($corners) ?: count($jugados),
        'corners_promedio'            => $prom($corners),
        'tarjetas_amarillas_promedio' => $prom($amarillas),
        'tarjetas_rojas_promedio'     => $prom($rojas),
        'atajadas_promedio'           => $prom($atajadas),
        'posesion_promedio'           => $prom($posesion),
        'xg_promedio'                 => $prom($xg),
        'penales_promedio'            => $prom($penales),
        'actualizado_en'              => date('c'),
    ];

    // 5. Guardar en caché
    $sql = "INSERT INTO equipo_stats_reales
            (equipo_nombre, highlightly_id, partidos_analizados, corners_promedio, tarjetas_amarillas_promedio,
             tarjetas_rojas_promedio, atajadas_promedio, posesion_promedio, xg_promedio, penales_promedio, actualizado_en)
            VALUES (:nom, :hid, :pa, :co, :am, :ro, :at, :po, :xg, :pn, now())
            ON CONFLICT (equipo_nombre) DO UPDATE SET
                highlightly_id = EXCLUDED.highlightly_id, partidos_analizados = EXCLUDED.partidos_analizados,
                corners_promedio = EXCLUDED.corners_promedio, tarjetas_amarillas_promedio = EXCLUDED.tarjetas_amarillas_promedio,
                tarjetas_rojas_promedio = EXCLUDED.tarjetas_rojas_promedio, atajadas_promedio = EXCLUDED.atajadas_promedio,
                posesion_promedio = EXCLUDED.posesion_promedio, xg_promedio = EXCLUDED.xg_promedio,
                penales_promedio = EXCLUDED.penales_promedio, actualizado_en = now()";
    $conexion->prepare($sql)->execute([
        ':nom' => $equipoNombre, ':hid' => $highlightlyId, ':pa' => $resultado['partidos_analizados'],
        ':co' => $resultado['corners_promedio'], ':am' => $resultado['tarjetas_amarillas_promedio'],
        ':ro' => $resultado['tarjetas_rojas_promedio'], ':at' => $resultado['atajadas_promedio'],
        ':po' => $resultado['posesion_promedio'], ':xg' => $resultado['xg_promedio'], ':pn' => $resultado['penales_promedio'],
    ]);

    return $resultado;
}