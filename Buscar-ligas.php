<?php
// buscar-ligas.php — ponlo en la RAÍZ del proyecto, úsalo una sola vez para
// encontrar el leagueId de cada liga en Highlightly, y bórralo después.
require_once __DIR__ . '/config/env.php';

$apiKey = getenv('HIGHLIGHTLY_API_KEY');
$nombre = $_GET['nombre'] ?? '';
$pais   = $_GET['pais'] ?? '';

echo "<body style='background:#0b0e11; color:white; font-family:sans-serif; padding:20px;'>";
echo "<h1>🔎 Buscar liga en Highlightly</h1>";
echo "<form method='GET' style='margin-bottom:20px;'>
        Nombre de la liga: <input name='nombre' value='" . htmlspecialchars($nombre) . "'>
        País: <input name='pais' value='" . htmlspecialchars($pais) . "'>
        <button type='submit'>Buscar</button>
      </form>";

if (!$apiKey) {
    echo "<p style='color:#ff4444;'>⛔ Falta HIGHLIGHTLY_API_KEY en tu .env.</p>";
    exit;
}

if ($nombre || $pais) {
    $params = [];
    if ($nombre) $params['leagueName'] = $nombre;
    if ($pais)   $params['countryName'] = $pais;

    $url = "https://soccer.highlightly.net/leagues?" . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["x-rapidapi-key: $apiKey"],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $res = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) {
        echo "<p style='color:#ff4444;'>HTTP $http: " . htmlspecialchars($res) . "</p>";
    } else {
        $data = json_decode($res, true);
        echo "<pre style='background:#111; padding:15px; border-radius:10px;'>";
        foreach ($data['data'] ?? [] as $liga) {
            $temporadas = implode(', ', array_column($liga['seasons'] ?? [], 'season'));
            echo "ID: {$liga['id']}  |  Nombre: {$liga['name']}  |  País: {$liga['country']['name']}  |  Temporadas: $temporadas\n";
        }
        echo "</pre>";
    }
}