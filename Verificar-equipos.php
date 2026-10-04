<?php
// verificar-equipos.php — revisa TODOS los equipos de TODAS las ligas contra
// Highlightly de una sola vez. Los que ya se verificaron antes (están en
// equipo_stats_reales) se muestran al instante sin gastar cupo de la API;
// solo se consulta en vivo a los que nunca se han revisado.
// Ponlo en la raíz, úsalo, y bórralo cuando termines.
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/stats_reales.php';

$apiKey = getenv('HIGHLIGHTLY_API_KEY');

// Modo AJAX: verifica un equipo puntual y GUARDA el resultado en caché
// (encontrado o no) para que la próxima corrida no lo vuelva a consultar.
if (isset($_GET['equipo_check'])) {
    header('Content-Type: application/json; charset=utf-8');
    $nombre = $_GET['equipo_check'];
    $encontrado = hlResolverEquipo($nombre, $apiKey);
    $hid = $encontrado['id'] ?? null;

    $conexion->prepare(
        "INSERT INTO equipo_stats_reales (equipo_nombre, highlightly_id, actualizado_en)
         VALUES (:n, :id, now())
         ON CONFLICT (equipo_nombre) DO UPDATE SET highlightly_id = EXCLUDED.highlightly_id, actualizado_en = now()"
    )->execute([':n' => $nombre, ':id' => $hid]);

    echo json_encode([
        'nombre' => $nombre, 'encontrado' => !empty($hid),
        'hl_nombre' => $encontrado['name'] ?? null, 'hl_id' => $hid,
        'candidato_usado' => $encontrado['candidato_usado'] ?? null,
        'ultimo_http' => $encontrado['ultimo_http'] ?? null,
        'ultimo_body' => $encontrado['ultimo_body'] ?? null,
    ]);
    exit;
}

// Todos los equipos de todas las ligas, de una
$equipos = $conexion->query("SELECT DISTINCT equipo_nombre, liga_id FROM estadisticas_reales ORDER BY liga_id, equipo_nombre")->fetchAll(PDO::FETCH_ASSOC);

// Lo que ya está en caché (verificado antes, con o sin éxito)
$cacheStmt = $conexion->query("SELECT equipo_nombre, highlightly_id FROM equipo_stats_reales");
$cache = [];
foreach ($cacheStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $cache[$row['equipo_nombre']] = $row['highlightly_id'];
}

$pendientes = [];
$yaVerificados = ['ok' => 0, 'fail' => 0];
foreach ($equipos as $e) {
    if (array_key_exists($e['equipo_nombre'], $cache)) {
        $cache[$e['equipo_nombre']] ? $yaVerificados['ok']++ : $yaVerificados['fail']++;
    } else {
        $pendientes[] = $e;
    }
}

echo "<body style='background:#0b0e11; color:white; font-family:sans-serif; padding:20px;'>";
echo "<h1>🔍 Verificar TODOS los equipos vs Highlightly</h1>";
echo "<p>Total en tu base: " . count($equipos) . " equipos, en " . count(array_unique(array_column($equipos, 'liga_id'))) . " ligas.</p>";
echo "<p>Ya verificados antes (desde caché, sin gastar API): <b style='color:#0f0;'>{$yaVerificados['ok']} encontrados</b>, <b style='color:#f44;'>{$yaVerificados['fail']} no encontrados</b>.</p>";
echo "<p>Pendientes por verificar ahora (van a gastar cupo de la API): <b style='color:#ff0;'>" . count($pendientes) . "</b></p>";

echo "<h3>Ligas detectadas en tu base de datos:</h3><ul>";
$porLiga = [];
foreach ($equipos as $e) $porLiga[$e['liga_id']] = ($porLiga[$e['liga_id']] ?? 0) + 1;
foreach ($porLiga as $liga => $n) echo "<li>$liga ($n equipos)</li>";
echo "</ul>";

echo "<h3>Resultado:</h3><div id='resultado'></div>";

echo "<script>\n";
echo "const pendientes = " . json_encode(array_column($pendientes, 'equipo_nombre')) . ";\n";
?>
const div = document.getElementById('resultado');

function textoResultado(data) {
    if (data.encontrado) {
        return ' -> HL: ' + data.hl_nombre + ' (id ' + data.hl_id + ', probado como ' + data.candidato_usado + ')';
    }
    var nota = (data.ultimo_http == 429) ? ' = LIMITE DE RAFAGA' : '';
    return ' -> NO ENCONTRADO (ultimo HTTP: ' + data.ultimo_http + nota + ', resp: ' + data.ultimo_body + ')';
}

async function verificarPendientes() {
    if (pendientes.length === 0) {
        div.innerHTML = '<p style="color:#0f0;">No hay pendientes — todo tu roster ya fue verificado en corridas anteriores. Si agregaste ligas nuevas, corre un SYNC primero.</p>';
        return;
    }
    for (const eq of pendientes) {
        const p = document.createElement('div');
        p.textContent = '⏳ ' + eq + '...';
        div.appendChild(p);
        try {
            const res = await fetch('verificar-equipos.php?equipo_check=' + encodeURIComponent(eq));
            const data = await res.json();
            p.textContent = (data.encontrado ? '✅ ' : '⚠️ ') + eq + textoResultado(data);
            p.style.color = data.encontrado ? '#0f0' : '#f44';
        } catch (e) {
            p.textContent = '⚠️ ' + eq + ': error de red';
        }
        await new Promise(r => setTimeout(r, 800));
    }
    div.insertAdjacentHTML('beforeend', '<p style="color:#0f0; margin-top:10px;">✅ Listo. La próxima vez que corras esto, estos ya van a salir desde caché.</p>');
}
verificarPendientes();
<?php
echo "</script>";