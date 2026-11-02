<?php
$golFilas = [];
try {
    $golStmt = $conexion->prepare(
        "SELECT posicion, jugador, equipo, goles, asistencias, penales, partidos
         FROM goleadores
         WHERE liga_id = ?
         ORDER BY posicion ASC
         LIMIT 15"
    );
    $golStmt->execute([$liga_actual]);
    $golFilas = $golStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Goleadores: ' . $e->getMessage()); // normalmente: falta correr la migración SQL
}
$golEsLatam = (strpos((string) $liga_actual, 'HL_') === 0);
?>
<div class="gol-wrap">
    <h2>⚽ Goleadores</h2>

    <?php if ($golFilas): ?>
        <div class="gol-scroll">
            <table class="goleadores">
                <thead>
                    <tr>
                        <th class="izq">#</th>
                        <th class="izq">Jugador</th>
                        <th class="izq">Equipo</th>
                        <th>PJ</th>
                        <th>Goles</th>
                        <th>Asist.</th>
                        <th>Pen.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($golFilas as $g): ?>
                        <tr>
                            <td class="izq"><?= (int) $g['posicion'] ?></td>
                            <td class="izq jugador"><?= htmlspecialchars($g['jugador']) ?></td>
                            <td class="izq equipo"><?= htmlspecialchars($g['equipo']) ?></td>
                            <td><?= (int) $g['partidos'] ?></td>
                            <td class="goles"><?= (int) $g['goles'] ?></td>
                            <td><?= (int) $g['asistencias'] ?></td>
                            <td><?= (int) $g['penales'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($golEsLatam): ?>
        <div class="h2h-aviso">Los goleadores solo están disponibles para las ligas que vienen de football-data.org (Europa, Brasileirão, Mundial y Eurocopa).</div>

    <?php else: ?>
        <div class="h2h-aviso">Todavía no hay goleadores guardados para esta liga. Corre <b>SYNC EUROPA</b> (y la migración SQL si es la primera vez).</div>
    <?php endif; ?>
</div>