<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/partidos.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$mensaje   = mb_substr(trim((string) ($input['mensaje'] ?? '')), 0, 600);
$local     = trim((string) ($input['local'] ?? ''));
$visitante = trim((string) ($input['visitante'] ?? ''));
$liga      = trim((string) ($input['liga'] ?? ''));

if ($mensaje === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el mensaje']);
    exit;
}

$contexto = "Eres el asistente de SANTIAGO AI PREDICTOR PRO, un sitio de estadísticas y "
    . "predicciones de fútbol. Responde en español, de forma clara, breve y con buena onda. "
    . "Para cifras, posiciones, partidos y horarios usa SOLO los datos que aparecen abajo; si algo no está "
    . "en esos datos, dilo en vez de inventarlo. Las horas están en hora de Colombia. "
    . "Si preguntan por apuestas, aclara que las probabilidades son estimaciones estadísticas "
    . "y que deben jugar con responsabilidad. "
    . "Fecha y hora actual en Colombia: " . date('Y-m-d H:i') . ".";

if ($local !== '' && $visitante !== '') {
    $stmt = $conexion->prepare(
        "SELECT equipo_nombre, liga_id, posicion_tabla, puntos, partidos_jugados, goles_favor, goles_contra
         FROM estadisticas_reales
         WHERE equipo_nombre IN (:l, :v)"
    );
    $stmt->execute([':l' => $local, ':v' => $visitante]);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($equipos) {
        $contexto .= " Equipos en pantalla: " . json_encode($equipos, JSON_UNESCAPED_UNICODE) . ".";
    }
}

if (preg_match('/^[A-Za-z0-9_]{2,10}$/', $liga) && $liga !== 'TODAS') {
    try {
        $stmt = $conexion->prepare(
            "SELECT equipo_nombre, posicion_tabla, puntos, partidos_jugados, goles_favor, goles_contra
             FROM estadisticas_reales
             WHERE liga_id = :l
             ORDER BY posicion_tabla ASC
             LIMIT 8"
        );
        $stmt->execute([':l' => $liga]);
        $lineas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $lineas[] = $f['posicion_tabla'] . '. ' . $f['equipo_nombre'] . ' (' . ($f['puntos'] ?? '?') . ' pts, ' . $f['partidos_jugados'] . ' PJ, GF ' . $f['goles_favor'] . ', GC ' . $f['goles_contra'] . ')';
        }
        if ($lineas) {
            $contexto .= " Primeros puestos de la liga " . $liga . ": " . implode('; ', $lineas) . ".";
        }
    } catch (Throwable $e) {
        error_log('Chat: no se pudo leer la tabla: ' . $e->getMessage());
    }
}

try {
    $hoy = ptPartidosDelDia('hoy', true);
    $estados = ['vivo' => 'en vivo', 'final' => 'terminado', 'programado' => 'programado', 'aplazado' => 'aplazado'];
    $lineas = [];
    foreach ($hoy['grupos'] as $g) {
        foreach ($g['partidos'] as $p) {
            if (count($lineas) >= 30) {
                break 2;
            }
            $marcador = ($p['gl'] !== null && $p['gv'] !== null && $p['estado'] !== 'programado') ? ' ' . $p['gl'] . '-' . $p['gv'] : '';
            $lineas[] = $g['titulo'] . ': ' . $p['local'] . ' vs ' . $p['visitante'] . ' a las ' . ptHora($p['inicio']) . ' (' . $estados[$p['estado']] . $marcador . ')';
        }
    }
    if ($lineas) {
        $contexto .= " Partidos de hoy: " . implode('; ', $lineas) . ".";
    }
} catch (Throwable $e) {
    error_log('Chat: no se pudieron leer los partidos de hoy: ' . $e->getMessage());
}

$payload = [
    'model' => getenv('GROQ_MODEL') ?: 'openai/gpt-oss-120b',
    'messages' => [
        ['role' => 'system', 'content' => $contexto],
        ['role' => 'user', 'content' => $mensaje],
    ],
    'temperature' => 0.4,
    'max_tokens'  => 500,
];

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . getenv('GROQ_API_KEY'),
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT    => 25,
]);
$respuesta  = curl_exec($ch);
$codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errorCurl  = curl_error($ch);
curl_close($ch);

if ($respuesta === false || $codigoHttp >= 400) {
    error_log("Error Groq ($codigoHttp): $errorCurl | $respuesta");
    http_response_code(502);
    echo json_encode(['error' => 'No se pudo contactar a la IA. Intenta de nuevo.']);
    exit;
}

$data      = json_decode($respuesta, true);
$respTexto = $data['choices'][0]['message']['content'] ?? 'No pude generar una respuesta.';

try {
    $log = $conexion->prepare(
        "INSERT INTO chat_historial (mensaje, respuesta, local, visitante) VALUES (:m, :r, :l, :v)"
    );
    $log->execute([':m' => $mensaje, ':r' => $respTexto, ':l' => $local ?: null, ':v' => $visitante ?: null]);
} catch (PDOException $e) {
    error_log('No se pudo guardar el historial del chat: ' . $e->getMessage());
}

echo json_encode(['respuesta' => $respTexto], JSON_UNESCAPED_UNICODE);