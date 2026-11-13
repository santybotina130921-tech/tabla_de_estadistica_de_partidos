<?php
require_once __DIR__ . '/core/detalle.php';

$id = (int) ($_GET['id'] ?? 0);
$d = $id ? hlDetalleCompleto(getenv('HIGHLIGHTLY_API_KEY'), $id) : null;
$zona = new DateTimeZone('America/Bogota');

$etiquetasEstado = ['vivo' => 'EN VIVO', 'final' => 'FINAL', 'programado' => 'PROGRAMADO', 'aplazado' => 'APLAZADO'];

$iconos = [
    'goal'         => '⚽',
    'own goal'     => '⚽ (autogol)',
    'penalty'      => '🎯',
    'yellow card'  => '🟨',
    'red card'     => '🟥',
    'substitution' => '🔁',
];

$nombresStats = [
    'Possession'       => 'Posesión',
    'Expected Goals'   => 'Goles esperados (xG)',
    'Shots on target'  => 'Tiros al arco',
    'Shots off target' => 'Tiros desviados',
    'Blocked shots'    => 'Tiros bloqueados',
    'Corners'          => 'Córners',
    'Fouls'            => 'Faltas',
    'Yellow cards'     => 'Tarjetas amarillas',
    'Red cards'        => 'Tarjetas rojas',
    'Offsides'         => 'Fueras de lugar',
    'Goalkeeper saves' => 'Atajadas',
    'Total passes'     => 'Pases',
];

