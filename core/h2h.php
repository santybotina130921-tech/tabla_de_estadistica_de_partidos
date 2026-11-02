<?php
/**
 * core/h2h.php
 *
 * Cara a cara (últimos enfrentamientos entre dos equipos) con Highlightly y las
 * "bolitas" de forma reciente. Reemplaza el viejo bloque "ÚLTIMO RESULTADO", que
 * solo servía para ligas de football-data.org y llamaba a la API en cada visita.
 * Gasta 1 petición por pareja y se recuerda 24 horas.
 */
require_once __DIR__ . '/alineaciones.php'; // trae hlLlamar() y la caché en archivos

/** Devuelve ['partidos' => [...], 'http' => código, 'crudo' => primeros caracteres de la respuesta]. */
function hlH2H(?string $apiKey, int $idA, int $idB): array
{
    if (!$apiKey || !$idA || !$idB) {
        return ['partidos' => [], 'http' => 0, 'crudo' => ''];
    }

    $clave = 'h2h_' . min($idA, $idB) . '_' . max($idA, $idB);
    $c = alCacheLeer($clave, 24 * 3600);
    if ($c['hit'] && is_array($c['dato'])) {
        return $c['dato'];
    }

    $r = hlLlamar("https://soccer.highlightly.net/head-2-head?teamIdOne=$idA&teamIdTwo=$idB", $apiKey);
    $crudo = substr((string) $r['body'], 0, 300);
    if ($r['http'] !== 200) {
        return ['partidos' => [], 'http' => $r['http'], 'crudo' => $crudo]; // los fallos no se guardan
    }

    $d = json_decode((string) $r['body'], true);
    $lista = [];
    if (is_array($d)) {
        $lista = (isset($d['data']) && is_array($d['data'])) ? $d['data'] : $d;
    }

    $partidos = [];
    foreach ($lista as $m) {
        if (!is_array($m) || empty($m['homeTeam']) || empty($m['awayTeam'])) {
            continue;
        }
        $marcador = $m['state']['score']['current'] ?? ($m['score'] ?? '');
        if (!is_string($marcador) || !preg_match('/(\d+)\s*-\s*(\d+)/', $marcador, $g)) {
            continue; // partido sin marcador legible (no jugado)
        }
        $partidos[] = [
            'fecha'   => (string) ($m['date'] ?? ''),
            'liga'    => (string) ($m['league']['name'] ?? ''),
            'home_id' => (int) ($m['homeTeam']['id'] ?? 0),
            'home'    => (string) ($m['homeTeam']['name'] ?? ''),
            'away_id' => (int) ($m['awayTeam']['id'] ?? 0),
            'away'    => (string) ($m['awayTeam']['name'] ?? ''),
            'gh'      => (int) $g[1],
            'ga'      => (int) $g[2],
        ];
    }
    usort($partidos, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

    $res = ['partidos' => $partidos, 'http' => 200, 'crudo' => $crudo];
    alCacheEscribir($clave, $res);
    return $res;
}

/** Bolitas V / E / D (del partido más viejo al más nuevo). */
function formaHtml(?string $forma): string
{
    if (!$forma) {
        return '<span class="h2h-sin">sin datos todavía</span>';
    }
    $h = '';
    foreach (str_split($forma) as $r) {
        $clase = ($r === 'V') ? 'f-v' : (($r === 'D') ? 'f-d' : 'f-e');
        $h .= '<span class="forma-chip ' . $clase . '">' . htmlspecialchars($r) . '</span>';
    }
    return $h;
}