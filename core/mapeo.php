<?php
require_once __DIR__ . '/alineaciones.php';

function mpLigasFootballData(): array
{
    return [
        'CL'  => ['nombre' => 'UEFA Champions League', 'pais' => 'World',       'id' => 2486],
        'PD'  => ['nombre' => 'La Liga',               'pais' => 'Spain',       'id' => 119924],
        'PL'  => ['nombre' => 'Premier League',        'pais' => 'England',     'id' => null],
        'ELC' => ['nombre' => 'Championship',          'pais' => 'England',     'id' => null],
        'BL1' => ['nombre' => 'Bundesliga',            'pais' => 'Germany',     'id' => null],
        'SA'  => ['nombre' => 'Serie A',               'pais' => 'Italy',       'id' => null],
        'FL1' => ['nombre' => 'Ligue 1',               'pais' => 'France',      'id' => null],
        'PPL' => ['nombre' => 'Primeira Liga',         'pais' => 'Portugal',    'id' => null],
        'DED' => ['nombre' => 'Eredivisie',            'pais' => 'Netherlands', 'id' => null],
        'BSA' => ['nombre' => 'Serie A',               'pais' => 'Brazil',      'id' => null],
    ];
}

function mpNormalizar(string $nombre): array
{
    $t = strtr($nombre, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Å' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ě' => 'e', 'ę' => 'e', 'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ı' => 'i', 'İ' => 'i', 'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ø' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ů' => 'u', 'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'Ñ' => 'n', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'Ç' => 'c', 'Č' => 'c',
        'š' => 's', 'ś' => 's', 'ş' => 's', 'Š' => 's', 'Ş' => 's', 'ž' => 'z', 'ź' => 'z', 'ż' => 'z', 'Ž' => 'z',
        'ł' => 'l', 'Ł' => 'l', 'ř' => 'r', 'Ř' => 'r', 'ğ' => 'g', 'Ğ' => 'g', 'ß' => 'ss', 'ď' => 'd', 'ť' => 't',
    ]);
    $t = strtolower($t);
    $t = preg_replace('/[^a-z0-9]+/', ' ', $t);

    $sobran = [
        'fc', 'cf', 'ac', 'acf', 'ssc', 'afc', 'sc', 'cd', 'rc', 'rcd', 'ec', 'aa', 'ca', 'se', 'us', 'ud',
        'rb', 'vfb', 'vfl', 'tsg', 'sv', 'fsv', 'aj', 'es', 'af', 'sk', 'fk', 'bk', 'if', 'club', 'clube',
        'de', 'da', 'do', 'del', 'la', 'le', 'los', 'las', 'the', 'calcio', 'futbol', 'football', 'balompie',
    ];

    $tokens = [];
    foreach (explode(' ', trim($t)) as $tok) {
        if ($tok === '' || ctype_digit($tok) || in_array($tok, $sobran, true)) {
            continue;
        }
        $tokens[] = $tok;
    }
    if (!$tokens) {
        $tokens = array_values(array_filter(explode(' ', trim($t)), fn($x) => $x !== ''));
    }
    return $tokens;
}

function mpPuntuacion(string $a, string $b): float
{
    $ta = mpNormalizar($a);
    $tb = mpNormalizar($b);
    $sa = implode(' ', $ta);
    $sb = implode(' ', $tb);
    if ($sa === '' || $sb === '') {
        return 0.0;
    }
    if ($sa === $sb) {
        return 1.0;
    }

    similar_text($sa, $sb, $pct);
    $desempate = ($pct / 100) * 0.05;

    $corto = count($ta) <= count($tb) ? $ta : $tb;
    $largo = count($ta) <= count($tb) ? $tb : $ta;
    $contenido = true;
    foreach ($corto as $tok) {
        $encontrado = false;
        foreach ($largo as $tl) {
            if ($tok === $tl || (strlen($tok) >= 3 && strpos($tl, $tok) === 0)) {
                $encontrado = true;
                break;
            }
        }
        if (!$encontrado) {
            $contenido = false;
            break;
        }
    }
    if ($contenido) {
        return 0.88 + $desempate;
    }

    $lev = 1 - levenshtein(substr($sa, 0, 60), substr($sb, 0, 60)) / max(strlen($sa), strlen($sb), 1);
    return max($pct / 100, $lev) * 0.85;
}

function mpDescubrirLiga(?string $apiKey, string $codigo, array $cfg, int $temporada): ?int
{
    if (!empty($cfg['id'])) {
        return (int) $cfg['id'];
    }
    $c = alCacheLeer('mp_liga_' . $codigo, 30 * 86400);
    if ($c['hit'] && $c['dato']) {
        return (int) $c['dato'];
    }

    $r = hlLlamar('https://soccer.highlightly.net/leagues?countryName=' . urlencode($cfg['pais']) . '&limit=100', $apiKey);
    if ($r['http'] !== 200) {
        return null;
    }
    $d = json_decode((string) $r['body'], true);
    $objetivo = strtolower($cfg['nombre']);

    foreach ($d['data'] ?? [] as $l) {
        $temporadas = array_map('intval', array_column($l['seasons'] ?? [], 'season'));
        if (in_array($temporada, $temporadas, true) && strtolower((string) ($l['name'] ?? '')) === $objetivo) {
            alCacheEscribir('mp_liga_' . $codigo, (int) $l['id']);
            return (int) $l['id'];
        }
    }
    return null;
}

