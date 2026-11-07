<?php
require_once __DIR__ . '/../core/alineaciones.php';

$alApiKey = getenv('HIGHLIGHTLY_API_KEY');
$alIdL = (int) ($realL['highlightly_id'] ?? 0);
$alIdV = (int) ($realV['highlightly_id'] ?? 0);

$alPartido = ($alIdL && $alIdV) ? alProximoPartido($alApiKey, $alIdL, $alIdV) : null;
$alData    = $alPartido ? alAlineacion($alApiKey, $alPartido) : null;
?>
<div class="al-wrap">
    <h2>🧑‍🤝‍🧑 11 inicial y convocados</h2>
    <?php
    $alZona = new DateTimeZone('America/Bogota');

    if (!$alIdL || !$alIdV): ?>
        <div class="al-aviso">Todavía no tengo vinculado a uno de los dos equipos con Highlightly, así que no puedo buscar su alineación.</div>

    <?php elseif (!$alPartido): ?>
        <div class="al-aviso">No encontré un partido próximo entre <b><?= htmlspecialchars($L['equipo_nombre']) ?></b> y <b><?= htmlspecialchars($V['equipo_nombre']) ?></b>. Las alineaciones solo existen para partidos reales programados.</div>

    <?php else:
        $alFecha = (new DateTime('@' . $alPartido['inicio']))->setTimezone($alZona)->format('d/m/Y H:i');
        $alCabecera = '<b>' . htmlspecialchars($alPartido['local']) . '</b> vs <b>' . htmlspecialchars($alPartido['visitante']) . '</b>'
                    . ($alPartido['liga'] ? ' · ' . htmlspecialchars($alPartido['liga']) : '')
                    . '<br>📅 ' . $alFecha . ' (hora de Colombia)';

        if ($alData['estado'] === 'pendiente'): ?>
            <div class="al-aviso"><?= $alCabecera ?><br><br>⏳ La alineación y los convocados se publican unos <b>40 minutos antes</b> del partido. Vuelve a entrar a esa hora y aparece aquí solo.</div>

        <?php elseif ($alData['estado'] === 'no_publicada'): ?>
            <div class="al-aviso"><?= $alCabecera ?><br><br>🕐 Ya casi: el partido está en la ventana, pero la alineación todavía no está publicada. Recarga en unos minutos (se actualiza cada 15 min).</div>

        <?php elseif ($alData['estado'] === 'error'): ?>
            <div class="al-aviso"><?= $alCabecera ?><br><br>⚠️ No pude traer la alineación ahora (código <?= (int) ($alData['http'] ?? 0) ?>). Si es 429, se acabó el cupo diario de Highlightly; vuelve a las 7:00 p.m. (hora Colombia).</div>

        <?php else:
            $alClaseA = ((int) ($alData['equipos'][0]['id'] ?? 0) === $alIdL) ? 'es-local' : 'es-visitante';
            $alClaseB = ($alClaseA === 'es-local') ? 'es-visitante' : 'es-local';
            ?>
            <div class="al-aviso"><?= $alCabecera ?></div>
            <div class="al-grid">
                <?= alHtmlEquipo($alData['equipos'][0], $alClaseA) ?>
                <?= alHtmlEquipo($alData['equipos'][1], $alClaseB) ?>
            </div>
        <?php endif;

        if (isset($_GET['debug'])): ?>
            <div class="al-debug">debug → idLocal <?= $alIdL ?> · idVisitante <?= $alIdV ?> · partido <?= (int) $alPartido['id'] ?> · estado API: <?= htmlspecialchars($alPartido['estado']) ?> · estado alineación: <?= htmlspecialchars($alData['estado']) ?></div>
        <?php endif;
    endif; ?>
</div>