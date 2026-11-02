<?php
/**
 * core/poisson.php
 *
 * Motor de predicción real basado en la distribución de Poisson.
 * Reemplaza las fórmulas caseras (multiplicadores fijos) por matemática
 * estadística de verdad: a partir del promedio de goles de cada equipo,
 * calcula la probabilidad de cada marcador posible y de ahí se derivan
 * todos los mercados (1X2, over/under, ambos marcan, córners, etc).
 */

/** Probabilidad de que un equipo anote exactamente $k goles, dado su promedio $lambda */
function poissonProb(float $lambda, int $k): float
{
    if ($lambda <= 0) $lambda = 0.01; // evita división por cero / log(0)
    return (pow($lambda, $k) * exp(-$lambda)) / hlFactorial($k);
}

function hlFactorial(int $n): float
{
    $r = 1;
    for ($i = 2; $i <= $n; $i++) $r *= $i;
    return $r;
}

/**
 * Construye la matriz de probabilidad de cada marcador exacto (0-0, 1-0, 2-1, etc)
 * asumiendo independencia entre los goles de local y visitante (estándar en este tipo de modelos).
 */
function poissonGrid(float $lambdaLocal, float $lambdaVisitante, int $maxGoles = 6): array
{
    $grid = [];
    for ($i = 0; $i <= $maxGoles; $i++) {
        for ($j = 0; $j <= $maxGoles; $j++) {
            $grid[$i][$j] = poissonProb($lambdaLocal, $i) * poissonProb($lambdaVisitante, $j);
        }
    }
    return $grid;
}

/** A partir de la matriz, calcula todos los mercados de una vez */
function poissonMercados(array $grid): array
{
    $pLocal = $pEmpate = $pVisitante = $pBTTS = 0;
    $mejorScore = [0, 0];
    $mejorProb = 0;
    $overGoles = []; // over X.5 goles totales

    foreach ($grid as $i => $fila) {
        foreach ($fila as $j => $prob) {
            if ($i > $j) $pLocal += $prob;
            elseif ($i == $j) $pEmpate += $prob;
            else $pVisitante += $prob;

            if ($i > 0 && $j > 0) $pBTTS += $prob;

            if ($prob > $mejorProb) { $mejorProb = $prob; $mejorScore = [$i, $j]; }

            $totalGoles = $i + $j;
            for ($linea = 0; $linea <= 9; $linea++) {
                if ($totalGoles > $linea + 0.5) {
                    $overGoles[$linea] = ($overGoles[$linea] ?? 0) + $prob;
                }
            }
        }
    }

    return [
        'local'      => round($pLocal * 100),
        'empate'     => round($pEmpate * 100),
        'visitante'  => round($pVisitante * 100),
        'btts'       => round($pBTTS * 100),
        'marcador'   => $mejorScore,
        'over'       => array_map(fn($v) => round($v * 100), $overGoles), // ['0'=>%, '1'=>%, '2'=>%, ...]
    ];
}

/** Probabilidad de más de $linea (ej. 6.5) para cualquier variable Poisson (usado para córners) */
function poissonOverLinea(float $lambda, float $linea, int $maxK = 25): float
{
    $acumulado = 0;
    for ($k = 0; $k <= $maxK; $k++) {
        if ($k > $linea) $acumulado += poissonProb($lambda, $k);
    }
    return round($acumulado * 100);
}

/**
 * Promedio de goles por equipo y partido en las ligas de los equipos (la referencia
 * contra la que se mide qué tan bueno es un ataque o una defensa).
 */
function mediaLigaDe(array $equipos, array $ligas): float
{
    $gf = 0;
    $pj = 0;
    foreach ($equipos as $eq) {
        if (in_array($eq['liga_id'], $ligas, true)) {
            $gf += (int) $eq['goles_favor'];
            $pj += (int) $eq['partidos_jugados'];
        }
    }
    return $pj > 0 ? $gf / $pj : 1.35;
}

/**
 * Predicción de un partido: $home juega de local y $away de visitante (filas de estadisticas_reales).
 *
 * Supuestos (no están calibrados contra miles de partidos, son razonables pero simples):
 *  - Con pocos partidos jugados, el promedio de cada equipo se acerca al de la liga
 *    (equivale a sumarle 6 partidos "promedio"), para no exagerar con muestras chicas.
 *  - Ventaja de local fija: el local rinde x1.12 y el visitante x0.90.
 */
function modeloPoisson(array $home, array $away, float $mediaLiga): array
{
    $k = 6;
    $suave = function (float $totalGoles, int $pj) use ($mediaLiga, $k): float {
        $prom = $pj > 0 ? $totalGoles / $pj : $mediaLiga;
        $w = $pj / ($pj + $k);
        return $w * $prom + (1 - $w) * $mediaLiga;
    };

    $pjH = (int) $home['partidos_jugados'];
    $pjA = (int) $away['partidos_jugados'];
    $gfH = $suave((float) $home['goles_favor'], $pjH);
    $gcH = $suave((float) $home['goles_contra'], $pjH);
    $gfA = $suave((float) $away['goles_favor'], $pjA);
    $gcA = $suave((float) $away['goles_contra'], $pjA);

    $media = max($mediaLiga, 0.5);
    $lambdaH = min(4.5, max(0.2, $gfH * $gcA / $media * 1.12));
    $lambdaA = min(4.5, max(0.2, $gfA * $gcH / $media * 0.90));

    $mk = poissonMercados(poissonGrid($lambdaH, $lambdaA, 8));
    $pH = (int) $mk['local'];
    $pE = (int) $mk['empate'];

    return [
        'lambda_local'     => $lambdaH,
        'lambda_visitante' => $lambdaA,
        'local'            => $pH,
        'empate'           => $pE,
        'visitante'        => 100 - $pH - $pE,
        'marcador'         => $mk['marcador'],
        'btts'             => (int) $mk['btts'],
        'over'             => $mk['over'], // over[2] = más de 2.5 goles
    ];
}