function mpMapearLiga(PDO $c, ?string $apiKey, string $codigo, array $cfg, int $temporada = 2026, ?int $idManual = null): array
{
    if (!$apiKey) {
        return ['ok' => false, 'msg' => 'Falta HIGHLIGHTLY_API_KEY.'];
    }

    $leagueId = $idManual ?: mpDescubrirLiga($apiKey, $codigo, $cfg, $temporada);
    if (!$leagueId) {
        return ['ok' => false, 'msg' => 'No encontré esta liga en Highlightly. Puedes pasar su ID a mano agregando &id=NUMERO.'];
    }

    $r = hlLlamar("https://soccer.highlightly.net/standings?leagueId=$leagueId&season=$temporada", $apiKey);
    if ($r['http'] !== 200) {
        return ['ok' => false, 'msg' => 'Highlightly respondió HTTP ' . $r['http'] . ' (liga ' . $leagueId . ').'];
    }
    $d = json_decode((string) $r['body'], true);

    $hl = [];
    foreach ($d['groups'] ?? [] as $grupo) {
        foreach ($grupo['standings'] ?? [] as $fila) {
            $t = $fila['team'] ?? null;
            if ($t && !empty($t['id'])) {
                $hl[(int) $t['id']] = (string) ($t['name'] ?? '');
            }
        }
    }
    if (!$hl) {
        return ['ok' => false, 'msg' => 'La liga ' . $leagueId . ' no devolvió equipos para la temporada ' . $temporada . '.'];
    }

    $st = $c->prepare("SELECT DISTINCT equipo_nombre FROM estadisticas_reales WHERE liga_id = :l");
    $st->execute([':l' => $codigo]);
    $nuestros = $st->fetchAll(PDO::FETCH_COLUMN);

    $yaMapeados = [];
    $filas = $c->query("SELECT equipo_nombre, highlightly_id FROM equipo_stats_reales WHERE highlightly_id IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $usadosHl = [];
    foreach ($filas as $f) {
        $yaMapeados[$f['equipo_nombre']] = (int) $f['highlightly_id'];
        $usadosHl[(int) $f['highlightly_id']] = true;
    }

    $pendientes = array_values(array_filter($nuestros, fn($n) => !isset($yaMapeados[$n])));

    $pares = [];
    foreach ($pendientes as $nombre) {
        foreach ($hl as $id => $nombreHl) {
            if (!isset($usadosHl[$id])) {
                $pares[] = [mpPuntuacion($nombre, $nombreHl), $nombre, $id, $nombreHl];
            }
        }
    }
    usort($pares, fn($a, $b) => $b[0] <=> $a[0]);

    $asignados = [];
    foreach ($pares as [$score, $nombre, $id, $nombreHl]) {
        if ($score < 0.62) {
            break;
        }
        if (isset($asignados[$nombre]) || isset($usadosHl[$id])) {
            continue;
        }
        $asignados[$nombre] = ['id' => $id, 'hl' => $nombreHl, 'score' => round($score, 2), 'metodo' => 'similitud'];
        $usadosHl[$id] = true;
    }

    $restantes = array_values(array_filter($pendientes, fn($n) => !isset($asignados[$n])));
    $libres = array_filter($hl, fn($id) => !isset($usadosHl[$id]), ARRAY_FILTER_USE_KEY);

    $dudosos = [];
    if (count($restantes) === 1 && count($libres) === 1) {
        $nombre = $restantes[0];
        $id = (int) array_key_first($libres);
        $asignados[$nombre] = ['id' => $id, 'hl' => $libres[$id], 'score' => round(mpPuntuacion($nombre, $libres[$id]), 2), 'metodo' => 'por descarte'];
        $restantes = [];
    } else {
        foreach ($restantes as $nombre) {
            $mejor = null;
            $mejorScore = 0.0;
            foreach ($libres as $nombreHl) {
                $s = mpPuntuacion($nombre, $nombreHl);
                if ($s > $mejorScore) {
                    $mejorScore = $s;
                    $mejor = $nombreHl;
                }
            }
            $dudosos[] = ['nombre' => $nombre, 'candidato' => $mejor, 'score' => round($mejorScore, 2)];
        }
    }

    $guardar = $c->prepare(
        "INSERT INTO equipo_stats_reales (equipo_nombre, highlightly_id) VALUES (:n, :i)
         ON CONFLICT (equipo_nombre) DO UPDATE SET highlightly_id = EXCLUDED.highlightly_id"
    );
    foreach ($asignados as $nombre => $a) {
        $guardar->execute([':n' => $nombre, ':i' => $a['id']]);
    }

    $detalle = [];
    foreach ($asignados as $nombre => $a) {
        $detalle[] = ['nombre' => $nombre, 'hl' => $a['hl'], 'score' => $a['score'], 'metodo' => $a['metodo']];
    }

    return [
        'ok'          => true,
        'liga_hl'     => $leagueId,
        'total'       => count($nuestros),
        'ya_estaban'  => count($nuestros) - count($pendientes),
        'mapeados'    => count($asignados),
        'detalle'     => $detalle,
        'sin_mapear'  => $dudosos,
    ];
}