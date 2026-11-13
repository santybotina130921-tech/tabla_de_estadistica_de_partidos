<?php
/**
 * core/predicciones.php
 *
 * Historial de aciertos: guarda cada predicción ANTES del partido y después la
 * compara con el resultado real. Solo cuentan predicciones de partidos reales
 * programados (con su fecha), nunca cruces hipotéticos, y nunca se modifican
 * una vez empieza el partido.
 */
require_once __DIR__ . '/poisson.php';
require_once __DIR__ . '/stats_reales.php'; // hlLlamar()

/**
 * Guarda (o actualiza, mientras el partido no haya empezado) la predicción de un
 * partido real. Se calcula con el equipo que de verdad juega de local en ese partido.
 * Si se pasa $hl, guarda también las probabilidades de Highlightly para compararlas luego.
 */
function guardarPrediccionReal(PDO $c, array $partido, array $L, array $V, int $idL, array $equipos, ?array $hl = null): void
{
    if ((int) $partido['inicio'] <= time()) {
        return;
    }
    $localId = (int) ($partido['local_id'] ?? 0);
    if (!$localId) {
        return;
    }

    $homeEsL = ($localId === $idL);
    $home = $homeEsL ? $L : $V;
    $away = $homeEsL ? $V : $L;

    $media = mediaLigaDe($equipos, array_values(array_unique([$home['liga_id'], $away['liga_id']])));
    $m = modeloPoisson($home, $away, $media);

    $params = [
        ':id'     => (int) $partido['id'],
        ':inicio' => gmdate('Y-m-d H:i:s', (int) $partido['inicio']) . '+00',
        ':liga'   => $partido['liga'] ?? '',
        ':ln'     => $partido['local'] ?? '',
        ':vn'     => $partido['visitante'] ?? '',
        ':pl'     => $m['local'],
        ':pe'     => $m['empate'],
        ':pv'     => $m['visitante'],
        ':ml'     => $m['marcador'][0],
        ':mv'     => $m['marcador'][1],
        ':o25'    => $m['over'][2] ?? 0,
        ':bt'     => $m['btts'],
    ];

    $sqlNuevo = "INSERT INTO predicciones
              (partido_id, inicio, liga, local_nombre, visitante_nombre, prob_local, prob_empate, prob_visitante,
               marcador_local, marcador_visitante, prob_over25, prob_btts, hl_local, hl_empate, hl_visitante)
            VALUES (:id, CAST(:inicio AS timestamptz), :liga, :ln, :vn, :pl, :pe, :pv, :ml, :mv, :o25, :bt, :hl1, :hle, :hl2)
            ON CONFLICT (partido_id) DO UPDATE SET
              inicio = EXCLUDED.inicio, liga = EXCLUDED.liga,
              prob_local = EXCLUDED.prob_local, prob_empate = EXCLUDED.prob_empate, prob_visitante = EXCLUDED.prob_visitante,
              marcador_local = EXCLUDED.marcador_local, marcador_visitante = EXCLUDED.marcador_visitante,
              prob_over25 = EXCLUDED.prob_over25, prob_btts = EXCLUDED.prob_btts,
              hl_local = EXCLUDED.hl_local, hl_empate = EXCLUDED.hl_empate, hl_visitante = EXCLUDED.hl_visitante,
              actualizado_en = now()
            WHERE predicciones.inicio > now() AND predicciones.resultado_local IS NULL";

    $sqlBasico = "INSERT INTO predicciones
              (partido_id, inicio, liga, local_nombre, visitante_nombre, prob_local, prob_empate, prob_visitante,
               marcador_local, marcador_visitante, prob_over25, prob_btts)
            VALUES (:id, CAST(:inicio AS timestamptz), :liga, :ln, :vn, :pl, :pe, :pv, :ml, :mv, :o25, :bt)
            ON CONFLICT (partido_id) DO UPDATE SET
              inicio = EXCLUDED.inicio, liga = EXCLUDED.liga,
              prob_local = EXCLUDED.prob_local, prob_empate = EXCLUDED.prob_empate, prob_visitante = EXCLUDED.prob_visitante,
              marcador_local = EXCLUDED.marcador_local, marcador_visitante = EXCLUDED.marcador_visitante,
              prob_over25 = EXCLUDED.prob_over25, prob_btts = EXCLUDED.prob_btts, actualizado_en = now()
            WHERE predicciones.inicio > now() AND predicciones.resultado_local IS NULL";

    try {
        $c->prepare($sqlNuevo)->execute($params + [
            ':hl1' => $hl['local'] ?? null,
            ':hle' => $hl['empate'] ?? null,
            ':hl2' => $hl['visitante'] ?? null,
        ]);
    } catch (PDOException $e) {
        error_log('Predicción guardada sin columnas de Highlightly (corre migracion_v3.sql): ' . $e->getMessage());
        $c->prepare($sqlBasico)->execute($params);
    }
}

