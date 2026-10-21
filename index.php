<?php
require_once 'config/database.php';
$liga_actual = $_GET['liga'] ?? 'TODAS';

// Lógica de carga (Individual o Super Liga)
if ($liga_actual === 'TODAS') {
    $stmt = $conexion->prepare("SELECT * FROM estadisticas_reales ORDER BY equipo_nombre ASC");
    $stmt->execute();
} else {
    $stmt = $conexion->prepare("SELECT * FROM estadisticas_reales WHERE liga_id = ? ORDER BY equipo_nombre ASC");
    $stmt->execute([$liga_actual]);
}
$equipos = $stmt->fetchAll();

$L = null; $V = null; $h2h_real = [];
$pL = 0; $pE = 0; $pV = 0;

if (isset($_POST['local'], $_POST['visitante'])) {
    foreach ($equipos as $e) {
        if ($e['equipo_nombre'] == $_POST['local']) $L = $e;
        if ($e['equipo_nombre'] == $_POST['visitante']) $V = $e;
    }

    if ($L && $V) {
        // --- 0. DATOS REALES DE CÓRNERS/TARJETAS/ATAJADAS (Highlightly, con caché) ---
        require_once __DIR__ . '/core/stats_reales.php';
        $apiKeyHL = getenv('HIGHLIGHTLY_API_KEY');
        $realL = obtenerStatsReales($conexion, $apiKeyHL, $L['equipo_nombre']);
        $realV = obtenerStatsReales($conexion, $apiKeyHL, $V['equipo_nombre']);

        // Si conseguimos datos reales, reemplazamos los "totales" por
        // (promedio real x partidos jugados) para que el resto de las fórmulas,
        // que ya asumen un total de temporada, sigan funcionando igual.
        if ($realL && $realL['corners_promedio'] !== null) {
            $L['corners_total']       = round($realL['corners_promedio'] * $L['partidos_jugados']);
            $L['tarjetas_amarillas']  = round(($realL['tarjetas_amarillas_promedio'] ?? 0) * $L['partidos_jugados']);
            $L['tarjetas_rojas']      = round(($realL['tarjetas_rojas_promedio'] ?? 0) * $L['partidos_jugados']);
            $L['atajadas_portero']    = round(($realL['atajadas_promedio'] ?? 0) * $L['partidos_jugados']);
            $L['penaltis_anotados']   = round(($realL['penales_promedio'] ?? 0) * $L['partidos_jugados']);
            $L['datos_reales']        = true;
        }
        if ($realV && $realV['corners_promedio'] !== null) {
            $V['corners_total']       = round($realV['corners_promedio'] * $V['partidos_jugados']);
            $V['tarjetas_amarillas']  = round(($realV['tarjetas_amarillas_promedio'] ?? 0) * $V['partidos_jugados']);
            $V['tarjetas_rojas']      = round(($realV['tarjetas_rojas_promedio'] ?? 0) * $V['partidos_jugados']);
            $V['atajadas_portero']    = round(($realV['atajadas_promedio'] ?? 0) * $V['partidos_jugados']);
            $V['penaltis_anotados']   = round(($realV['penales_promedio'] ?? 0) * $V['partidos_jugados']);
            $V['datos_reales']        = true;
        }

        // --- 1. LÓGICA DE FUERZA ---
        $fuerzaL = ($L['goles_favor'] + $V['goles_contra']) / 2;
        $fuerzaV = ($V['goles_favor'] + $L['goles_contra']) / 2;
        $mediaL = $L['goles_favor'] / max($L['partidos_jugados'], 1);
        $mediaV = $V['goles_favor'] / max($V['partidos_jugados'], 1);

        $dif = abs($fuerzaL - $fuerzaV);

        // --- 2. EL ARREGLO DEL EMPATE (Lógica Pro) ---
        $pE = round(28 - ($dif * 1.5));
        if ($pE < 22) $pE = 23;

        $resto = 100 - $pE;
        $pL = round($resto * ($fuerzaL / ($fuerzaL + $fuerzaV + 0.1)));
        $pV = 100 - $pL - $pE;

        // --- 3. DATOS PARA MERCADOS ---
        $promG = ($L['goles_favor'] + $V['goles_favor']) / max(($L['partidos_jugados'] + $V['partidos_jugados']), 1);
        $corners_avg = ($L['corners_total'] + $V['corners_total']) / 20;

        // --- 4. H2H REAL ---
        $liga_h2h = ($liga_actual === 'TODAS') ? $L['liga_id'] : $liga_actual;
        $apiToken = getenv('FOOTBALL_DATA_API_TOKEN');
        $urlH2H = "https://api.football-data.org/v4/competitions/$liga_h2h/matches?status=FINISHED";
        $opts = ["http" => ["method" => "GET", "header" => "X-Auth-Token: $apiToken\r\n"]];
        $res = @file_get_contents($urlH2H, false, stream_context_create($opts));
        if ($res) {
            $data = json_decode($res, true);
            foreach ($data['matches'] as $m) {
                if (($m['homeTeam']['name'] == $L['equipo_nombre'] && $m['awayTeam']['name'] == $V['equipo_nombre']) ||
                    ($m['homeTeam']['name'] == $V['equipo_nombre'] && $m['awayTeam']['name'] == $L['equipo_nombre'])) {
                    $h2h_real[] = $m;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SANTIAGO AI - PREDICTOR ELITE</title>
    <style>
        :root { --green: #00ff00; --red: #ff4444; --dark: #0b0e11; --card: #161a1e; --gold: #f1c40f; }
        html { color-scheme: dark; }
        /* Fuerza fondo oscuro + letra clara en la lista desplegable nativa (celular/Windows) */
        select option, select optgroup { background-color: #14181d; color: #ffffff; }
        select option:checked { background-color: #0a5; color: #ffffff; }
        body { background: var(--dark); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; overflow-x: hidden; }
        .container { max-width: 1200px; margin: auto; padding: 20px; text-align: center; }
        .main-card { background: var(--card); border: 1px solid #2b2f36; padding: 40px; border-radius: 40px; box-shadow: 0 0 60px rgba(0,255,0,0.15); }
        .arena { display: flex; justify-content: space-around; align-items: center; margin: 40px 0; }
        .shield { width: 220px; height: 220px; background: radial-gradient(circle, #222 0%, #000 100%); border: 6px solid var(--green); border-radius: 50%; padding: 25px; display: flex; align-items: center; justify-content: center; position: relative; box-shadow: 0 0 40px rgba(0,255,0,0.3); }
        .pos-badge { position: absolute; top: 5px; right: 5px; background: var(--gold); color: black; font-weight: 900; padding: 10px 18px; border-radius: 50%; font-size: 22px; border: 4px solid var(--card); }
        .shield img { max-width: 90%; max-height: 90%; filter: drop-shadow(0 0 15px rgba(255,255,255,0.3)); }
        .score-center { background: linear-gradient(180deg, #111, #000); border: 2px solid #333; padding: 30px; border-radius: 25px; min-width: 200px; box-shadow: inset 0 0 20px #000; }
        .score-val { font-size: 80px; font-weight: 900; color: var(--green); letter-spacing: 10px; text-shadow: 0 0 20px rgba(0,255,0,0.5); }
        .score-label { color: var(--gold); font-size: 12px; font-weight: bold; letter-spacing: 2px; }
        select { margin-top: 25px; padding: 15px; background: #000; color: white; border: 2px solid var(--green); border-radius: 12px; width: 280px; font-size: 16px; font-weight: bold; color-scheme: dark; }

        /* --- SELECTOR DE EQUIPO (rediseño) --- */
        .team-picker { position: relative; margin-top: 22px; }
        .team-picker select {
            appearance: none; -webkit-appearance: none;
            width: 260px; margin: 0; padding: 16px 42px 16px 18px;
            background: linear-gradient(180deg, #14181d, #0a0c0f);
            border: 1px solid #2b2f36; border-radius: 16px;
            color: #fff; font-size: 15px; font-weight: 700; letter-spacing: .3px;
            text-align: left; cursor: pointer; color-scheme: dark;
            box-shadow: 0 6px 18px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.03);
            transition: border-color .2s, box-shadow .2s;
        }
        .team-picker::after {
            content: '▾'; position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            pointer-events: none; font-size: 14px; color: var(--accent, var(--green));
        }
        .team-picker.local select:focus, .team-picker.local select:hover { border-color: var(--green); box-shadow: 0 0 0 3px rgba(0,255,0,.15), 0 6px 18px rgba(0,0,0,.45); }
        .team-picker.visitante select:focus, .team-picker.visitante select:hover { border-color: var(--red); box-shadow: 0 0 0 3px rgba(255,68,68,.15), 0 6px 18px rgba(0,0,0,.45); }
        .team-picker.local { --accent: var(--green); }
        .team-picker.visitante { --accent: var(--red); }

        .prob-bar { display: flex; height: 65px; border-radius: 35px; overflow: hidden; margin: 40px 0; border: 3px solid #333; font-weight: 900; font-size: 18px; }
        .p-item { display: flex; align-items: center; justify-content: center; text-shadow: 2px 2px #000; }
        .bet-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 30px; }
        .bet-box { background: #080a0c; padding: 30px; border-radius: 30px; border-left: 8px solid var(--green); text-align: left; }
        .bet-row { display: flex; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #1a1e23; font-size: 18px; }
        .bet-row b { color: var(--green); }

        /* --- TABLA DE POSICIONES --- */
        .standings-wrap { margin-top: 40px; background: #000; border-radius: 30px; padding: 25px; border: 2px solid #1a1e23; text-align: left; }
        .standings-title { color: var(--gold); text-transform: uppercase; letter-spacing: 3px; font-size: 16px; margin: 0 0 18px; text-align: center; }
        .standings-scroll { overflow-x: auto; }
        table.standings { width: 100%; border-collapse: collapse; min-width: 560px; font-size: 13px; }
        table.standings thead th {
            position: sticky; top: 0; background: #0d1117; color: #848e9c;
            font-size: 10px; letter-spacing: 1px; text-transform: uppercase;
            padding: 10px 8px; border-bottom: 1px solid #222; text-align: center;
        }
        table.standings thead th:first-child, table.standings thead th:nth-child(2) { text-align: left; }
        table.standings tbody tr { border-bottom: 1px solid #14181d; }
        table.standings tbody tr:hover { background: rgba(255,255,255,.02); }
        table.standings td { padding: 9px 8px; text-align: center; font-variant-numeric: tabular-nums; }
        table.standings td.club { text-align: left; display: flex; align-items: center; gap: 10px; font-weight: 700; }
        table.standings td.club img { width: 22px; height: 22px; object-fit: contain; }
        table.standings td.pos { text-align: left; font-weight: 900; color: #848e9c; width: 30px; }
        table.standings td.pts { font-weight: 900; color: var(--gold); }
        table.standings tr.zona-alta td.pos { color: var(--green); }
        table.standings tr.zona-alta { border-left: 3px solid var(--green); }
        table.standings tr.zona-baja { border-left: 3px solid var(--red); }
        table.standings tr.es-local { background: rgba(0,255,0,.08); }
        table.standings tr.es-visitante { background: rgba(255,68,68,.08); }
        .standings-nota { margin-top: 12px; font-size: 11px; color: #555; text-align: center; }
        @media (max-width: 768px) { table.standings { font-size: 12px; } table.standings td.club img { width: 18px; height: 18px; } }


        /* --- ESTILOS PARA EL CELULAR DE TU AMIGO --- */
        @media (max-width: 768px) {
            .arena { flex-direction: column; gap: 30px; }
            .shield { width: 140px; height: 140px; padding: 15px; }
            .pos-badge { font-size: 14px; padding: 5px 10px; }
            .score-val { font-size: 50px; letter-spacing: 5px; }
            .bet-grid { grid-template-columns: 1fr; }
            .prob-bar { font-size: 12px; height: 50px; }
            .main-card { padding: 20px; border-radius: 20px; }
            h1 { font-size: 20px; }
            .h2h-container div { font-size: 18px !important; }
            table b { font-size: 18px !important; }
        }
    </style>
</head>
<body>
    <?php include 'includes/nav.php'; ?>
    <div class="container">
        <select onchange="location.href='?liga='+this.value" style="background: var(--green); color: black; margin-bottom: 20px;">
            <option value="TODAS" <?= $liga_actual == 'TODAS' ? 'selected' : '' ?>>🌍 TODAS LAS LIGAS (Super Clubes)</option>
            <option value="CL" <?= $liga_actual == 'CL' ? 'selected' : '' ?>>🏆 Champions League</option>
            <option value="PD" <?= $liga_actual == 'PD' ? 'selected' : '' ?>>🇪🇸 La Liga</option>
            <option value="PL" <?= $liga_actual == 'PL' ? 'selected' : '' ?>>🏴󠁧󠁢󠁥󠁮󠁧󠁿 Premier League</option>
        </select>

        <div class="main-card">
            <h1>SANTIAGO AI <span style="color:var(--green)">PREDICTOR PRO</span></h1>

            <form method="POST">
                <div class="arena">
                    <div>
                        <div class="shield">
                            <?php if($L): ?><div class="pos-badge">#<?= $L['posicion_tabla'] ?></div><?php endif; ?>
                            <img src="<?= $L['logo_url'] ?? '' ?>">
                        </div>
                        <div class="team-picker local">
                        <select name="local" onchange="this.form.submit()">
                            <option value="">CLUB LOCAL</option>
                            <?php foreach($equipos as $e): ?><option value="<?= $e['equipo_nombre'] ?>" <?= ($L['equipo_nombre']??'')==$e['equipo_nombre']?'selected':'' ?>><?= $e['equipo_nombre'] ?></option><?php endforeach; ?>
                        </select>
                        </div>
                    </div>

                    <?php if($L && $V): ?>
                    <div class="score-center">
                        <div class="score-label">PREDICCIÓN</div>
                        <div class="score-val"><?= round($mediaL) ?>-<?= round($mediaV) ?></div>
                        <div class="score-label">MARCADOR EXACTO</div>
                    </div>
                    <?php else: ?>
                    <div style="font-size: 60px; font-weight: 900; color: #222;">VS</div>
                    <?php endif; ?>

                    <div>
                        <div class="shield" style="border-color:var(--red);">
                            <?php if($V): ?><div class="pos-badge" style="background:silver;">#<?= $V['posicion_tabla'] ?></div><?php endif; ?>
                            <img src="<?= $V['logo_url'] ?? '' ?>">
                        </div>
                        <div class="team-picker visitante">
                        <select name="visitante" onchange="this.form.submit()">
                            <option value="">CLUB VISITANTE</option>
                            <?php foreach($equipos as $e): ?><option value="<?= $e['equipo_nombre'] ?>" <?= ($V['equipo_nombre']??'')==$e['equipo_nombre']?'selected':'' ?>><?= $e['equipo_nombre'] ?></option><?php endforeach; ?>
                        </select>
                        </div>
                    </div>
                </div>
            </form>

            <?php if ($liga_actual !== 'TODAS' && count($equipos) > 0): ?>
                <?php
                    $tabla_ordenada = $equipos;
                    usort($tabla_ordenada, fn($a, $b) => ($a['posicion_tabla'] ?? 999) <=> ($b['posicion_tabla'] ?? 999));
                    $n = count($tabla_ordenada);
                ?>
                <div class="standings-wrap">
                    <h2 class="standings-title">📋 Tabla de posiciones</h2>
                    <div class="standings-scroll">
                        <table class="standings">
                            <thead>
                                <tr><th>#</th><th>Club</th><th>PJ</th><th>G</th><th>E</th><th>P</th><th>GF</th><th>GC</th><th>DIF</th><th>PTS</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tabla_ordenada as $i => $e):
                                    $clases = [];
                                    if ($i < 4) $clases[] = 'zona-alta';
                                    if ($i >= $n - 3 && $n > 6) $clases[] = 'zona-baja';
                                    if ($L && $e['equipo_nombre'] === $L['equipo_nombre']) $clases[] = 'es-local';
                                    if ($V && $e['equipo_nombre'] === $V['equipo_nombre']) $clases[] = 'es-visitante';
                                    $dif = ($e['goles_favor'] ?? 0) - ($e['goles_contra'] ?? 0);
                                ?>
                                <tr class="<?= implode(' ', $clases) ?>">
                                    <td class="pos"><?= $e['posicion_tabla'] ?? '-' ?></td>
                                    <td class="club"><img src="<?= htmlspecialchars($e['logo_url'] ?? '') ?>" alt=""><?= htmlspecialchars($e['equipo_nombre']) ?></td>
                                    <td><?= $e['partidos_jugados'] ?? 0 ?></td>
                                    <td><?= $e['ganados'] ?? 0 ?></td>
                                    <td><?= $e['empatados'] ?? 0 ?></td>
                                    <td><?= $e['perdidos'] ?? 0 ?></td>
                                    <td><?= $e['goles_favor'] ?? 0 ?></td>
                                    <td><?= $e['goles_contra'] ?? 0 ?></td>
                                    <td><?= $dif > 0 ? '+' : '' ?><?= $dif ?></td>
                                    <td class="pts"><?= $e['puntos'] ?? '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="standings-nota">El borde verde/rojo marca las primeras y últimas posiciones de la tabla (no zonas oficiales de clasificación/descenso).</p>
                </div>
            <?php endif; ?>

            <?php if($L && $V): ?>
                <div class="prob-bar" style="box-shadow: 0 0 20px rgba(0,0,0,0.5); border: 2px solid #444;">
                    <div class="p-item" style="width:<?= $pL ?>%; background: linear-gradient(90deg, #008000, var(--green)); color:#000; font-weight:900;">L <?= $pL ?>%</div>
                    <div class="p-item" style="width:<?= $pE ?>%; background: #222; color:#fff;">E <?= $pE ?>%</div>
                    <div class="p-item" style="width:<?= $pV ?>%; background: linear-gradient(90deg, var(--red), #800000); color:#fff; font-weight:900;">V <?= $pV ?>%</div>
                </div>

                <div class="bet-grid">
                    <div class="bet-box">
                        <h3 style="color:var(--green); margin-top:0;">⚽ MERCADO DE GOLES</h3>
                        <?php
                            $p05 = min(99, round($promG * 38));
                            $p15 = min(96, round($promG * 32));
                            $p25 = min(89, round($promG * 25));
                            $m35 = 100 - min(35, round($promG * 8));
                            $m45 = 100 - min(15, round($promG * 4));
                            $ambos = ($mediaL >= 0.7 && $mediaV >= 0.7) ? 'SÍ' : 'NO';
                            $penal = ($L['penaltis_anotados'] + $V['penaltis_anotados'] > 0) ? 'SÍ' : 'NO';
                        ?>
                        <div class="bet-row"><span>+0.5</span><b><?= $p05 ?>%</b></div>
                        <div class="bet-row"><span>+1.5</span><b><?= $p15 ?>%</b></div>
                        <div class="bet-row"><span>+2.5</span><b><?= $p25 ?>%</b></div>
                        <div class="bet-row"><span>-3.5</span><b><?= $m35 ?>%</b></div>
                        <div class="bet-row"><span>-4.5</span><b><?= $m45 ?>%</b></div>
                        <div class="bet-row" style="border-top:1px solid #333; margin-top:5px; padding-top:5px;">
                            <span>Ambos Marcan</span><b><?= $ambos ?></b>
                        </div>
                        <div class="bet-row"><span>Penalti</span><b><?= $penal ?></b></div>
                    </div>

                    <div class="bet-box" style="border-left-color:var(--gold);">
                        <h3 style="color:var(--gold); margin-top:0;">⚔️ ACCIÓN Y CÓRNERS</h3>
                        <?php
                            $pjL = max($L['partidos_jugados'], 1);
                            $pjV = max($V['partidos_jugados'], 1);
                            $cTotalProm = ($L['corners_total'] + $V['corners_total']) / ($pjL + $pjV);
                            if($cTotalProm < 7.5) { $cTotalProm = 8.5; }
                            $p65 = min(99, round($cTotalProm * 12.5));
                            $p75 = min(96, round($cTotalProm * 11));
                            $p85 = min(88, round($cTotalProm * 9.5));
                            $p95 = min(78, round($cTotalProm * 8));
                            $remL = round(($L['goles_favor'] / $pjL) * 6.5 + 4);
                            $remV = round(($V['goles_favor'] / $pjV) * 6.5 + 4);
                            $arcoL = round($remL * 0.35);
                            $arcoV = round($remV * 0.35);
                            $totalArco = $arcoL + $arcoV;
                        ?>
                        <div class="bet-row"><span>🚩 +6.5</span><b><?= $p65 ?>%</b></div>
                        <div class="bet-row"><span>🚩 +7.5</span><b><?= $p75 ?>%</b></div>
                        <div class="bet-row"><span>🚩 +8.5</span><b><?= $p85 ?>%</b></div>
                        <div class="bet-row"><span>🚩 +9.5</span><b><?= $p95 ?>%</b></div>
                        <div class="bet-row"><span>🔥 Remates</span><b><?= $remL ?>/<?= $remV ?></b></div>
                        <div class="bet-row"><span>🎯 Arco</span><b><?= $arcoL ?>/<?= $arcoV ?></b></div>
                        <div class="bet-row"><span>DOBLE</span><b style="color:var(--gold)"><?= ($pL+$pE) ?>%|<?= ($pL+$pV) ?>%|<?= ($pV+$pE) ?>%</b></div>
                    </div>
                </div>

                <div class="h2h-container" style="margin-top:30px; background:#000; padding:20px; border-radius:20px;">
                    <h3 style="text-align:left; color:var(--green);">⚔️ ÚLTIMO RESULTADO</h3>
                    <?php if($h2h_real): $m = $h2h_real[0]; ?>
                        <div style="font-size:30px; font-weight:900;"><?= $m['homeTeam']['name'] ?> <span style="color:var(--gold)"><?= $m['score']['fullTime']['home'] ?>-<?= $m['score']['fullTime']['away'] ?></span> <?= $m['awayTeam']['name'] ?></div>
                    <?php else: echo "Sin previos."; endif; ?>
                </div>

                <div style="margin-top: 50px; background: #000; border-radius: 40px; padding: 25px; border: 2px solid #1a1e23;">
                    <h2 style="color: var(--gold); text-transform: uppercase; letter-spacing: 4px; font-size: 18px;">📊 ANALÍTICA
                        <?php if (!empty($L['datos_reales']) && !empty($V['datos_reales'])): ?>
                            <span style="font-size:11px; background:#0f5; color:#000; padding:3px 8px; border-radius:20px; vertical-align:middle;">✅ CÓRNERS/TARJETAS REALES</span>
                        <?php else: ?>
                            <span style="font-size:11px; background:#555; color:#fff; padding:3px 8px; border-radius:20px; vertical-align:middle;">⚠️ CÓRNERS/TARJETAS ESTIMADOS</span>
                        <?php endif; ?>
                    </h2>
                    <div style="overflow-x: auto;">
                    <table style="width:100%; border-collapse:separate; border-spacing: 0 12px; min-width: 640px;">
                        <tbody>
                            <?php
                            $pjL = max($L['partidos_jugados'], 1);
                            $pjV = max($V['partidos_jugados'], 1);
                            $filas = [
                                ['POSICIÓN', '#'.$L['posicion_tabla'], '#'.$V['posicion_tabla'], '🏆', false],
                                ['PUNTOS', $L['puntos'] ?? '-', $V['puntos'] ?? '-', '⭐', false],
                                ['PARTIDOS', $L['partidos_jugados'], $V['partidos_jugados'], '📅', false],
                                ['GOLES A FAVOR', $L['goles_favor'], $V['goles_favor'], '⚽', true],
                                ['GOLES EN CONTRA', $L['goles_contra'], $V['goles_contra'], '🛡️', true],
                                ['CÓRNERS', $L['corners_total'], $V['corners_total'], '⛳', true],
                                ['TARJETAS AMARILLAS', $L['tarjetas_amarillas'], $V['tarjetas_amarillas'], '🟨', true],
                                ['TARJETAS ROJAS', $L['tarjetas_rojas'], $V['tarjetas_rojas'], '🟥', true],
                                ['PENALES', $L['penaltis_anotados'], $V['penaltis_anotados'], '🎯', true],
                                ['ATAJADAS', $L['atajadas_portero'], $V['atajadas_portero'], '🧤', true],
                            ];
                            foreach($filas as $f):
                                $valL = floatval(str_replace(['#','%'], '', $f[1]));
                                $valV = floatval(str_replace(['#','%'], '', $f[2]));
                                $total = ($valL + $valV) > 0 ? ($valL + $valV) : 1;
                                $porcentL = ($valL / $total) * 100;
                                $conPromedio = $f[4];
                                $promL = $conPromedio ? round($valL / $pjL, 2) : null;
                                $promV = $conPromedio ? round($valV / $pjV, 2) : null;
                            ?>
                            <tr style="background: linear-gradient(180deg, #0f1319, #0a0d11); border-radius: 16px;">
                                <td style="padding:16px 12px; text-align:right; border-radius: 16px 0 0 16px; width:26%;">
                                    <b style="font-size:26px; color:var(--green);"><?= $f[1] ?></b>
                                    <?php if ($conPromedio): ?><div style="font-size:11px; color:#5a5; margin-top:2px;"><?= $promL ?>/partido</div><?php endif; ?>
                                </td>
                                <td style="padding:10px; text-align: center;">
                                    <div style="font-size:12px; margin-bottom: 8px; color:#aaa; letter-spacing:1px;"><?= $f[3] ?> <?= $f[0] ?></div>
                                    <div style="height: 14px; background: #1a1e23; border-radius: 20px; display: flex; overflow:hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,.6);">
                                        <div style="width: <?= $porcentL ?>%; background: linear-gradient(90deg, #0a5, var(--green)); transition: width .4s;"></div>
                                        <div style="width: <?= 100 - $porcentL ?>%; background: linear-gradient(90deg, var(--red), #a00); transition: width .4s;"></div>
                                    </div>
                                </td>
                                <td style="padding:16px 12px; text-align:left; border-radius: 0 16px 16px 0; width:26%;">
                                    <b style="font-size:26px; color:var(--red);"><?= $f[2] ?></b>
                                    <?php if ($conPromedio): ?><div style="font-size:11px; color:#c55; margin-top:2px;"><?= $promV ?>/partido</div><?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/chat_widget.php'; ?>
</body>
</html>