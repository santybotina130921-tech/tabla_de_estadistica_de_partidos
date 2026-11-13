<?php
$grNum = fn($v) => ($v !== null && $v !== '') ? (float) $v : null;

$grMetricas = [
    ['Posesión (%)',  $grNum($realL['posesion_promedio'] ?? null),          $grNum($realV['posesion_promedio'] ?? null)],
    ['Remates',       $grNum($realL['remates_promedio'] ?? null),           $grNum($realV['remates_promedio'] ?? null)],
    ['Tiros al arco', $grNum($realL['arco_promedio'] ?? null),              $grNum($realV['arco_promedio'] ?? null)],
    ['Córners',       $grNum($realL['corners_promedio'] ?? null),           $grNum($realV['corners_promedio'] ?? null)],
    ['xG',            $grNum($realL['xg_promedio'] ?? null),                $grNum($realV['xg_promedio'] ?? null)],
    ['Amarillas',     $grNum($realL['tarjetas_amarillas_promedio'] ?? null), $grNum($realV['tarjetas_amarillas_promedio'] ?? null)],
];
$grMetricas = array_values(array_filter($grMetricas, fn($m) => $m[1] !== null && $m[2] !== null));

$grPuntosAcumulados = function (?string $forma): array {
    $acum = [0];
    foreach (str_split((string) $forma) as $r) {
        $acum[] = end($acum) + ($r === 'V' ? 3 : ($r === 'E' ? 1 : 0));
    }
    return $acum;
};
$grFormaL = $grPuntosAcumulados($realL['forma'] ?? null);
$grFormaV = $grPuntosAcumulados($realV['forma'] ?? null);
$grHayForma = count($grFormaL) > 2 && count($grFormaV) > 2;
?>
<div class="graf-wrap">
    <h2>📈 Gráficos de rendimiento</h2>

    <div class="graf-leyenda">
        <span class="graf-chip graf-chip-local"></span><?= htmlspecialchars($L['equipo_nombre']) ?>
        <span class="graf-chip graf-chip-visitante"></span><?= htmlspecialchars($V['equipo_nombre']) ?>
    </div>

    <div class="graf-grid">
        <div class="graf-caja">
            <h3>Promedio por partido (últimos 5)</h3>
            <?php if (!$grMetricas): ?>
                <div class="h2h-aviso">Todavía no hay estadísticas reales de estos equipos para graficar.</div>
            <?php else:
                $grAlto = 20 + count($grMetricas) * 52;
                ?>
                <svg class="graf-svg" viewBox="0 0 600 <?= $grAlto ?>" role="img" aria-label="Comparación de promedios por partido entre los dos equipos">
                    <?php foreach ($grMetricas as $i => [$nombre, $a, $b]):
                        $y = 14 + $i * 52;
                        $max = max($a, $b, 0.01);
                        $wa = round(380 * $a / $max, 1);
                        $wb = round(380 * $b / $max, 1);
                        ?>
                        <text class="graf-texto" x="0" y="<?= $y + 18 ?>"><?= htmlspecialchars($nombre) ?></text>
                        <rect class="graf-barra-local" x="130" y="<?= $y ?>" width="<?= $wa ?>" height="16" rx="4"></rect>
                        <text class="graf-valor" x="<?= 136 + $wa ?>" y="<?= $y + 13 ?>"><?= round($a, 1) ?></text>
                        <rect class="graf-barra-visitante" x="130" y="<?= $y + 20 ?>" width="<?= $wb ?>" height="16" rx="4"></rect>
                        <text class="graf-valor" x="<?= 136 + $wb ?>" y="<?= $y + 33 ?>"><?= round($b, 1) ?></text>
                    <?php endforeach; ?>
                </svg>
            <?php endif; ?>
        </div>

        <div class="graf-caja">
            <h3>Puntos acumulados en los últimos partidos</h3>
            <?php if (!$grHayForma): ?>
                <div class="h2h-aviso">Todavía no hay resultados recientes de estos equipos para graficar.</div>
            <?php else:
                $grN = max(count($grFormaL), count($grFormaV)) - 1;
                $grMax = max(end($grFormaL), end($grFormaV), 3);
                $grX = fn($i) => 50 + $i * (500 / max($grN, 1));
                $grY = fn($v) => 200 - ($v / $grMax) * 170;
                $grPuntos = function (array $serie) use ($grX, $grY): string {
                    $pares = [];
                    foreach ($serie as $i => $v) {
                        $pares[] = round($grX($i), 1) . ',' . round($grY($v), 1);
                    }
                    return implode(' ', $pares);
                };
                ?>
                <svg class="graf-svg" viewBox="0 0 600 240" role="img" aria-label="Evolución de puntos de los dos equipos en sus últimos partidos">
                    <?php foreach ([0, $grMax / 2, $grMax] as $linea): ?>
                        <line class="graf-eje" x1="50" y1="<?= round($grY($linea), 1) ?>" x2="550" y2="<?= round($grY($linea), 1) ?>"></line>
                        <text class="graf-valor" x="10" y="<?= round($grY($linea), 1) + 4 ?>"><?= round($linea, 1) ?></text>
                    <?php endforeach; ?>
                    <?php for ($i = 0; $i <= $grN; $i++): ?>
                        <text class="graf-valor" x="<?= round($grX($i) - 6, 1) ?>" y="228"><?= $i === 0 ? 'Ini' : 'P' . $i ?></text>
                    <?php endfor; ?>
                    <polyline class="graf-linea-local" points="<?= $grPuntos($grFormaL) ?>"></polyline>
                    <polyline class="graf-linea-visitante" points="<?= $grPuntos($grFormaV) ?>"></polyline>
                    <?php foreach ($grFormaL as $i => $v): ?>
                        <circle class="graf-punto-local" cx="<?= round($grX($i), 1) ?>" cy="<?= round($grY($v), 1) ?>" r="4"></circle>
                    <?php endforeach; ?>
                    <?php foreach ($grFormaV as $i => $v): ?>
                        <circle class="graf-punto-visitante" cx="<?= round($grX($i), 1) ?>" cy="<?= round($grY($v), 1) ?>" r="4"></circle>
                    <?php endforeach; ?>
                </svg>
            <?php endif; ?>
        </div>
    </div>
</div>