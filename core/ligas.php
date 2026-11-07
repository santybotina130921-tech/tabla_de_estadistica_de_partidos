<?php
function ligaEtiquetas(): array
{
    return [
        'CL'     => '🏆 Champions League',
        'PL'     => '🇬🇧 Premier League',
        'ELC'    => '🇬🇧 Championship',
        'PD'     => '🇪🇸 La Liga',
        'BL1'    => '🇩🇪 Bundesliga',
        'SA'     => '🇮🇹 Serie A',
        'FL1'    => '🇫🇷 Ligue 1',
        'PPL'    => '🇵🇹 Primeira Liga',
        'DED'    => '🇳🇱 Eredivisie',
        'BSA'    => '🇧🇷 Brasileirão',
        'WC'     => '🌍 Copa del Mundo',
        'EC'     => '🇪🇺 Eurocopa',
        'HL_LIB' => '🏆 Copa Libertadores',
        'HL_SUD' => '🥈 Copa Sudamericana',
        'HL_COL' => '🇨🇴 Liga BetPlay',
        'HL_ARG' => '🇦🇷 Liga Profesional Argentina',
        'HL_CHI' => '🇨🇱 Primera Chile',
        'HL_URU' => '🇺🇾 Primera Uruguay',
        'HL_PAR' => '🇵🇾 División Profesional Paraguay',
        'HL_PER' => '🇵🇪 Primera Perú',
        'HL_ECU' => '🇪🇨 Liga Pro Ecuador',
        'HL_MEX' => '🇲🇽 Liga MX',
        'HL_MLS' => '🇺🇸 MLS',
        'HL_SAU' => '🇸🇦 Pro League Arabia Saudita',
    ];
}

function ligasEnBase(PDO $conexion): array
{
    $codigosEnBase = $conexion->query("SELECT DISTINCT liga_id FROM estadisticas_reales")->fetchAll(PDO::FETCH_COLUMN);
    $salida = [];

    foreach (ligaEtiquetas() as $codigo => $etiqueta) {
        if (in_array($codigo, $codigosEnBase, true)) {
            $salida[$codigo] = $etiqueta;
        }
    }
    foreach ($codigosEnBase as $codigo) {
        if (!isset($salida[$codigo])) {
            $salida[$codigo] = $codigo;
        }
    }
    return $salida;
}

function ligasHighlightly(): array
{
    return [
        'HL_COL' => ['id' => 204173, 'nombre' => 'Liga BetPlay (Colombia)',       'pais' => 'CO'],
        'HL_ARG' => ['id' => 109712, 'nombre' => 'Liga Profesional (Argentina)',  'pais' => 'AR'],
        'HL_CHI' => ['id' => 226299, 'nombre' => 'Primera División (Chile)',      'pais' => 'CL'],
        'HL_URU' => ['id' => 228852, 'nombre' => 'Primera División (Uruguay)',    'pais' => 'UY'],
        'HL_PAR' => ['id' => 213534, 'nombre' => 'División Profesional (Paraguay)', 'pais' => 'PY'],
        'HL_PER' => ['id' => 239915, 'nombre' => 'Primera División (Perú)',       'pais' => 'PE'],
        'HL_ECU' => ['id' => 206726, 'nombre' => 'Liga Pro (Ecuador)',            'pais' => 'EC'],
        'HL_MEX' => ['id' => 223746, 'nombre' => 'Liga MX (México)',              'pais' => 'MX'],
        'HL_MLS' => ['id' => 216087, 'nombre' => 'MLS (USA)',                     'pais' => 'US'],
        'HL_SAU' => ['id' => 262041, 'nombre' => 'Pro League (Arabia Saudita)',   'pais' => 'SA'],
        'HL_LIB' => ['id' => 11847,  'nombre' => 'CONMEBOL Libertadores',         'pais' => 'World'],
        'HL_SUD' => ['id' => 10145,  'nombre' => 'CONMEBOL Sudamericana',         'pais' => 'World'],
    ];
}