function ptClasePorcentaje(float $valor, float $total): string
{
    if ($total <= 0) {
        return 'pct-0';
    }
    return 'pct-' . (int) (round(($valor / $total) * 100 / 5) * 5);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen del partido - SANTIAGO AI</title>
    <link rel="stylesheet" href="assets/css/extras.css?v=<?= filemtime(__DIR__ . '/assets/css/extras.css') ?>">
</head>
<body class="pagina-simple">
    <?php include 'includes/nav.php'; ?>

    <div class="contenedor">
        <p class="sync-volver"><a href="hoy.php">← Partidos de hoy</a></p>

        <?php if (!$d): ?>
            <div class="hist-wrap">
                <div class="h2h-aviso">
                    No pude cargar este partido ahora mismo. Puede ser que el número no exista o que se haya agotado el cupo diario de la API.
                    <br>
                    <a class="hoy-analizar" href="partido.php?id=<?= $id ?>">Reintentar</a>
                </div>
            </div>
        <?php else:
            $conMarcador = ($d['estado'] === 'vivo' || $d['estado'] === 'final') && $d['gl'] !== null && $d['gv'] !== null;
            $fecha = (new DateTime('@' . $d['inicio']))->setTimezone($zona)->format('d/m/Y H:i');
            ?>
            <div class="hist-wrap">
                <p class="pt-liga"><?= htmlspecialchars($d['liga']) ?><?= $d['ronda'] ? ' · ' . htmlspecialchars($d['ronda']) : '' ?></p>

                <div class="pt-cabecera">
                    <div class="pt-equipo">
                        <?php if ($d['local']['logo']): ?><img src="<?= htmlspecialchars($d['local']['logo']) ?>" alt="" width="64" height="64" decoding="async"><?php endif; ?>
                        <b><?= htmlspecialchars($d['local']['nombre']) ?></b>
                    </div>
                    <div class="pt-centro">
                        <div class="pt-marcador"><?= $conMarcador ? (int) $d['gl'] . ' - ' . (int) $d['gv'] : 'vs' ?></div>
                        <span class="hoy-estado estado-<?= $d['estado'] ?>">
                            <?= $etiquetasEstado[$d['estado']] ?><?= ($d['estado'] === 'vivo' && $d['minuto']) ? ' ' . (int) $d['minuto'] . "'" : '' ?>
                        </span>
                        <div class="pt-fecha"><?= $fecha ?> (hora de Colombia)</div>
                    </div>
                    <div class="pt-equipo">
                        <?php if ($d['visitante']['logo']): ?><img src="<?= htmlspecialchars($d['visitante']['logo']) ?>" alt="" width="64" height="64" decoding="async"><?php endif; ?>
                        <b><?= htmlspecialchars($d['visitante']['nombre']) ?></b>
                    </div>
                </div>

                <div class="pt-info">
                    <?php if ($d['estadio']['nombre']): ?>
                        <span>🏟️ <?= htmlspecialchars($d['estadio']['nombre']) ?><?= $d['estadio']['ciudad'] ? ', ' . htmlspecialchars($d['estadio']['ciudad']) : '' ?><?= $d['estadio']['capacidad'] ? ' · ' . number_format((int) $d['estadio']['capacidad'], 0, ',', '.') . ' personas' : '' ?></span>
                    <?php endif; ?>
                    <?php if ($d['arbitro']['nombre']): ?>
                        <span>🧑‍⚖️ Árbitro: <?= htmlspecialchars($d['arbitro']['nombre']) ?><?= $d['arbitro']['pais'] ? ' (' . htmlspecialchars($d['arbitro']['pais']) . ')' : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hist-wrap">
                <h2>⏱️ Cronología</h2>
                <?php if (!$d['eventos']): ?>
                    <div class="h2h-aviso">Todavía no hay eventos registrados (goles, tarjetas o cambios).</div>
                <?php else: ?>
                    <?php foreach ($d['eventos'] as $e):
                        $clave = strtolower($e['tipo']);
                        $icono = $iconos[$clave] ?? (strpos($clave, 'penalty') !== false ? '🎯' : '•');
                        ?>
                        <div class="pt-evento <?= $e['lado'] === 0 ? 'pt-ev-local' : 'pt-ev-visitante' ?> <?= $clave === 'substitution' ? 'pt-ev-cambio' : '' ?>">
                            <span class="pt-min"><?= htmlspecialchars($e['minuto']) ?>'</span>
                            <span class="pt-icono"><?= $icono ?></span>
                            <span class="pt-texto">
                                <?php if ($clave === 'substitution'): ?>
                                    Sale <?= htmlspecialchars($e['jugador']) ?> · entra <?= htmlspecialchars($e['cambio']) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($e['jugador']) ?><?= $e['asistencia'] ? ' (asistencia: ' . htmlspecialchars($e['asistencia']) . ')' : '' ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="hist-wrap">
                <h2>📊 Estadísticas</h2>
                <?php
                $filasStats = [];
                foreach ($nombresStats as $clave => $nombre) {
                    if (isset($d['stats'][$clave][0], $d['stats'][$clave][1])) {
                        $filasStats[] = [$nombre, $clave, (float) $d['stats'][$clave][0], (float) $d['stats'][$clave][1]];
                    }
                }
                ?>
                <?php if (!$filasStats): ?>
                    <div class="h2h-aviso">Todavía no hay estadísticas de este partido.</div>
                <?php else: ?>
                    <div class="graf-leyenda">
                        <span class="graf-chip graf-chip-local"></span><?= htmlspecialchars($d['local']['nombre']) ?>
                        <span class="graf-chip graf-chip-visitante"></span><?= htmlspecialchars($d['visitante']['nombre']) ?>
                    </div>
                    <?php foreach ($filasStats as [$nombre, $clave, $a, $b]):
                        $esPosesion = ($clave === 'Possession');
                        $ta = $esPosesion ? round($a * 100) : round($a, 2);
                        $tb = $esPosesion ? round($b * 100) : round($b, 2);
                        $suma = $a + $b;
                        ?>
                        <div class="pt-stat">
                            <div class="pt-stat-cifras">
                                <b class="pt-stat-local"><?= $ta ?><?= $esPosesion ? '%' : '' ?></b>
                                <span><?= htmlspecialchars($nombre) ?></span>
                                <b class="pt-stat-visitante"><?= $tb ?><?= $esPosesion ? '%' : '' ?></b>
                            </div>
                            <div class="pt-barra">
                                <div class="pt-barra-local <?= ptClasePorcentaje($a, $suma) ?>"></div>
                                <div class="pt-barra-visitante <?= ptClasePorcentaje($b, $suma) ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($d['prediccion']): ?>
                <div class="hist-wrap">
                    <h2>🤖 Probabilidades de Highlightly</h2>
                    <div class="hist-cifras hist-cifras-tres">
                        <div class="hist-cifra"><small>Gana <?= htmlspecialchars($d['local']['nombre']) ?></small><b><?= (int) $d['prediccion']['local'] ?>%</b></div>
                        <div class="hist-cifra"><small>Empate</small><b><?= (int) $d['prediccion']['empate'] ?>%</b></div>
                        <div class="hist-cifra"><small>Gana <?= htmlspecialchars($d['visitante']['nombre']) ?></small><b><?= (int) $d['prediccion']['visitante'] ?>%</b></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($d['destacados'][0] || $d['destacados'][1]): ?>
                <div class="hist-wrap">
                    <h2>⭐ Jugadores destacados</h2>
                    <div class="al-grid">
                        <?php foreach ([0 => $d['local']['nombre'], 1 => $d['visitante']['nombre']] as $lado => $nombreEquipo): ?>
                            <div class="pt-destacados">
                                <h3><?= htmlspecialchars($nombreEquipo) ?></h3>
                                <?php foreach ($d['destacados'][$lado] as $j): ?>
                                    <p><b><?= htmlspecialchars($j['nombre']) ?></b> <small><?= htmlspecialchars($j['posicion']) ?></small><br><span><?= htmlspecialchars($j['estadisticas']) ?></span></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($d['noticias']): ?>
                <div class="hist-wrap">
                    <h2>📰 Noticias del partido</h2>
                    <?php foreach ($d['noticias'] as $n):
                        $fechaNoticia = $n['fecha'] ? (new DateTime($n['fecha']))->setTimezone($zona)->format('d/m H:i') : '';
                        ?>
                        <p class="pt-noticia">
                            <a href="<?= htmlspecialchars($n['url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($n['titulo']) ?></a>
                            <small><?= $fechaNoticia ?></small>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>