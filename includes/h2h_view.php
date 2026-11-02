<?php
require_once __DIR__ . '/../core/h2h.php';

$h2hKey = getenv('HIGHLIGHTLY_API_KEY');
$h2hIdL = (int) ($realL['highlightly_id'] ?? 0);
$h2hIdV = (int) ($realV['highlightly_id'] ?? 0);
$h2hDatos = ($h2hIdL && $h2hIdV) ? hlH2H($h2hKey, $h2hIdL, $h2hIdV) : null;
?>
<div class="h2h-wrap">
    <h2>⚔️ Cara a cara y forma</h2>

    <div class="h2h-forma">
        <div>
            <small><?= htmlspecialchars($L['equipo_nombre']) ?> · últimos 5</small>
            <?= formaHtml($realL['forma'] ?? null) ?>
        </div>
        <div class="vis">
            <small><?= htmlspecialchars($V['equipo_nombre']) ?> · últimos 5</small>
            <?= formaHtml($realV['forma'] ?? null) ?>
        </div>
    </div>

    <?php if ($h2hDatos === null): ?>
        <div class="h2h-aviso">No pude identificar a uno de los dos equipos en Highlightly, así que no puedo buscar sus enfrentamientos.</div>

    <?php elseif (empty($h2hDatos['partidos'])): ?>
        <div class="h2h-aviso">No encontré enfrentamientos previos entre estos dos equipos<?= $h2hDatos['http'] !== 200 ? ' (la API respondió ' . (int) $h2hDatos['http'] . ')' : '' ?>.</div>

    <?php else:
        $h2hLista = array_slice($h2hDatos['partidos'], 0, 8);
        $vicL = $emp = $vicV = $golesTot = $ambosMarcan = 0;
        foreach ($h2hLista as $p) {
            $gL = ($p['home_id'] === $h2hIdL) ? $p['gh'] : $p['ga'];
            $gV = ($p['home_id'] === $h2hIdL) ? $p['ga'] : $p['gh'];
            if ($gL > $gV) $vicL++; elseif ($gL === $gV) $emp++; else $vicV++;
            $golesTot += $gL + $gV;
            if ($gL > 0 && $gV > 0) $ambosMarcan++;
        }
        $h2hN = count($h2hLista);
        ?>
        <div class="h2h-resumen">
            Últimos <b><?= $h2hN ?></b> cruces: <b><?= htmlspecialchars($L['equipo_nombre']) ?></b> ganó <b><?= $vicL ?></b> ·
            empates <b><?= $emp ?></b> · <b><?= htmlspecialchars($V['equipo_nombre']) ?></b> ganó <b><?= $vicV ?></b><br>
            <?= round($golesTot / $h2hN, 1) ?> goles por partido · ambos marcaron en <b><?= $ambosMarcan ?></b> de <?= $h2hN ?>
        </div>
        <?php foreach ($h2hLista as $p): ?>
            <div class="h2h-fila">
                <div class="fecha"><?= $p['fecha'] ? date('d/m/Y', strtotime($p['fecha'])) : '' ?></div>
                <div class="cruce"><?= htmlspecialchars($p['home']) ?><span><?= $p['gh'] ?> - <?= $p['ga'] ?></span><?= htmlspecialchars($p['away']) ?></div>
                <div class="liga"><?= htmlspecialchars($p['liga']) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($_GET['debug']) && $h2hDatos): ?>
        <p class="h2h-debug">debug H2H → HTTP <?= (int) $h2hDatos['http'] ?> · <?= htmlspecialchars($h2hDatos['crudo']) ?></p>
    <?php endif; ?>
</div>