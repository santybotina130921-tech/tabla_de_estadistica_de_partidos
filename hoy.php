<?php
require_once __DIR__ . '/core/partidos.php';

set_time_limit(60);

$dia = $_GET['dia'] ?? 'hoy';
if (!in_array($dia, ['ayer', 'hoy', 'manana'], true)) {
    $dia = 'hoy';
}

$datos = ptPartidosDelDia($dia);

$refrescar = 0;
if ($datos['pendientes'] > 0) {
    $refrescar = 6;
} elseif ($dia === 'hoy' && $datos['hayVivo']) {
    $refrescar = 120;
}

$etiquetasDia = ['ayer' => 'Ayer', 'hoy' => 'Hoy', 'manana' => 'Mañana'];
$etiquetasEstado = ['vivo' => 'EN VIVO', 'final' => 'FINAL', 'programado' => 'PROGRAMADO', 'aplazado' => 'APLAZADO'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partidos de hoy - SANTIAGO AI</title>
    <link rel="stylesheet" href="assets/css/extras.css?v=<?= filemtime(__DIR__ . '/assets/css/extras.css') ?>">
    <?php if ($refrescar): ?>
        <meta http-equiv="refresh" content="<?= $refrescar ?>">
    <?php endif; ?>
</head>
<body class="pagina-simple">
    <?php include 'includes/nav.php'; ?>

    <div class="contenedor">
        <h1 class="hist-titulo">📅 Partidos <span><?= $etiquetasDia[$dia] ?></span></h1>
        <p class="hoy-fecha"><?= htmlspecialchars(ptEtiquetaFecha($datos['fecha'])) ?> · hora de Colombia</p>

        <div class="hoy-tabs">
            <?php foreach ($etiquetasDia as $clave => $etiqueta): ?>
                <a class="hoy-tab <?= $clave === $dia ? 'activo' : '' ?>" href="hoy.php?dia=<?= $clave ?>"><?= $etiqueta ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($datos['pendientes'] > 0): ?>
            <div class="h2h-aviso">Cargando ligas… faltan <?= (int) $datos['pendientes'] ?>. Esta página se actualiza sola en unos segundos.</div>
        <?php endif; ?>

        <?php foreach ($datos['avisos'] as $aviso): ?>
            <div class="h2h-aviso"><?= htmlspecialchars($aviso) ?></div>
        <?php endforeach; ?>

        <?php if (!$datos['grupos']): ?>
            <div class="hist-vacio">No hay partidos de las ligas que seguimos para este día.</div>
        <?php else: ?>
            <p class="hoy-resumen"><b><?= (int) $datos['total'] ?></b> partidos<?= $datos['hayVivo'] ? ' · hay partidos en vivo' : '' ?></p>

            <?php foreach ($datos['grupos'] as $grupo): ?>
                <div class="hoy-liga">
                    <h3><?= htmlspecialchars($grupo['titulo']) ?></h3>

                    <?php foreach ($grupo['partidos'] as $p):
                        $conMarcador = ($p['estado'] === 'vivo' || $p['estado'] === 'final') && $p['gl'] !== null && $p['gv'] !== null;
                        $urlAnalizar = 'index.php?liga=' . rawurlencode($p['liga_codigo']) . '&local=' . rawurlencode($p['local']) . '&visitante=' . rawurlencode($p['visitante']);
                        ?>
                        <div class="hoy-partido">
                            <div class="hoy-hora"><?= ptHora($p['inicio']) ?></div>

                            <div class="hoy-equipos">
                                <span class="hoy-equipo">
                                    <?php if ($p['local_logo']): ?><img src="<?= htmlspecialchars($p['local_logo']) ?>" alt="" width="22" height="22" loading="lazy" decoding="async"><?php endif; ?>
                                    <?= htmlspecialchars($p['local']) ?>
                                </span>
                                <span class="hoy-marcador"><?= $conMarcador ? (int) $p['gl'] . ' - ' . (int) $p['gv'] : 'vs' ?></span>
                                <span class="hoy-equipo">
                                    <?php if ($p['visitante_logo']): ?><img src="<?= htmlspecialchars($p['visitante_logo']) ?>" alt="" width="22" height="22" loading="lazy" decoding="async"><?php endif; ?>
                                    <?= htmlspecialchars($p['visitante']) ?>
                                </span>
                            </div>

                            <div class="hoy-lado">
                                <span class="hoy-estado estado-<?= $p['estado'] ?>">
                                    <?= $etiquetasEstado[$p['estado']] ?><?= ($p['estado'] === 'vivo' && $p['minuto']) ? ' ' . (int) $p['minuto'] . "'" : '' ?>
                                </span>
                                <a class="hoy-analizar" href="<?= htmlspecialchars($urlAnalizar) ?>">Analizar →</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>