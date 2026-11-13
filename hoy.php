<?php
require_once __DIR__ . '/core/partidos.php';

set_time_limit(60);

$dia = $_GET['dia'] ?? 'hoy';
if (!in_array($dia, ['ayer', 'hoy', 'manana'], true)) {
    $dia = 'hoy';
}

if (($_GET['formato'] ?? '') === 'json') {
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        $datos = ptPartidosDelDia($dia);
        $grupos = [];
        foreach ($datos['grupos'] as $g) {
            $lista = [];
            foreach ($g['partidos'] as $p) {
                $lista[] = [
                    'k'              => $p['liga_codigo'] . '|' . $p['local'] . '|' . $p['visitante'],
                    'hora'           => ptHora($p['inicio']),
                    'estado'         => $p['estado'],
                    'minuto'         => $p['minuto'],
                    'local'          => $p['local'],
                    'local_logo'     => $p['local_logo'],
                    'visitante'      => $p['visitante'],
                    'visitante_logo' => $p['visitante_logo'],
                    'gl'             => $p['gl'],
                    'gv'             => $p['gv'],
                    'url'            => 'index.php?liga=' . rawurlencode($p['liga_codigo']) . '&local=' . rawurlencode($p['local']) . '&visitante=' . rawurlencode($p['visitante']),
                    'resumen'        => $p['hl_id'] ? 'partido.php?id=' . (int) $p['hl_id'] : null,
                ];
            }
            $grupos[] = ['codigo' => $g['codigo'], 'titulo' => $g['titulo'], 'partidos' => $lista];
        }
        echo json_encode([
            'fecha'      => $datos['fecha'],
            'grupos'     => $grupos,
            'avisos'     => $datos['avisos'],
            'total'      => $datos['total'],
            'pendientes' => $datos['pendientes'],
            'hayVivo'    => $datos['hayVivo'],
            'edad_min'   => intdiv((int) $datos['edad'], 60),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('hoy.php: ' . $e->getMessage());
        echo json_encode(['error' => 'No se pudieron cargar los partidos. Intenta de nuevo en un momento.']);
    }
    exit;
}

$base = new DateTime('now', new DateTimeZone('America/Bogota'));
if ($dia === 'ayer') {
    $base->modify('-1 day');
}
if ($dia === 'manana') {
    $base->modify('+1 day');
}
$etiquetaFecha = ptEtiquetaFecha($base->format('Y-m-d'));
$etiquetasDia = ['ayer' => 'Ayer', 'hoy' => 'Hoy', 'manana' => 'Mañana'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partidos de hoy - SANTIAGO AI</title>
    <link rel="stylesheet" href="assets/css/extras.css?v=<?= filemtime(__DIR__ . '/assets/css/extras.css') ?>">
</head>
<body class="pagina-simple">
    <?php include 'includes/nav.php'; ?>

    <div class="contenedor">
        <h1 class="hist-titulo">📅 Partidos <span><?= $etiquetasDia[$dia] ?></span></h1>
        <p class="hoy-fecha"><?= htmlspecialchars($etiquetaFecha) ?> · hora de Colombia</p>

        <div class="hoy-tabs">
            <?php foreach ($etiquetasDia as $clave => $etiqueta): ?>
                <a class="hoy-tab <?= $clave === $dia ? 'activo' : '' ?>" href="hoy.php?dia=<?= $clave ?>"><?= $etiqueta ?></a>
            <?php endforeach; ?>
        </div>

        <div class="hoy-buscador">
            <input type="search" id="hoy-buscar" class="hoy-input" placeholder="Buscar equipo o liga..." autocomplete="off" aria-label="Buscar equipo o liga">
        </div>

        <p class="hoy-nota" id="hoy-nota" aria-live="polite"></p>
        <div id="hoy-avisos"></div>

        <div id="hoy-app" data-url="hoy.php?formato=json&amp;dia=<?= $dia ?>" data-dia="<?= $dia ?>">
            <div class="skeleton-liga">
                <div class="skeleton-linea skeleton-titulo"></div>
                <div class="skeleton-fila"><div class="skeleton-linea"></div><div class="skeleton-linea"></div><div class="skeleton-linea"></div></div>
                <div class="skeleton-fila"><div class="skeleton-linea"></div><div class="skeleton-linea"></div><div class="skeleton-linea"></div></div>
                <div class="skeleton-fila"><div class="skeleton-linea"></div><div class="skeleton-linea"></div><div class="skeleton-linea"></div></div>
            </div>
            <div class="skeleton-liga">
                <div class="skeleton-linea skeleton-titulo"></div>
                <div class="skeleton-fila"><div class="skeleton-linea"></div><div class="skeleton-linea"></div><div class="skeleton-linea"></div></div>
                <div class="skeleton-fila"><div class="skeleton-linea"></div><div class="skeleton-linea"></div><div class="skeleton-linea"></div></div>
            </div>
        </div>

        <noscript>
            <div class="h2h-aviso">Esta página necesita JavaScript para mostrar los partidos.</div>
        </noscript>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/chat_widget.php'; ?>
    <script src="assets/js/hoy.js?v=<?= filemtime(__DIR__ . '/assets/js/hoy.js') ?>" defer></script>
</body>
</html>