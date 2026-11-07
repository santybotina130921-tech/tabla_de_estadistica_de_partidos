<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/mapeo.php';

$claveAdmin = (string) getenv('ADMIN_CLAVE');
$claveRecibida = (string) ($_GET['clave'] ?? '');

if ($claveAdmin === '' || !hash_equals($claveAdmin, $claveRecibida)) {
    http_response_code(403);
    echo 'Acceso restringido. Define la variable ADMIN_CLAVE en Render y entra con ?clave=TU_CLAVE';
    exit;
}

$ligas = mpLigasFootballData();

if (isset($_GET['liga'])) {
    header('Content-Type: application/json; charset=utf-8');
    $codigo = (string) $_GET['liga'];
    if (!isset($ligas[$codigo])) {
        echo json_encode(['ok' => false, 'msg' => 'Liga no reconocida.']);
        exit;
    }
    try {
        $resultado = mpMapearLiga($conexion, getenv('HIGHLIGHTLY_API_KEY'), $codigo, $ligas[$codigo], 2026, isset($_GET['id']) ? (int) $_GET['id'] : null);
    } catch (Exception $e) {
        error_log('Mapear equipos: ' . $e->getMessage());
        $resultado = ['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()];
    }
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Vincular equipos con Highlightly</title>
    <link rel="stylesheet" href="assets/css/extras.css?v=<?= filemtime(__DIR__ . '/assets/css/extras.css') ?>">
</head>
<body class="pagina-simple">
    <div class="contenedor">
        <h1 class="hist-titulo">🔗 Vincular <span>equipos</span></h1>
        <div class="hist-nota">
            Une cada equipo de football-data.org con su ID de Highlightly para que funcionen las estadísticas, alineaciones, cara a cara y clima en todas las ligas.
            Gasta unas 2 peticiones de Highlightly por liga (la primera vez) y no repite equipos que ya están vinculados.
        </div>
        <div id="log"></div>
    </div>

    <script>
        const ligas = <?= json_encode(array_keys($ligas)) ?>;
        const clave = <?= json_encode($claveRecibida) ?>;
        const log = document.getElementById('log');

        function linea(texto, clase) {
            const p = document.createElement('div');
            p.className = 'mapeo-linea ' + (clase || '');
            p.textContent = texto;
            log.appendChild(p);
            return p;
        }

        function detalle(texto) {
            const p = document.createElement('div');
            p.className = 'mapeo-detalle';
            p.textContent = texto;
            log.appendChild(p);
        }

        async function pedirJson(url) {
            const res = await fetch(url);
            const texto = await res.text();
            try {
                return JSON.parse(texto);
            } catch (e) {
                return { ok: false, msg: 'respuesta no válida (HTTP ' + res.status + '): ' + texto.replace(/<[^>]*>/g, ' ').trim().slice(0, 200) };
            }
        }

        async function vincular() {
            for (const liga of ligas) {
                const fila = linea('⏳ ' + liga + '...');
                try {
                    const d = await pedirJson('mapear-equipos.php?clave=' + encodeURIComponent(clave) + '&liga=' + liga);
                    if (!d.ok) {
                        fila.textContent = '⚠️ ' + liga + ': ' + d.msg;
                        fila.className = 'mapeo-linea mal';
                    } else {
                        fila.textContent = '✅ ' + liga + ': ' + d.mapeados + ' vinculados ahora, ' + d.ya_estaban + ' ya estaban, ' + d.sin_mapear.length + ' sin vincular (de ' + d.total + ').';
                        fila.className = 'mapeo-linea ' + (d.sin_mapear.length ? 'mal' : 'ok');
                        d.detalle.filter(x => x.score < 0.8).forEach(x => detalle('revisar: ' + x.nombre + ' → ' + x.hl + ' (' + x.metodo + ', ' + x.score + ')'));
                        d.sin_mapear.forEach(x => detalle('sin vincular: ' + x.nombre + (x.candidato ? ' (¿' + x.candidato + '?)' : '')));
                    }
                } catch (e) {
                    fila.textContent = '⚠️ ' + liga + ': no hubo conexión con el servidor (' + e.message + ').';
                    fila.className = 'mapeo-linea mal';
                }
                await new Promise(r => setTimeout(r, 1200));
            }
            linea('Listo.', 'ok');
        }

        vincular();
    </script>
</body>
</html>