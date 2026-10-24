<?php
/**
 * includes/alineaciones_view.php
 * Bloque "11 inicial y convocados". Se incluye desde index.php cuando ya hay
 * dos equipos elegidos. Usa $realL y $realV (los datos reales que ya calcula index.php).
 */
require_once __DIR__ . '/../core/alineaciones.php';

$alApiKey = getenv('HIGHLIGHTLY_API_KEY');
$alIdL = (int) ($realL['highlightly_id'] ?? 0);
$alIdV = (int) ($realV['highlightly_id'] ?? 0);

$alPartido = ($alIdL && $alIdV) ? alProximoPartido($alApiKey, $alIdL, $alIdV) : null;
$alData    = $alPartido ? alAlineacion($alApiKey, $alPartido) : null;
?>
<style>
    .al-wrap { margin-top: 40px; background: #000; border-radius: 30px; padding: 25px; border: 2px solid #1a1e23; text-align: left; }
    .al-wrap h2 { color: var(--gold); text-transform: uppercase; letter-spacing: 3px; font-size: 16px; margin: 0 0 14px; text-align: center; }
    .al-aviso { background: #0d1117; border: 1px solid #1f242b; border-radius: 16px; padding: 16px; color: #bbb; font-size: 14px; line-height: 1.5; text-align: center; }
    .al-aviso b { color: #fff; }
    .al-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px; }
    .al-team { background: #0d1117; border-radius: 20px; padding: 14px; border-top: 4px solid var(--green); }
    .al-team.es-visitante { border-top-color: var(--red); }
    .al-head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
    .al-head img { width: 28px; height: 28px; object-fit: contain; }
    .al-form { margin-left: auto; background: #1a1e23; color: var(--gold); font-weight: 800; padding: 3px 10px; border-radius: 20px; font-size: 12px; }
    .al-pitch { display: flex; flex-direction: column; justify-content: space-around; gap: 8px; min-height: 380px; padding: 14px 6px; border-radius: 14px; border: 2px solid rgba(255,255,255,.25);
        background: repeating-linear-gradient(0deg, #0b5d1e 0 38px, #0e6b24 38px 76px); }
    .al-row { display: flex; justify-content: space-around; align-items: flex-start; }
    .al-player { display: flex; flex-direction: column; align-items: center; width: 62px; }
    .al-num { width: 32px; height: 32px; border-radius: 50%; background: #fff; color: #000; font-weight: 900; font-size: 13px; display: flex; align-items: center; justify-content: center; border: 3px solid var(--green); }
    .es-visitante .al-num { border-color: var(--red); }
    .al-name { margin-top: 3px; font-size: 10px; font-weight: 700; color: #fff; text-shadow: 0 1px 2px #000; max-width: 62px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .al-bench h4 { margin: 14px 0 6px; font-size: 13px; color: #aaa; }
    .al-grupo { margin-bottom: 6px; }
    .al-grupo small { display: block; color: #666; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 3px; }
    .al-chip { display: inline-block; background: #1a1e23; color: #ddd; font-size: 11px; padding: 3px 8px; border-radius: 12px; margin: 0 4px 4px 0; }
    .al-debug { margin-top: 10px; font-size: 11px; color: #555; text-align: center; }
    @media (max-width: 768px) { .al-grid { grid-template-columns: 1fr; } }
</style>

<div class="al-wrap">
    <h2>🧑‍🤝‍🧑 11 inicial y convocados</h2>
    <?php
    $alZona = new DateTimeZone('America/Bogota');

    if (!$alIdL || !$alIdV): ?>
        <div class="al-aviso">No pude identificar a uno de los dos equipos en Highlightly, así que no puedo buscar su alineación. Revisa que ambos aparezcan en verde en <b>verificar-equipos.php</b>.</div>

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