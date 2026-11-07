<?php
require_once __DIR__ . '/../core/Clima.php';

$clKey = getenv('HIGHLIGHTLY_API_KEY');
$clIdL = (int) ($realL['highlightly_id'] ?? 0);
$clIdV = (int) ($realV['highlightly_id'] ?? 0);
$clPartido = ($clIdL && $clIdV) ? alProximoPartido($clKey, $clIdL, $clIdV) : null; // sale de la caché

if ($clPartido):
    $clFaltan   = $clPartido['inicio'] - time();
    $clDentro   = $clFaltan <= 15 * 86400;
    $clDetalle  = null;
    $clGeo      = null;
    $clHoras    = null;
    $clTiempo   = null;

    if ($clDentro) {
        $clDetalle = clDetallePartido($clKey, (int) $clPartido['id']);
        if ($clDetalle && !empty($clDetalle['ciudad'])) {
            $clGeo = clCoordenadas($clDetalle['ciudad'], $clDetalle['pais'] ?? null);
        }
        if ($clGeo) {
            $clHoras = clPronostico($clGeo['lat'], $clGeo['lon']);
        }
        if ($clHoras) {
            $clTiempo = clExtraer($clHoras, (int) $clPartido['inicio']);
        }
    }

    try {
        $clZona = new DateTimeZone($clGeo['tz'] ?? 'UTC');
    } catch (Exception $e) {
        $clZona = new DateTimeZone('UTC');
    }
    $clHoraLocal = (new DateTime('@' . $clPartido['inicio']))->setTimezone($clZona)->format('d/m/Y H:i');
?>
<div class="cl-wrap">
    <h2>🌦️ Clima del partido</h2>
    <?php
    $clCabecera = '<b>' . htmlspecialchars($clPartido['local']) . '</b> vs <b>' . htmlspecialchars($clPartido['visitante']) . '</b>'
                . ($clPartido['liga'] ? ' · ' . htmlspecialchars($clPartido['liga']) : '');

    if (!$clDentro): ?>
        <div class="cl-aviso"><?= $clCabecera ?><br><br>🗓️ El pronóstico aparece cuando falten <b>15 días o menos</b> para el partido.</div>

    <?php elseif (!$clDetalle || empty($clDetalle['ciudad'])): ?>
        <div class="cl-aviso"><?= $clCabecera ?><br><br>📍 Todavía no tengo la ciudad del estadio de este partido, así que no puedo consultar el clima.</div>

    <?php elseif (!$clGeo): ?>
        <div class="cl-aviso"><?= $clCabecera ?><br><br>📍 No pude ubicar la ciudad «<?= htmlspecialchars($clDetalle['ciudad']) ?>» en el mapa del clima.</div>

    <?php elseif (!$clHoras): ?>
        <div class="cl-aviso"><?= $clCabecera ?><br><br>⚠️ No pude consultar el servicio del clima ahora. Intenta de nuevo en unos minutos.</div>

    <?php elseif (!$clTiempo): ?>
        <div class="cl-aviso"><?= $clCabecera ?><br><br>🕐 Todavía no hay pronóstico para la hora de este partido.</div>

    <?php else:
        [$clEmoji, $clTexto] = clDescribirCodigo($clTiempo['codigo']);
        ?>
        <div class="cl-top">
            <div class="cl-icono"><?= $clEmoji ?></div>
            <div>
                <div class="cl-desc"><?= $clTexto ?></div>
                <div class="cl-sub"><?= $clCabecera ?></div>
                <div class="cl-sub">📍 <?= htmlspecialchars((string) ($clDetalle['estadio'] ?? 'Estadio')) ?>, <?= htmlspecialchars($clDetalle['ciudad']) ?> · 🕒 <?= $clHoraLocal ?> (hora local del estadio)</div>
            </div>
        </div>

        <div class="cl-tiles">
            <div class="cl-tile"><small>Temperatura</small><b><?= round($clTiempo['temp']) ?>°C</b><span>sensación <?= round($clTiempo['sensacion']) ?>°C</span></div>
            <div class="cl-tile"><small>Lluvia</small><b><?= round($clTiempo['prob_lluvia']) ?>%</b><span><?= $clTiempo['lluvia_mm'] ?> mm esperados</span></div>
            <div class="cl-tile"><small>Viento</small><b><?= round($clTiempo['viento']) ?> km/h</b><span>rachas de <?= round($clTiempo['racha']) ?></span></div>
            <div class="cl-tile"><small>Humedad</small><b><?= round($clTiempo['humedad']) ?>%</b><span>al pitazo</span></div>
        </div>

        <ul class="cl-tips">
            <?php foreach (clConsejos($clTiempo) as $clTip): ?>
                <li><?= $clTip ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="cl-credito"><a href="https://open-meteo.com/" target="_blank" rel="noopener">Weather data by Open-Meteo.com</a></div>
    <?php if (isset($_GET['debug'])): ?>
        <div class="cl-credito">debug → partido <?= (int) $clPartido['id'] ?> · ciudad <?= htmlspecialchars((string) ($clDetalle['ciudad'] ?? '-')) ?> · coords <?= $clGeo ? $clGeo['lat'] . ', ' . $clGeo['lon'] : '-' ?> · tz <?= htmlspecialchars((string) ($clGeo['tz'] ?? '-')) ?></div>
    <?php endif; ?>
</div>
<?php endif; ?>