<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/predicciones.php';

function histPorcentaje(int $parte, int $total): string
{
    return $total > 0 ? round($parte * 100 / $total) . '%' : '—';
}

$apiKey     = getenv('HIGHLIGHTLY_API_KEY');
$errorTabla = false;
$nuevos     = 0;
$filas      = [];
$stats      = ['total' => 0, 'x12' => 0, 'exacto' => 0, 'over25' => 0, 'over25_n' => 0, 'btts' => 0, 'btts_n' => 0];

try {
    $nuevos = resolverPendientes($conexion, $apiKey, 3);
    $stats  = estadisticasAciertos($conexion);
    $filas  = $conexion->query("SELECT * FROM predicciones ORDER BY inicio DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorTabla = true;
    error_log('Historial: ' . $e->getMessage());
}

$zona = new DateTimeZone('America/Bogota');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de aciertos - SANTIAGO AI</title>
    <link rel="stylesheet" href="assets/css/extras.css">
</head>
<body class="pagina-simple">
    <?php include 'includes/nav.php'; ?>

    <div class="contenedor">
        <h1 class="hist-titulo">📈 Historial de <span>aciertos</span></h1>

        <?php if ($errorTabla): ?>
            <div class="hist-wrap">
                <div class="h2h-aviso">No pude leer la tabla de predicciones. ¿Ya corriste <b>migracion_extras.sql</b> en Supabase?</div>
            </div>

        <?php else: ?>
            <div class="hist-wrap">
                <h2>Cuántas veces acertó el modelo</h2>

                <div class="hist-cifras">
                    <div class="hist-cifra">
                        <small>Ganador / empate</small>
                        <b><?= histPorcentaje($stats['x12'], $stats['total']) ?></b>
                        <span><?= $stats['x12'] ?> de <?= $stats['total'] ?></span>
                    </div>
                    <div class="hist-cifra">
                        <small>Marcador exacto</small>
                        <b><?= histPorcentaje($stats['exacto'], $stats['total']) ?></b>
                        <span><?= $stats['exacto'] ?> de <?= $stats['total'] ?></span>
                    </div>
                    <div class="hist-cifra">
                        <small>Más/menos de 2.5 goles</small>
                        <b><?= histPorcentaje($stats['over25'], $stats['over25_n']) ?></b>
                        <span><?= $stats['over25'] ?> de <?= $stats['over25_n'] ?></span>
                    </div>
                    <div class="hist-cifra">
                        <small>Ambos marcan</small>
                        <b><?= histPorcentaje($stats['btts'], $stats['btts_n']) ?></b>
                        <span><?= $stats['btts'] ?> de <?= $stats['btts_n'] ?></span>
                    </div>
                </div>

                <div class="hist-nota">
                    <?php if ($stats['total'] < 30): ?>
                        Por ahora son solo <b><?= $stats['total'] ?></b> partidos terminados: con tan pocos, el porcentaje no dice casi nada. Se vuelve confiable con 30 o más.
                    <?php else: ?>
                        Sobre <b><?= $stats['total'] ?></b> partidos terminados. Para ganador/empate, elegir al azar acierta cerca del 33%; un modelo útil debería quedar claramente por encima.
                    <?php endif; ?>
                    Solo cuentan predicciones de partidos reales guardadas antes de que empezaran; nunca se cambian después.
                    <?php if ($nuevos > 0): ?>
                        <br>Se registraron <b><?= $nuevos ?></b> resultados nuevos al abrir esta página.
                    <?php endif; ?>
                </div>

                <?php if (!$filas): ?>
                    <div class="hist-vacio">Todavía no hay predicciones guardadas. Entra al predictor y elige dos equipos que tengan un partido próximo entre ellos: la predicción se guarda sola.</div>
                <?php else: ?>
                    <div class="hist-scroll">
                        <table class="historial">
                            <thead>
                                <tr>
                                    <th>Fecha (Colombia)</th>
                                    <th>Partido</th>
                                    <th>Predicción</th>
                                    <th>Resultado</th>
                                    <th>¿Acertó?</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filas as $f):
                                    $fecha = (new DateTime($f['inicio']))->setTimezone($zona)->format('d/m H:i');
                                    $pick  = pickPrediccion($f);
                                    $textoPick = ($pick === 'L') ? 'Gana ' . $f['local_nombre'] : (($pick === 'V') ? 'Gana ' . $f['visitante_nombre'] : 'Empate');
                                    $resuelto = ($f['resultado_local'] !== null);
                                    ?>
                                    <tr>
                                        <td><?= $fecha ?></td>
                                        <td><?= htmlspecialchars($f['local_nombre']) ?> vs <?= htmlspecialchars($f['visitante_nombre']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($textoPick) ?>
                                            (<?= (int) $f['prob_local'] ?>/<?= (int) $f['prob_empate'] ?>/<?= (int) $f['prob_visitante'] ?>)
                                            · <?= (int) $f['marcador_local'] ?>-<?= (int) $f['marcador_visitante'] ?>
                                        </td>
                                        <?php if ($resuelto): ?>
                                            <td><?= (int) $f['resultado_local'] ?>-<?= (int) $f['resultado_visitante'] ?></td>
                                            <?php if ($pick === resultadoReal($f)): ?>
                                                <td class="ok">✅ Sí</td>
                                            <?php else: ?>
                                                <td class="mal">❌ No</td>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <td class="pendiente">—</td>
                                            <td class="pendiente">⏳ pendiente</td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>