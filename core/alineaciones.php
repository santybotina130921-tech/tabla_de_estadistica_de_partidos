<?php
/**
 * core/alineaciones.php
 *
 * Busca el próximo partido REAL entre dos equipos y, cuando la alineación ya
 * está publicada, trae el 11 inicial y la banca (los convocados del partido).
 *
 * Reglas de Highlightly (plan gratis, misma key de siempre):
 *  - La alineación solo se pide desde ~40 min antes del pitazo hasta ~120 min después.
 *  - Ellos la refrescan cada 15 min, así que aquí la guardamos en caché 10 min.
 * Todo se cachea en archivos (core/cache/) para gastar lo mínimo del cupo diario.
 */
require_once __DIR__ . '/stats_reales.php';

function alCacheRuta(string $clave): string
{
    $dir = __DIR__ . '/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir . '/' . md5($clave) . '.json';
}

function alCacheLeer(string $clave, int $ttlSegundos): array
{
    $ruta = alCacheRuta($clave);
    if (is_file($ruta) && (time() - filemtime($ruta)) < $ttlSegundos) {
        $contenido = json_decode((string) file_get_contents($ruta), true);
        if (is_array($contenido) && array_key_exists('dato', $contenido)) {
            return ['hit' => true, 'dato' => $contenido['dato']];
        }
    }
    return ['hit' => false, 'dato' => null];
}

function alCacheEscribir(string $clave, $dato): void
{
    @file_put_contents(alCacheRuta($clave), json_encode(['dato' => $dato], JSON_UNESCAPED_UNICODE));
}

/**
 * Próximo partido (o en curso / recién terminado) entre dos equipos, en
 * cualquiera de los dos sentidos (local/visitante). Devuelve null si no hay.
 * Gasta 2 peticiones y se guarda 6 horas (también cuando no hay partido).
 */
function alProximoPartido(?string $apiKey, int $idA, int $idB): ?array
{
    if (!$apiKey || !$idA || !$idB) {
        return null;
    }

    $clave = 'proximo_' . min($idA, $idB) . '_' . max($idA, $idB);
    $c = alCacheLeer($clave, 6 * 3600);
    if ($c['hit']) {
        return $c['dato'];
    }

    $limiteInferior = time() - 130 * 60; // incluye partidos en curso o recién terminados
    $mejor = null;

    foreach ([[$idA, $idB], [$idB, $idA]] as $par) {
        $r = hlLlamar("https://soccer.highlightly.net/matches?homeTeamId={$par[0]}&awayTeamId={$par[1]}&limit=20", $apiKey);
        if ($r['http'] !== 200) {
            continue;
        }
        $d = json_decode((string) $r['body'], true);
        foreach ($d['data'] ?? [] as $m) {
            $inicio = strtotime($m['date'] ?? '');
            if (!$inicio || $inicio < $limiteInferior) {
                continue;
            }
            $estado = strtolower($m['state']['description'] ?? '');
            if (strpos($estado, 'postpon') !== false || strpos($estado, 'cancel') !== false) {
                continue;
            }
            if ($mejor === null || $inicio < $mejor['inicio']) {
                $mejor = [
                    'id'        => $m['id'],
                    'inicio'    => $inicio,
                    'liga'      => $m['league']['name'] ?? '',
                    'estado'    => $m['state']['description'] ?? '',
                    'local'     => $m['homeTeam']['name'] ?? '',
                    'visitante' => $m['awayTeam']['name'] ?? '',
                ];
            }
        }
    }

    alCacheEscribir($clave, $mejor);
    return $mejor;
}

/**
 * Alineación de un partido. Devuelve un arreglo con 'estado':
 *  - 'pendiente'  : todavía falta para la ventana de 40 min antes del partido
 *  - 'no_publicada': estamos en la ventana pero la API aún no la tiene
 *  - 'disponible' : trae 'equipos' => [homeTeam, awayTeam]
 *  - 'error'      : falló la API ('http' trae el código)
 */
