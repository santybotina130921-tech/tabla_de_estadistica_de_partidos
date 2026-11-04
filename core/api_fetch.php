<?php
require_once __DIR__ . '/../config/database.php';

$apiToken = getenv('FOOTBALL_DATA_API_TOKEN');
$baseUrl  = "https://api.football-data.org/v4/competitions/";
$competencias = ['CL', 'PL', 'PD', 'BL1', 'SA', 'FL1', 'PPL', 'DED', 'BSA', 'ELC', 'WC', 'EC'];

function pedirStandings(string $url, string $token): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["X-Auth-Token: $token"],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body  = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['body' => $body, 'http' => $http, 'error' => $error];
}

// --- MODO 1: sincroniza UNA sola liga (lo llama el JS de abajo, una petición corta cada vez) ---
if (isset($_GET['liga'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (!$apiToken) {
        echo json_encode(['ok' => false, 'msg' => 'FOOTBALL_DATA_API_TOKEN no está definido en tu .env.']);
        exit;
    }

    $code = $_GET['liga'];
    if (!in_array($code, $competencias, true)) {
        echo json_encode(['ok' => false, 'msg' => 'Liga no reconocida.']);
        exit;
    }

    $esGoleadores = (($_GET['tipo'] ?? '') === 'goleadores');
    $r = pedirStandings($baseUrl . "$code/" . ($esGoleadores ? 'scorers?limit=15' : 'standings'), $apiToken);

    if ($r['error']) {
        echo json_encode(['ok' => false, 'msg' => "error de conexión → {$r['error']}"]);
        exit;
    }
    if ($r['http'] === 429) {
        echo json_encode(['ok' => false, 'msg' => 'límite de peticiones alcanzado (HTTP 429), espera un poco.']);
        exit;
    }
    if ($r['http'] === 403) {
        echo json_encode(['ok' => false, 'msg' => 'token inválido o sin permiso (HTTP 403).']);
        exit;
    }
    if ($r['http'] !== 200) {
        echo json_encode(['ok' => false, 'msg' => "la API respondió HTTP {$r['http']}."]);
        exit;
    }

    $data = json_decode((string) $r['body'], true);

    if ($esGoleadores) {
        $lista = $data['scorers'] ?? [];
        if (!$lista) {
            echo json_encode(['ok' => false, 'msg' => 'todavía no hay goleadores registrados.']);
            exit;
        }
        try {
            $conexion->beginTransaction();
            $conexion->prepare("DELETE FROM goleadores WHERE liga_id = :l")->execute([':l' => $code]);
            $ins = $conexion->prepare(
                "INSERT INTO goleadores (liga_id, posicion, jugador, equipo, goles, asistencias, penales, partidos)
                 VALUES (:l, :pos, :j, :e, :g, :a, :p, :pj)
                 ON CONFLICT (liga_id, jugador, equipo) DO NOTHING"
            );
            foreach ($lista as $i => $sc) {
                $ins->execute([
                    ':l'   => $code,
                    ':pos' => $i + 1,
                    ':j'   => $sc['player']['name'] ?? '?',
                    ':e'   => $sc['team']['name'] ?? '?',
                    ':g'   => (int) ($sc['goals'] ?? $sc['numberOfGoals'] ?? 0),
                    ':a'   => (int) ($sc['assists'] ?? 0),
                    ':p'   => (int) ($sc['penalties'] ?? 0),
                    ':pj'  => (int) ($sc['playedMatches'] ?? 0),
                ]);
            }
            $conexion->commit();
        } catch (Exception $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            error_log('Goleadores: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'msg' => 'no pude guardar los goleadores (¿corriste la migración SQL?).']);
            exit;
        }
        echo json_encode(['ok' => true, 'msg' => 'goleadores actualizados (' . count($lista) . ').']);
        exit;
    }

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

    foreach ($data['standings'] ?? [] as $grupo) {
        if (($grupo['type'] ?? 'TOTAL') !== 'TOTAL') {
            continue;
        }
        foreach ($grupo['table'] as $pos) {
            $stmt->execute([
                ':nom' => $pos['team']['name'], ':liga' => $code, ':logo' => $pos['team']['crest'],
                ':pos' => $pos['position'], ':pj' => $pos['playedGames'], ':gf' => $pos['goalsFor'], ':gc' => $pos['goalsAgainst'],
                ':rj' => rand(0, 3), ':pn' => rand(1, 6), ':co' => rand(90, 160), ':am' => rand(30, 80), ':at' => rand(40, 110), ':po' => rand(45, 55),
                ':pt' => $pos['points'] ?? null, ':gn' => $pos['won'] ?? 0, ':em' => $pos['draw'] ?? 0, ':pe' => $pos['lost'] ?? 0,
            ]);
        }
    }

    $temporada = ($data['season']['startDate'] ?? '?') . ' → ' . ($data['season']['endDate'] ?? '?');
    echo json_encode(['ok' => true, 'msg' => "sincronizada (temporada $temporada)."]);
    exit;
}

// --- MODO 2: página con el botón que va llamando al Modo 1, una liga a la vez ---
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sincronizando...</title>
</head>
<body style="background:#0b0e11; color:white; font-family:sans-serif; padding:20px;">
<h1>🚀 Sincronización de la temporada actual</h1>
<div id="log"></div>
<p style="margin-top:20px;"><a href="../index.php" style="color:#00ff00;">← Volver al predictor</a></p>

<script>
const ligas = <?= json_encode($competencias) ?>;
const log = document.getElementById('log');

function linea(texto) {
    const p = document.createElement('div');
    p.innerHTML = texto;
    log.appendChild(p);
}

async function pedirJson(url) {
    const res = await fetch(url);
    const texto = await res.text();
    try {
        return JSON.parse(texto);
    } catch (e) {
        const limpio = texto.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220);
        return { ok: false, msg: 'respuesta no válida (HTTP ' + res.status + ') pidiendo ' + res.url + ' → ' + limpio };
    }
}

async function sincronizar() {
    for (const liga of ligas) {
        linea(`⏳ ${liga}...`);
        try {
            const data = await pedirJson(`api_fetch.php?liga=${liga}`);
            log.lastChild.innerHTML = (data.ok ? '✅ ' : '⚠️ ') + liga + ': ' + data.msg;
        } catch (e) {
            log.lastChild.innerHTML = '⚠️ ' + liga + ': no hubo conexión con el servidor (' + e.message + ').';
        }
        // pausa entre peticiones para respetar el límite de la API gratis (10 por minuto)
        await new Promise(r => setTimeout(r, 6500));

        linea(`⏳ ${liga} goleadores...`);
        try {
            const data2 = await pedirJson(`api_fetch.php?liga=${liga}&tipo=goleadores`);
            log.lastChild.innerHTML = (data2.ok ? '✅ ' : '⚠️ ') + liga + ' goleadores: ' + data2.msg;
        } catch (e) {
            log.lastChild.innerHTML = '⚠️ ' + liga + ' goleadores: no hubo conexión con el servidor (' + e.message + ').';
        }
        await new Promise(r => setTimeout(r, 6500));
    }
    linea('<br><b style="color:#00ff00;">Listo.</b>');
}

sincronizar();
</script>
</body>
</html>