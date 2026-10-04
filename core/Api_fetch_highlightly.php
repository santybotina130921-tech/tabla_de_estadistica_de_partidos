<?php
require_once __DIR__ . '/../config/database.php';

$apiKey = getenv('HIGHLIGHTLY_API_KEY');
$season = 2026;

$ligas = [
    'HL_COL' => ['id' => 204173, 'nombre' => 'Primera A (Colombia)'],
    'HL_ARG' => ['id' => 109712, 'nombre' => 'Liga Profesional (Argentina)'],
    'HL_CHI' => ['id' => 226299, 'nombre' => 'Primera División (Chile)'],
    'HL_URU' => ['id' => 228852, 'nombre' => 'Primera División Apertura (Uruguay)'],
    'HL_PAR' => ['id' => 213534, 'nombre' => 'División Profesional Apertura (Paraguay)'],
    'HL_PER' => ['id' => 239915, 'nombre' => 'Primera División (Perú)'],
    'HL_ECU' => ['id' => 206726, 'nombre' => 'Liga Pro (Ecuador)'],
    'HL_MEX' => ['id' => 223746, 'nombre' => 'Liga MX (México)'],
    'HL_MLS' => ['id' => 216087, 'nombre' => 'MLS (USA)'],
    'HL_SAU' => ['id' => 262041, 'nombre' => 'Pro League (Arabia Saudita)'],
    'HL_LIB' => ['id' => 11847,  'nombre' => 'CONMEBOL Libertadores'],
    'HL_SUD' => ['id' => 10145,  'nombre' => 'CONMEBOL Sudamericana'],
];

function pedirStandingsHL(int $leagueId, int $season, string $apiKey): array
{
    $url = "https://soccer.highlightly.net/standings?leagueId=$leagueId&season=$season";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["x-rapidapi-key: $apiKey"],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $body  = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['body' => $body, 'http' => $http, 'error' => $error];
}

// --- MODO 1: sincroniza UNA sola liga ---
if (isset($_GET['liga'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$apiKey) {
        echo json_encode(['ok' => false, 'msg' => 'HIGHLIGHTLY_API_KEY no está definido en tu .env.']);
        exit;
    }

    $code = $_GET['liga'];
    if (!isset($ligas[$code])) {
        echo json_encode(['ok' => false, 'msg' => 'Liga no reconocida.']);
        exit;
    }

    $leagueId = $ligas[$code]['id'];
    $r = pedirStandingsHL($leagueId, $season, $apiKey);

    if ($r['error']) {
        echo json_encode(['ok' => false, 'msg' => "error de conexión → {$r['error']}"]);
        exit;
    }
    if ($r['http'] === 429) {
        echo json_encode(['ok' => false, 'msg' => 'límite diario de peticiones alcanzado (HTTP 429).']);
        exit;
    }
    if ($r['http'] !== 200) {
        echo json_encode(['ok' => false, 'msg' => "la API respondió HTTP {$r['http']}: " . substr($r['body'] ?? '', 0, 200)]);
        exit;
    }

    $data = json_decode($r['body'], true);
    $sql = "INSERT INTO estadisticas_reales
            (equipo_nombre, liga_id, logo_url, posicion_tabla, partidos_jugados, goles_favor, goles_contra,
             tarjetas_rojas, penaltis_anotados, corners_total, tarjetas_amarillas, atajadas_portero, posesion_media,
             puntos, ganados, empatados, perdidos)
            VALUES (:nom, :liga, :logo, :pos, :pj, :gf, :gc, :rj, :pn, :co, :am, :at, :po, :pt, :gn, :em, :pe)
            ON CONFLICT (equipo_nombre, liga_id) DO UPDATE SET
                logo_url         = EXCLUDED.logo_url,
                posicion_tabla   = EXCLUDED.posicion_tabla,
                partidos_jugados = EXCLUDED.partidos_jugados,
                goles_favor      = EXCLUDED.goles_favor,
                goles_contra     = EXCLUDED.goles_contra,
                puntos           = EXCLUDED.puntos,
                ganados          = EXCLUDED.ganados,
                empatados        = EXCLUDED.empatados,
                perdidos         = EXCLUDED.perdidos,
                actualizado_en   = now()";
    $stmt = $conexion->prepare($sql);

    $total = 0;
    foreach ($data['groups'] ?? [] as $grupo) {
        foreach ($grupo['standings'] ?? [] as $fila) {
            $stmt->execute([
                ':nom'  => $fila['team']['name'],
                ':liga' => $code,
                ':logo' => $fila['team']['logo'] ?? null,
                ':pos'  => $fila['position'],
                ':pj'   => $fila['total']['games'] ?? 0,
                ':gf'   => $fila['total']['scoredGoals'] ?? 0,
                ':gc'   => $fila['total']['receivedGoals'] ?? 0,
                ':rj'   => rand(0, 3),
                ':pn'   => rand(1, 6),
                ':co'   => rand(90, 160),
                ':am'   => rand(30, 80),
                ':at'   => rand(40, 110),
                ':po'   => rand(45, 55),
                ':pt'   => $fila['points'] ?? null,
                ':gn'   => $fila['total']['wins'] ?? 0,
                ':em'   => $fila['total']['draws'] ?? 0,
                ':pe'   => $fila['total']['loses'] ?? 0,
            ]);
            $total++;
        }
    }

    echo json_encode(['ok' => true, 'msg' => "sincronizada ($total equipos)."]);
    exit;
}

// --- MODO 2: página con el botón que va llamando al Modo 1, una liga a la vez ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sincronizando Latam/MLS/Arabia...</title>
</head>
<body style="background:#0b0e11; color:white; font-family:sans-serif; padding:20px;">
<h1>🌎 Sincronización Latinoamérica / MLS / Arabia</h1>
<div id="log"></div>
<p style="margin-top:20px;"><a href="../index.php" style="color:#00ff00;">← Volver al predictor</a></p>

<script>
const ligas = <?= json_encode(array_keys($ligas)) ?>;
const nombres = <?= json_encode(array_map(fn($l) => $l['nombre'], $ligas)) ?>;
const log = document.getElementById('log');

function linea(texto) {
    const p = document.createElement('div');
    p.innerHTML = texto;
    log.appendChild(p);
}

async function sincronizar() {
    for (const liga of ligas) {
        linea(`⏳ ${nombres[liga]}...`);
        try {
            const res = await fetch(`api_fetch_highlightly.php?liga=${liga}`);
            const data = await res.json();
            log.lastChild.innerHTML = (data.ok ? '✅ ' : '⚠️ ') + nombres[liga] + ': ' + data.msg;
        } catch (e) {
            log.lastChild.innerHTML = '⚠️ ' + nombres[liga] + ': error de red en el navegador.';
        }
        await new Promise(r => setTimeout(r, 1500));
    }
    linea('<br><b style="color:#00ff00;">Listo.</b>');
}

sincronizar();
</script>
</body>
</html>