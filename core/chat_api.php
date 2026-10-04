<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$mensaje   = trim((string)($input['mensaje'] ?? ''));
$local     = trim((string)($input['local'] ?? ''));
$visitante = trim((string)($input['visitante'] ?? ''));

if ($mensaje === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el mensaje']);
    exit;
}

$contexto = "Eres el asistente de SANTIAGO AI PREDICTOR PRO, un sitio de estadísticas y "
    . "predicciones de fútbol. Responde en español, de forma clara, breve y con buena onda. "
    . "Puedes hablar tanto de fútbol/estadísticas como de temas generales. "
    . "Si preguntan por apuestas, aclara que las probabilidades son estimaciones estadísticas "
    . "y que deben jugar con responsabilidad.";

// Si el usuario ya seleccionó dos equipos en el predictor, se los pasamos como contexto real
if ($local !== '' && $visitante !== '') {
    $stmt = $conexion->prepare(
        "SELECT equipo_nombre, liga_id, posicion_tabla, partidos_jugados, goles_favor, goles_contra
         FROM estadisticas_reales
         WHERE equipo_nombre IN (:l, :v)"
    );
    $stmt->execute([':l' => $local, ':v' => $visitante]);
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($equipos) {
        $contexto .= " Datos actuales de los equipos en pantalla: " . json_encode($equipos, JSON_UNESCAPED_UNICODE);
    }
}

$payload = [
    'model' => getenv('GROQ_MODEL') ?: 'llama-3.3-70b-versatile',
    'messages' => [
        ['role' => 'system', 'content' => $contexto],
        ['role' => 'user', 'content' => $mensaje],
    ],
    'temperature' => 0.5,
    'max_tokens'  => 400,
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
    CURLOPT_TIMEOUT    => 20,
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

$data     = json_decode($respuesta, true);
$respTexto = $data['choices'][0]['message']['content'] ?? 'No pude generar una respuesta.';

// Guardamos el historial (opcional, no bloquea la respuesta si falla)
try {
    $log = $conexion->prepare(
        "INSERT INTO chat_historial (mensaje, respuesta, local, visitante) VALUES (:m, :r, :l, :v)"
    );
    $log->execute([':m' => $mensaje, ':r' => $respTexto, ':l' => $local ?: null, ':v' => $visitante ?: null]);
} catch (PDOException $e) {
    error_log('No se pudo guardar el historial del chat: ' . $e->getMessage());
}

echo json_encode(['respuesta' => $respTexto], JSON_UNESCAPED_UNICODE);
