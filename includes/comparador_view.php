<?php
require_once __DIR__ . '/../core/detalle.php';
require_once __DIR__ . '/../core/poisson.php';

$cmpKey = getenv('HIGHLIGHTLY_API_KEY');
$cmpPartido = $proximoReal ?? null;
$cmpDetalle = ($cmpPartido && !empty($cmpPartido['id'])) ? hlDetalleCompleto($cmpKey, (int) $cmpPartido['id']) : null;

if ($cmpPartido):
    $cmpIdL = (int) ($realL['highlightly_id'] ?? 0);
    $cmpLocalEsL = ((int) ($cmpPartido['local_id'] ?? 0)) === $cmpIdL;
    $cmpHome = $cmpLocalEsL ? $L : $V;
    $cmpAway = $cmpLocalEsL ? $V : $L;
    $cmpMedia = mediaLigaDe($equipos, array_values(array_unique([$cmpHome['liga_id'], $cmpAway['liga_id']])));
    $cmpModelo = modeloPoisson($cmpHome, $cmpAway, $cmpMedia);
    $cmpHL = $cmpDetalle['prediccion'] ?? null;
    $cmpNombreHome = $cmpPartido['local'] ?: $cmpHome['equipo_nombre'];
    $cmpNombreAway = $cmpPartido['visitante'] ?: $cmpAway['equipo_nombre'];
    $cmpFilas = [
        ['Gana ' . $cmpNombreHome, $cmpModelo['local'], $cmpHL['local'] ?? null],
        ['Empate', $cmpModelo['empate'], $cmpHL['empate'] ?? null],
        ['Gana ' . $cmpNombreAway, $cmpModelo['visitante'], $cmpHL['visitante'] ?? null],
    ];
    $cmpFavorito = function (int $l, int $e, int $v): string {
        $max = max($l, $e, $v);
        return $l === $max ? 'L' : ($v === $max ? 'V' : 'E');
    };
    ?>
    <div class="cmp-wrap">
        <h2>🤖 Tu modelo vs el de Highlightly</h2>

        <?php if (!$cmpHL): ?>
            <div class="h2h-aviso">Highlightly todavía no publica probabilidades para este partido (<?= htmlspecialchars($cmpNombreHome) ?> vs <?= htmlspecialchars($cmpNombreAway) ?>).</div>
        <?php else: ?>
            <div class="gol-scroll">
                <table class="goleadores">
                    <thead>
                        <tr>
                            <th class="izq">Resultado</th>
                            <th>Tu modelo</th>
                            <th>Highlightly</th>
                            <th>Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cmpFilas as [$etiqueta, $mio, $suyo]):
                            $dif = $mio - $suyo;
                            ?>
                            <tr>
                                <td class="izq jugador"><?= htmlspecialchars($etiqueta) ?></td>
                                <td class="goles"><?= (int) $mio ?>%</td>
                                <td class="goles"><?= (int) $suyo ?>%</td>
                                <td class="<?= abs($dif) >= 10 ? 'cmp-alta' : '' ?>"><?= $dif > 0 ? '+' : '' ?><?= (int) $dif ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hoy-nota">
                <?php if ($cmpFavorito($cmpModelo['local'], $cmpModelo['empate'], $cmpModelo['visitante']) === $cmpFavorito($cmpHL['local'], $cmpHL['empate'], $cmpHL['visitante'])): ?>
                    Los dos modelos coinciden en el resultado más probable.
                <?php else: ?>
                    Los dos modelos no coinciden en el resultado más probable: úsalo como señal de que hay incertidumbre.
                <?php endif; ?>
                Son estimaciones, no resultados seguros. El historial mide cuál acierta más.
            </p>
        <?php endif; ?>

        <?php if (!empty($cmpPartido['id'])): ?>
            <p class="hoy-nota"><a class="hoy-analizar" href="partido.php?id=<?= (int) $cmpPartido['id'] ?>">Ver el resumen completo del partido →</a></p>
        <?php endif; ?>
    </div>
<?php endif; ?>