/**
 * Busca el resultado de las predicciones cuyo partido ya terminó. Gasta 1 petición
 * por partido y, si no está listo, no vuelve a intentar hasta 6 horas después.
 * Devuelve cuántos resultados se registraron.
 */
function resolverPendientes(PDO $c, ?string $apiKey, int $max = 3): int
{
    if (!$apiKey) {
        return 0;
    }

    $ids = $c->query(
        "SELECT partido_id FROM predicciones
         WHERE resultado_local IS NULL
           AND inicio < now() - interval '3 hours'
           AND intentos < 8
           AND (ultimo_intento IS NULL OR ultimo_intento < now() - interval '6 hours')
         ORDER BY inicio ASC
         LIMIT " . (int) $max
    )->fetchAll(PDO::FETCH_COLUMN);

    $resueltos = 0;
    foreach ($ids as $id) {
        $r = hlLlamar("https://soccer.highlightly.net/matches/$id", $apiKey);
        $d = json_decode((string) $r['body'], true);
        if (is_array($d) && isset($d[0]) && is_array($d[0])) {
            $d = $d[0];
        }
        $desc = strtolower((string) ($d['state']['description'] ?? ''));
        $terminado = ($r['http'] === 200) && (strpos($desc, 'finish') !== false || strpos($desc, 'full time') !== false);

        if ($terminado && preg_match('/(\d+)\s*-\s*(\d+)/', (string) ($d['state']['score']['current'] ?? ''), $g)) {
            $c->prepare(
                "UPDATE predicciones
                 SET resultado_local = :a, resultado_visitante = :b, resuelto_en = now(),
                     intentos = intentos + 1, ultimo_intento = now()
                 WHERE partido_id = :id"
            )->execute([':a' => (int) $g[1], ':b' => (int) $g[2], ':id' => $id]);
            $resueltos++;
        } else {
            $c->prepare(
                "UPDATE predicciones SET intentos = intentos + 1, ultimo_intento = now() WHERE partido_id = :id"
            )->execute([':id' => $id]);
        }
        usleep(300000);
    }
    return $resueltos;
}

function pickDe(int $l, int $e, int $v): string
{
    $max = max($l, $e, $v);
    if ($l === $max) return 'L';
    if ($v === $max) return 'V';
    return 'E';
}

/** Resultado que la predicción consideraba más probable: L, E o V. */
function pickPrediccion(array $f): string
{
    return pickDe((int) $f['prob_local'], (int) $f['prob_empate'], (int) $f['prob_visitante']);
}

function resultadoReal(array $f): string
{
    $a = (int) $f['resultado_local'];
    $b = (int) $f['resultado_visitante'];
    return $a > $b ? 'L' : ($a === $b ? 'E' : 'V');
}

/** Cuántas veces acertó el modelo, solo con partidos ya terminados. */
function estadisticasAciertos(PDO $c): array
{
    $filas = $c->query("SELECT * FROM predicciones WHERE resultado_local IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);

    $t = ['total' => 0, 'x12' => 0, 'exacto' => 0, 'over25' => 0, 'over25_n' => 0, 'btts' => 0, 'btts_n' => 0, 'hl_n' => 0, 'hl_x12' => 0, 'modelo_en_hl' => 0];
    foreach ($filas as $f) {
        $rl = (int) $f['resultado_local'];
        $rv = (int) $f['resultado_visitante'];
        $t['total']++;

        if (pickPrediccion($f) === resultadoReal($f)) {
            $t['x12']++;
        }
        if (($f['hl_local'] ?? null) !== null) {
            $t['hl_n']++;
            $real = resultadoReal($f);
            if (pickDe((int) $f['hl_local'], (int) $f['hl_empate'], (int) $f['hl_visitante']) === $real) {
                $t['hl_x12']++;
            }
            if (pickPrediccion($f) === $real) {
                $t['modelo_en_hl']++;
            }
        }
        if ((int) $f['marcador_local'] === $rl && (int) $f['marcador_visitante'] === $rv) {
            $t['exacto']++;
        }
        if ($f['prob_over25'] !== null) {
            $t['over25_n']++;
            if (((int) $f['prob_over25'] >= 50) === (($rl + $rv) > 2)) {
                $t['over25']++;
            }
        }
        if ($f['prob_btts'] !== null) {
            $t['btts_n']++;
            if (((int) $f['prob_btts'] >= 50) === ($rl > 0 && $rv > 0)) {
                $t['btts']++;
            }
        }
    }
    return $t;
}