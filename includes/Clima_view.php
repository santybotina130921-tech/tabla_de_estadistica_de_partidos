<?php
/**
 * includes/clima_view.php
 * Tarjeta "Clima del partido". Se incluye desde index.php cuando ya hay dos
 * equipos elegidos. Si no hay partido próximo entre ellos, no muestra nada
 * (el bloque de alineaciones ya lo avisa).
 */
require_once __DIR__ . '/../core/Clima.php'; // trae clDetallePartido(), clCoordenadas(), clPronostico(), clExtraer(), clDescribirCodigo(), clConsejos()

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
<style>
    .cl-wrap { margin-top: 40px; background: #000; border-radius: 30px; padding: 25px; border: 2px solid #1a1e23; text-align: left; }
    .cl-wrap h2 { color: var(--gold); text-transform: uppercase; letter-spacing: 3px; font-size: 16px; margin: 0 0 14px; text-align: center; }
    .cl-aviso { background: #0d1117; border: 1px solid #1f242b; border-radius: 16px; padding: 16px; color: #bbb; font-size: 14px; line-height: 1.5; text-align: center; }
    .cl-aviso b { color: #fff; }
    .cl-top { display: flex; align-items: center; justify-content: center; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
    .cl-icono { font-size: 56px; line-height: 1; }
    .cl-desc { font-size: 22px; font-weight: 800; }
    .cl-sub { color: #999; font-size: 13px; margin-top: 2px; }
    .cl-tiles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .cl-tile { background: #0d1117; border-radius: 16px; padding: 14px 8px; text-align: center; border: 1px solid #1a1e23; }
    .cl-tile small { display: block; color: #777; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
    .cl-tile b { font-size: 22px; color: var(--green); }
    .cl-tile span { display: block; font-size: 11px; color: #888; margin-top: 3px; }
    .cl-tips { margin: 14px 0 0; padding: 0; list-style: none; }
    .cl-tips li { background: #0d1117; border-left: 4px solid var(--gold); border-radius: 10px; padding: 9px 12px; margin-bottom: 6px; font-size: 13px; color: #ddd; }
    .cl-credito { margin-top: 10px; font-size: 11px; color: #555; text-align: center; }
    .cl-credito a { color: #777; }
    @media (max-width: 768px) { .cl-tiles { grid-template-columns: repeat(2, 1fr); } }
</style>

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