function alAlineacion(?string $apiKey, array $partido): array
{
    $ahora  = time();
    $inicio = (int) $partido['inicio'];

    if ($ahora < $inicio - 45 * 60) {
        return ['estado' => 'pendiente'];
    }
    if (!$apiKey) {
        return ['estado' => 'error', 'http' => 0];
    }

    $clave = 'alineacion_' . $partido['id'];
    $c = alCacheLeer($clave, 600);
    if ($c['hit']) {
        // Si lo guardado fue un fallo o "aún no publicada", solo se respeta 2 minutos
        $esExito = is_array($c['dato']) && ($c['dato']['estado'] ?? '') === 'disponible';
        $edad = time() - (int) @filemtime(alCacheRuta($clave));
        if ($esExito || $edad < 120) {
            return $c['dato'];
        }
    }

    $r = hlLlamar("https://soccer.highlightly.net/lineups/{$partido['id']}", $apiKey);
    $d = json_decode((string) $r['body'], true);

    if ($r['http'] === 200 && !empty($d['homeTeam']['initialLineup']) && !empty($d['awayTeam']['initialLineup'])) {
        $res = ['estado' => 'disponible', 'equipos' => [$d['homeTeam'], $d['awayTeam']]];
        alCacheEscribir($clave, $res);
        return $res;
    }

    if ($r['http'] === 200) {
        $res = ['estado' => 'no_publicada'];
    } else {
        $res = ['estado' => 'error', 'http' => $r['http']];
    }
    // Los fallos se recuerdan poco tiempo para no gastar cupo si recargan la página
    @file_put_contents(alCacheRuta($clave), json_encode(['dato' => $res]));
    return $res;
}

function alEtiquetaPosicion(string $pos): string
{
    $mapa = [
        'Goalkeeper' => 'Porteros',
        'Defender'   => 'Defensas',
        'Midfielder' => 'Medios',
        'Forward'    => 'Delanteros',
    ];
    return $mapa[$pos] ?? $pos;
}

function alApellido(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    return $partes ? end($partes) : $nombre;
}

/** HTML de un equipo: cancha con el 11 (portero abajo) + banca agrupada por posición. */
function alHtmlEquipo(array $eq, string $clase): string
{
    $h  = '<div class="al-team ' . $clase . '">';
    $h .= '<div class="al-head">';
    if (!empty($eq['logo'])) {
        $h .= '<img src="' . htmlspecialchars($eq['logo']) . '" alt="">';
    }
    $h .= '<b>' . htmlspecialchars($eq['name'] ?? '') . '</b>';
    $h .= '<span class="al-form">' . htmlspecialchars($eq['formation'] ?? '') . '</span></div>';

    $h .= '<div class="al-pitch">';
    foreach (array_reverse($eq['initialLineup'] ?? []) as $fila) {
        $h .= '<div class="al-row">';
        foreach ($fila as $j) {
            $nombre = (string) ($j['name'] ?? '');
            $h .= '<div class="al-player" title="' . htmlspecialchars($nombre) . '">';
            $h .= '<span class="al-num">' . htmlspecialchars((string) ($j['number'] ?? '')) . '</span>';
            $h .= '<span class="al-name">' . htmlspecialchars(alApellido($nombre)) . '</span></div>';
        }
        $h .= '</div>';
    }
    $h .= '</div>';

    $banca = $eq['substitutes'] ?? [];
    $h .= '<div class="al-bench"><h4>🪑 Banca (' . count($banca) . ')</h4>';
    $grupos = [];
    foreach ($banca as $j) {
        $grupos[$j['position'] ?? 'Otros'][] = $j;
    }
    foreach (['Goalkeeper', 'Defender', 'Midfielder', 'Forward'] as $pos) {
        if (empty($grupos[$pos])) {
            continue;
        }
        $h .= '<div class="al-grupo"><small>' . alEtiquetaPosicion($pos) . '</small>';
        foreach ($grupos[$pos] as $j) {
            $h .= '<span class="al-chip">' . htmlspecialchars((string) ($j['number'] ?? '')) . ' ' . htmlspecialchars($j['name'] ?? '') . '</span>';
        }
        $h .= '</div>';
    }
    $h .= '</div></div>';

    return $h;
}